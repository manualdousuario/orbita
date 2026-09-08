@props([
    'rows' => 6,
    'placeholder' => 'Escreva em Markdown...',
    'allowImage' => false,
    'textareaClass' => 'min-h-[260px] max-w-[640px]',
    'previewClass' => 'min-h-[260px] max-w-none',
])

<div x-data="markdownEditor(@js(['allowImage' => $allowImage]))"
     x-on:editor-reset="resetEditor()"
     class="markdown-editor">
    <div class="toolbar-scroll mb-1 flex flex-nowrap items-center gap-1 overflow-x-auto overflow-y-hidden overscroll-x-contain rounded-md border border-gray-300 bg-gray-50 p-0 dark:border-gray-700 dark:bg-gray-800">
        @php
            $btn = 'inline-flex h-9 w-8 shrink-0 items-center justify-center text-gray-600 hover:bg-gray-200 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white';
        @endphp
        <button type="button" @click="apply('bold')" :disabled="mode === 'preview'" title="Negrito (Ctrl+B)" aria-label="Negrito" class="{{ $btn }}">
            <x-heroicon-o-bold class="h-4 w-4" />
        </button>
        <button type="button" @click="apply('italic')" :disabled="mode === 'preview'" title="Itálico (Ctrl+I)" aria-label="Itálico" class="{{ $btn }}">
            <x-heroicon-o-italic class="h-4 w-4" />
        </button>
        <button type="button" @click="apply('strikethrough')" :disabled="mode === 'preview'" title="Tachado" aria-label="Tachado" class="{{ $btn }}">
            <x-heroicon-o-strikethrough class="h-4 w-4" />
        </button>
        <button type="button" @click="apply('link')" :disabled="mode === 'preview'" title="Link (Ctrl+K)" aria-label="Link" class="{{ $btn }}">
            <x-heroicon-o-link class="h-4 w-4" />
        </button>
        <span class="mx-1 h-5 w-px shrink-0 bg-gray-300 dark:bg-gray-600"></span>
        <button type="button" @click="apply('ul')" :disabled="mode === 'preview'" title="Lista não ordenada" aria-label="Lista não ordenada" class="{{ $btn }}">
            <x-heroicon-o-list-bullet class="h-4 w-4" />
        </button>
        <button type="button" @click="apply('ol')" :disabled="mode === 'preview'" title="Lista ordenada" aria-label="Lista ordenada" class="{{ $btn }}">
            <x-heroicon-o-numbered-list class="h-4 w-4" />
        </button>
        <button type="button" @click="apply('checklist')" :disabled="mode === 'preview'" title="Checklist" aria-label="Checklist" class="{{ $btn }}">
            <x-heroicon-o-check-circle class="h-4 w-4" />
        </button>
        <button type="button" @click="apply('quote')" :disabled="mode === 'preview'" title="Citação" aria-label="Citação" class="{{ $btn }}">
            <x-heroicon-o-chat-bubble-bottom-center-text class="h-4 w-4" />
        </button>
        <button type="button" @click="apply('code')" :disabled="mode === 'preview'" title="Código (inline ou bloco)" aria-label="Código" class="{{ $btn }}">
            <x-heroicon-o-code-bracket class="h-4 w-4" />
        </button>
        <button type="button" @click="apply('hr')" :disabled="mode === 'preview'" title="Linha horizontal" aria-label="Linha horizontal" class="{{ $btn }}">
            <x-heroicon-o-minus class="h-4 w-4" />
        </button>

        @if ($allowImage)
            <span class="mx-1 h-5 w-px shrink-0 bg-gray-300 dark:bg-gray-600"></span>
            <button type="button" @click="pickImage()" :disabled="mode === 'preview' || uploading"
                    title="Inserir imagem" aria-label="Inserir imagem" class="{{ $btn }}">
                <x-heroicon-o-photo class="h-4 w-4" />
            </button>
            <span x-show="uploading" role="status" aria-live="polite"
                  class="ml-1 shrink-0 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">Enviando…</span>
        @endif

        <button type="button" @click="toggleMode()"
                class="ml-auto inline-flex h-9 shrink-0 items-center whitespace-nowrap px-3 text-sm font-medium text-primary-700 hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-gray-700"
                x-text="mode === 'markdown' ? 'Pré-visualizar' : 'Editar'"></button>
    </div>

    @if ($allowImage)
        <input type="file" x-ref="fileInput" accept="{{ \App\Support\ImageFormats::acceptAttribute() }}"
               @change="onFilePicked($event)" class="hidden">
    @endif

    <div class="relative">
        <textarea
            x-ref="textarea"
            x-show="mode === 'markdown'"
            @input="onInput()"
            @keydown="onKeydown($event)"
            @dragover="onDragOver($event)"
            @drop="onDrop($event)"
            @paste="onPaste($event)"
            @scroll.passive="mentionOpen && positionMention()"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="mentionOpen"
            :aria-controls="mentionId"
            :aria-activedescendant="mentionOpen && mentionResults.length ? mentionId + '-option-' + mentionIndex : null"
            {{ $attributes->merge([
                'rows' => $rows,
                'placeholder' => $placeholder,
                'class' => 'w-full ' . $textareaClass . ' rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100',
            ]) }}></textarea>

        <ul x-show="mentionOpen" x-cloak
            x-ref="mentionList"
            :id="mentionId"
            role="listbox"
            :style="mentionStyle"
            @mousedown="$event.preventDefault()"
            @click.outside="closeMention()"
            class="absolute z-20 max-h-60 w-72 max-w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
            <template x-for="(user, index) in mentionResults" :key="user.username">
                <li :id="mentionId + '-option-' + index"
                    role="option"
                    :aria-selected="index === mentionIndex"
                    @mouseenter="mentionIndex = index"
                    @click="applyMention(user)"
                    :class="index === mentionIndex ? 'bg-primary-50 dark:bg-gray-700' : ''"
                    class="flex cursor-pointer items-center gap-2 px-3 py-2">
                    <img :src="user.avatar_url" alt="" loading="lazy"
                         class="h-6 w-6 rounded-full bg-gray-100 object-cover dark:bg-gray-700">
                    <span class="min-w-0 flex-1 truncate text-sm">
                        <span x-text="user.display_name || user.username" class="font-medium text-gray-900 dark:text-gray-100"></span>
                        <span x-text="'@' + user.username" class="ml-1 text-gray-500 dark:text-gray-400"></span>
                    </span>
                </li>
            </template>
        </ul>
    </div>

    @if ($allowImage)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Ao inserir uma imagem, o cursor fica dentro de <code class="font-mono">![ ]</code>:
            descreva ali o que a imagem mostra, para quem usa leitor de tela. Deixe vazio se a
            imagem for apenas decorativa.
        </p>
    @endif

    <div x-show="mode === 'preview'" x-cloak
         class="md-content {{ $previewClass }} rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
        <template x-if="preview"><div x-html="preview"></div></template>
        <p x-show="!preview" class="italic text-gray-500 dark:text-gray-400">Nada para pré-visualizar.</p>
    </div>
</div>
