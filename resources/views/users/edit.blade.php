@php
    $inputClass = 'w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100';
    $labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    $helpClass = 'mt-1 text-sm text-gray-500 dark:text-gray-400';
    $primaryBtn = 'inline-flex items-center justify-center rounded-md bg-primary-600 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-700';
@endphp

<x-layouts.app :title="'Configurações - '.config('orbita.name', 'Órbita')">
    <div class="mx-auto max-w-2xl space-y-6" x-data="{ tab: 'profile' }">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Configurações</h1>
            <div class="flex gap-2">
                <a href="{{ route('users.profile', ['username' => $user->username]) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                    Ver perfil
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-700 dark:bg-green-950/40 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="relative -mx-4 border-b border-gray-200 md:mx-0 dark:border-gray-800" x-data="scrollHint">
            <nav x-ref="scroller" aria-label="Seções das configurações"
                 class="-mb-px flex snap-x snap-proximity gap-4 overflow-x-auto overscroll-x-contain scroll-px-4 px-4 text-sm font-medium [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:scroll-px-0 md:overflow-x-visible md:px-0">
                @php
                    $showConnections = $isSelfEdit && ! empty($socialProviders);
                    $tabs = ['profile' => 'Perfil', 'account' => 'Conta']
                        + ($showConnections ? ['connections' => 'Contas conectadas'] : [])
                        + ['notifications' => 'Notificações', 'preferences' => 'Preferências'];
                @endphp
                @foreach ($tabs as $key => $label)
                    <button type="button"
                            @click="tab = '{{ $key }}'; $el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' })"
                            :aria-current="tab === '{{ $key }}' ? 'true' : 'false'"
                            :class="tab === '{{ $key }}' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                            class="shrink-0 snap-start whitespace-nowrap border-b-2 px-1 py-2">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>

            <button type="button" x-show="! atStart" x-cloak tabindex="-1" aria-hidden="true"
                    @click="scrollBy(-1)"
                    class="absolute inset-y-0 left-0 flex w-12 items-center justify-start bg-gradient-to-r from-gray-50 from-50% to-transparent pl-0.5 text-gray-500 md:hidden dark:from-gray-950 dark:text-gray-400">
                <x-heroicon-o-chevron-left class="h-4 w-4" aria-hidden="true" />
            </button>
            <button type="button" x-show="! atEnd" x-cloak tabindex="-1" aria-hidden="true"
                    @click="scrollBy(1)"
                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-end bg-gradient-to-l from-gray-50 from-50% to-transparent pr-0.5 text-gray-500 md:hidden dark:from-gray-950 dark:text-gray-400">
                <x-heroicon-o-chevron-right class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>

        <div x-show="tab === 'profile'" x-cloak>
            <form action="{{ route('users.update', ['username' => $user->username]) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="username" class="{{ $labelClass }}">Nome de usuário</label>
                    <input type="text" id="username" name="username" required maxlength="50"
                           pattern="[A-Za-z0-9_]+" value="{{ old('username', $user->username) }}"
                           @if ($usernameLockedUntil) readonly @endif
                           class="mt-1 {{ $inputClass }} @if ($usernameLockedUntil) cursor-not-allowed opacity-60 @endif">
                    @if ($usernameLockedUntil)
                        <p class="{{ $helpClass }}">Você poderá alterar novamente em {{ $usernameLockedUntil->isoFormat('LL') }}.</p>
                    @else
                        <p class="{{ $helpClass }}">Letras, números e underscore. Pode ser alterado uma vez a cada 30 dias.</p>
                    @endif
                    @error('username') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="display_name" class="{{ $labelClass }}">Nome público</label>
                    <input type="text" id="display_name" name="display_name" required maxlength="100"
                           value="{{ old('display_name', $user->display_name) }}" class="mt-1 {{ $inputClass }}">
                    <p class="{{ $helpClass }}">Nome exibido no seu perfil.</p>
                    @error('display_name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="bio" class="{{ $labelClass }}">Biografia</label>
                    <textarea id="bio" name="bio" rows="3" maxlength="500" class="mt-1 {{ $inputClass }}">{{ old('bio', $user->bio) }}</textarea>
                    <p class="{{ $helpClass }}">Máximo de 500 caracteres.</p>
                    @error('bio') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="website" class="{{ $labelClass }}">Site</label>
                    <input type="url" id="website" name="website" maxlength="255" placeholder="https://..."
                           value="{{ old('website', $user->website) }}" class="mt-1 {{ $inputClass }}">
                    <p class="{{ $helpClass }}">Um link para você mesmo: site, rede social, portfólio. Aparece ao lado do seu nome.</p>
                    @error('website') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Avatar</label>
                    <div class="mt-1 flex items-center gap-3">
                        <x-avatar :user="$user" class="h-14 w-14" alt="Avatar atual" />
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif"
                               class="block text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-primary-700 dark:text-gray-300">
                    </div>
                    <p class="{{ $helpClass }}">JPEG, PNG, GIF ou WebP. Máximo 5MB.</p>
                    @error('avatar') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="{{ $primaryBtn }}">Salvar alterações</button>
            </form>
        </div>

        <div x-show="tab === 'account'" x-cloak class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Email</h2>
                <p class="{{ $helpClass }}">Email atual: <strong>{{ $user->email }}</strong></p>
                <form action="{{ route('users.email', ['username' => $user->username]) }}" method="POST" class="mt-3 space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="new_email" class="{{ $labelClass }}">Novo email</label>
                        <input type="email" id="new_email" name="new_email" required value="{{ old('new_email') }}" class="mt-1 {{ $inputClass }}">
                        <p class="{{ $helpClass }}">Você receberá um link de confirmação no novo endereço.</p>
                        @error('new_email', 'updateEmail') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        @error('new_email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    @if ($user->hasPassword())
                        <div>
                            <label for="email_password" class="{{ $labelClass }}">Senha atual</label>
                            <input type="password" id="email_password" name="password" required class="mt-1 {{ $inputClass }}">
                            @error('password', 'updateEmail') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <button type="submit" class="{{ $primaryBtn }}">Alterar email</button>
                </form>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Senha</h2>
                @unless ($user->hasPassword())
                    <p class="{{ $helpClass }}">
                        Sua conta ainda não tem senha, você entra pelo login social. Defina uma para
                        também poder entrar com email e senha.
                    </p>
                @endunless
                <form action="{{ route('users.password', ['username' => $user->username]) }}" method="POST" class="mt-3 space-y-4">
                    @csrf
                    @method('PUT')
                    @if ($user->hasPassword())
                        <div>
                            <label for="current_password" class="{{ $labelClass }}">Senha atual</label>
                            <input type="password" id="current_password" name="current_password" required class="mt-1 {{ $inputClass }}">
                            @error('current_password', 'updatePassword') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div>
                        <label for="password" class="{{ $labelClass }}">{{ $user->hasPassword() ? 'Nova senha' : 'Senha' }}</label>
                        <input type="password" id="password" name="password" required class="mt-1 {{ $inputClass }}">
                        @error('password', 'updatePassword') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="{{ $labelClass }}">{{ $user->hasPassword() ? 'Confirmar nova senha' : 'Confirmar senha' }}</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required class="mt-1 {{ $inputClass }}">
                    </div>
                    <button type="submit" class="{{ $primaryBtn }}">{{ $user->hasPassword() ? 'Alterar senha' : 'Definir senha' }}</button>
                </form>
            </div>

            @if ((int) auth()->id() === (int) $user->id)
                <div x-data="{ confirming: false }"
                     class="rounded-lg border border-red-300 bg-white p-4 dark:border-red-900 dark:bg-gray-900">
                    <h2 class="text-base font-semibold text-red-700 dark:text-red-400">Excluir conta</h2>
                    <p class="{{ $helpClass }}">
                        Seus posts e comentários continuam publicados, mas passam a aparecer como
                        "Conta excluída", sem link para o seu perfil. Nome, email, avatar, bio,
                        favoritos e notificações são apagados, e seu email e nome de usuário ficam livres
                        para um novo cadastro. Enviaremos um link de confirmação para <strong>{{ $user->email }}</strong>.
                        A conta só é excluída depois que você confirmar por lá. Esta ação é irreversível.
                    </p>

                    <button type="button" x-show="! confirming" x-on:click="confirming = true"
                            class="mt-3 inline-flex items-center justify-center rounded-md border border-red-300 px-4 py-3 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950">
                        Excluir minha conta
                    </button>

                    <form x-show="confirming" x-cloak
                          action="{{ route('users.delete-account', ['username' => $user->username]) }}"
                          method="POST" class="mt-3 space-y-4">
                        @csrf
                        @if ($user->hasPassword())
                            <div>
                                <label for="delete_password" class="{{ $labelClass }}">Senha atual</label>
                                <input type="password" id="delete_password" name="password" required class="mt-1 {{ $inputClass }}">
                                @error('password', 'deleteAccount') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            <button type="submit"
                                    class="inline-flex items-center justify-center rounded-md bg-red-600 px-4 py-3 text-sm font-semibold text-white hover:bg-red-700">
                                Enviar link de confirmação
                            </button>
                            <button type="button" x-on:click="confirming = false"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        @if ($showConnections)
            <div x-show="tab === 'connections'" x-cloak class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Contas conectadas</h2>
                    <p class="{{ $helpClass }}">
                        Conecte um provedor para entrar sem digitar sua senha. Conectar não altera o
                        email da sua conta no {{ config('orbita.name', 'Órbita') }}.
                    </p>

                    @unless ($user->hasPassword())
                        <p class="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            Sua conta não tem senha, você entra apenas pelo login social. Defina uma senha
                            na aba <button type="button" x-on:click="tab = 'account'" class="font-semibold underline">Conta</button>
                            para não depender de um único provedor.
                        </p>
                    @endunless

                    <ul class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($socialProviders as $provider)
                            @php
                                $account = $socialAccounts[$provider->value] ?? null;
                                $isLastAccessMethod = ! $user->hasPassword() && $socialAccounts->count() <= 1;
                            @endphp
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <x-social-icon :provider="$provider" />
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $provider->label() }}</p>
                                        @if ($account)
                                            <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                                                {{ $account->provider_nickname ?: $account->provider_email ?: $account->provider_name }}
                                                @if ($account->created_at)
                                                    · conectado em {{ $account->created_at->isoFormat('LL') }}
                                                @endif
                                            </p>
                                        @else
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Não conectado</p>
                                        @endif
                                    </div>
                                </div>

                                @if ($account)
                                    @if ($isLastAccessMethod)
                                        <div class="text-right">
                                            <button type="button" disabled
                                                    class="inline-flex cursor-not-allowed items-center justify-center rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-500 dark:border-gray-800 dark:text-gray-600">
                                                Desconectar
                                            </button>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Defina uma senha antes.</p>
                                        </div>
                                    @else
                                        <form action="{{ route('users.connections.destroy', ['username' => $user->username, 'provider' => $provider->value]) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950">
                                                Desconectar
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <form action="{{ route('users.connections.store', ['username' => $user->username, 'provider' => $provider->value]) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                                            Conectar
                                        </button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div x-show="tab === 'notifications'" x-cloak>
            <form action="{{ route('users.update', ['username' => $user->username]) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <fieldset class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
                    <legend class="px-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Notificações</legend>
                    <div class="space-y-3">
                        @php
                            $prefs = [
                                'notify_replies_system' => 'Respostas e comentários: no site',
                                'notify_replies_email' => 'Respostas e comentários: por email',
                                'notify_mentions_system' => 'Menções: no site',
                                'notify_mentions_email' => 'Menções: por email',
                                'notify_follows_system' => 'Posts que acompanho: no site',
                                'notify_follows_email' => 'Posts que acompanho: por email',
                            ];
                        @endphp
                        @foreach ($prefs as $field => $label)
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $user->{$field}))
                                       class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <button type="submit" class="{{ $primaryBtn }}">Salvar preferências</button>
            </form>

            <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <a href="{{ route('notifications.index') }}" wire:navigate class="text-primary-600 hover:underline dark:text-primary-400">Ver notificações recebidas</a>.
            </div>
        </div>

        <div x-show="tab === 'preferences'" x-cloak>
            <form action="{{ route('users.update', ['username' => $user->username]) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="default_comment_sort" class="{{ $labelClass }}">Ordem padrão dos comentários</label>
                    <select id="default_comment_sort" name="default_comment_sort" class="mt-1 {{ $inputClass }}">
                        <option value="" @selected(old('default_comment_sort', $user->default_comment_sort) === null)>Padrão do site (mais novos)</option>
                        <option value="newest" @selected(old('default_comment_sort', $user->default_comment_sort) === 'newest')>Mais novos</option>
                        <option value="oldest" @selected(old('default_comment_sort', $user->default_comment_sort) === 'oldest')>Mais antigos</option>
                        <option value="most_reactions" @selected(old('default_comment_sort', $user->default_comment_sort) === 'most_reactions')>Populares</option>
                    </select>
                    <p class="{{ $helpClass }}">Ordem usada ao abrir os comentários de um post. Pode ser trocada a qualquer momento dentro do post.</p>
                    @error('default_comment_sort') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="{{ $primaryBtn }}">Salvar preferências</button>
            </form>
        </div>
    </div>
</x-layouts.app>
