<?php

use App\Livewire\Attributes\RequiresActivatedAccount;
use App\Models\Comment;
use App\Services\CommentService;
use App\Support\Markdown;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Creates a comment or reply (or edits an existing comment) through
 * CommentService, which enforces antispam, nesting depth and the edit window.
 *
 * Create/reply posts inline: on success it dispatches `comment-created` so the
 * comment-tree reloads without a full page reload. Edit mode (mounted with a
 * comment hashid) redirects back to the post after saving.
 */
new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $postId = 0;

    #[Locked]
    public ?int $parentId = null;

    #[Locked]
    public ?int $commentId = null;

    public string $content = '';

    /** Inline-editor upload: the file currently being processed (Livewire temp). */
    public $pendingImage = null;

    /** media_ids inserted inline into a NEW comment, attached to the pivot on save. */
    public array $pendingMediaIds = [];

    /** Reply forms render compact and collapse after submit. */
    public bool $isReply = false;

    /** Edit mode redirects instead of dispatching. */
    public bool $isEdit = false;

    /** Whether image uploads are allowed (admin setting). */
    public bool $allowImages = false;

    public function mount(int $postId = 0, ?int $parentId = null, ?string $editHashid = null): void
    {
        $this->postId = $postId;
        $this->parentId = $parentId;
        $this->isReply = $parentId !== null;
        $this->allowImages = (bool) config('orbita.comments.allow_images', true);

        if ($editHashid !== null) {
            $comment = Comment::query()->where('hashid', $editHashid)->firstOrFail();
            $this->commentId = (int) $comment->id;
            $this->postId = (int) $comment->post_id;
            $this->content = (string) $comment->content;
            $this->isEdit = true;
        }
    }

    /**
     * Inline-editor upload: stores the pending image as media, attaches it to the
     * comment (immediately when editing, deferred to save for a new comment) and
     * returns its served URL for `![](url)` insertion.
     * Server-side gate: drops everything when comments.allow_images is off.
     */
    #[RequiresActivatedAccount(silent: true)]
    public function insertImage(): string
    {
        if (! $this->allowImages || ! $this->canUploadImage()) {
            return '';
        }

        $maxKb = (int) (((int) config('orbita.posts.image_max_size', 10485760)) / 1024);

        try {
            $this->validate([
                'pendingImage' => ['required', 'image', 'mimes:'.\App\Support\ImageFormats::mimesRule(), 'max:'.$maxKb],
            ]);
        } catch (\Illuminate\Validation\ValidationException) {
            $this->pendingImage = null;

            return '';
        }

        $userId = (int) auth()->id();
        $result = app(\App\Services\ImageService::class)->uploadImage([
            'name' => $this->pendingImage->getClientOriginalName(),
            'tmp_name' => $this->pendingImage->getRealPath(),
            'size' => $this->pendingImage->getSize(),
            'type' => $this->pendingImage->getMimeType(),
        ], 'posts', $userId);

        $mediaId = (int) ($result['media_id'] ?? 0);
        $this->pendingImage = null;

        if (! ($result['success'] ?? false) || $mediaId === 0) {
            return '';
        }

        if ($this->isEdit && $this->commentId !== null) {
            $comment = Comment::query()->find($this->commentId);
            if ($comment !== null) {
                $order = (int) $comment->media()->count();
                $comment->media()->syncWithoutDetaching([$mediaId => ['display_order' => $order]]);
            }
        } else {
            $this->pendingMediaIds[] = $mediaId;
        }

        $media = \App\Models\Media::query()->find($mediaId);

        return $media !== null ? \App\Support\ImageUrl::original((string) $media->path) : '';
    }

    private function canUploadImage(): bool
    {
        $limit = (int) config('orbita.comments.max_images', 4);
        $existing = $this->isEdit && $this->commentId !== null
            ? (int) Comment::query()->find($this->commentId)?->media()->count()
            : 0;

        return (count($this->pendingMediaIds) + $existing) < $limit;
    }

    #[RequiresActivatedAccount]
    public function save(): void
    {
        if (! $this->allowImages) {
            $this->pendingImage = null;
            $this->pendingMediaIds = [];
        }

        $this->validate(['content' => ['required', 'string', 'min:1', 'max:10000']]);

        $service = app(CommentService::class);
        $userId = (int) auth()->id();

        if ($this->isEdit) {
            $comment = Comment::query()->findOrFail($this->commentId);
            try {
                $service->updateComment($comment, ['content' => $this->content]);
            } catch (\RuntimeException $e) {
                $this->addError('content', $e->getMessage());

                return;
            }

            $this->redirectRoute('posts.show', [
                'hashid' => $comment->post->hashid,
                'slug' => $comment->post->slug,
            ]);

            return;
        }

        $antispam = $service->checkAntispam($userId, (int) $this->postId, $this->content);
        if (! $antispam['allowed']) {
            $this->addError('content', $antispam['reason'] ?? 'Aguarde para comentar novamente.');

            return;
        }

        try {
            $comment = $service->createComment([
                'post_id' => $this->postId,
                'parent_id' => $this->parentId,
                'user_id' => $userId,
                'content' => $this->content,
            ]);
        } catch (\RuntimeException $e) {
            $this->addError('content', $e->getMessage());

            return;
        }

        if ($this->allowImages && $this->pendingMediaIds !== []) {
            $order = (int) $comment->media()->count();
            $attach = [];
            foreach ($this->pendingMediaIds as $mediaId) {
                $attach[(int) $mediaId] = ['display_order' => $order++];
            }
            $comment->media()->syncWithoutDetaching($attach);
        }

        $this->content = '';
        $this->pendingMediaIds = [];

        $this->dispatch('comment-created', postId: $this->postId, parentId: $this->parentId, commentId: (int) $comment->id);

        if ($this->isReply) {
            $this->dispatch('reply-posted', parentId: $this->parentId);
        }
    }
}; ?>

<form wire:submit="save" onsubmit="this.querySelector('textarea')?.blur()" class="{{ $isReply ? 'mt-3' : '' }}">
    @auth
        @if (auth()->user()->isPendingActivation())
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <a href="{{ route('verification.notice') }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">Ative sua conta</a> para comentar.
            </div>
        @else
        <div class="max-w-[640px] rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
            @unless ($isReply || $isEdit)
                <label for="comment-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Deixe um comentário
                </label>
            @endunless
            <x-markdown-editor
                id="comment-{{ $this->getId() }}"
                wire:model="content"
                rows="{{ $isReply ? 3 : 4 }}"
                placeholder="{{ $isReply ? 'Escreva uma resposta...' : 'Escreva um comentário...' }}"
                :allow-image="$allowImages && ! $isEdit"
                preview-class="min-h-[260px] max-w-none md-content--comment" />

            @error('content')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="mt-2 flex items-center justify-end gap-2">
                <div class="flex items-center gap-2">
                    @if ($isReply)
                        <button type="button" x-on:click="$dispatch('cancel-reply')"
                                class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                            Cancelar
                        </button>
                    @endif
                    <button type="submit" wire:loading.attr="disabled"
                            class="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Salvar' : ($isReply ? 'Responder' : 'Publicar') }}</span>
                        <span wire:loading wire:target="save">Enviando...</span>
                    </button>
                </div>
            </div>
        </div>
        @endif
    @endauth

    @guest
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <a href="{{ route('login', ['redirect_to' => url()->current()]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">Faça login</a> para comentar.
        </div>
    @endguest
</form>
