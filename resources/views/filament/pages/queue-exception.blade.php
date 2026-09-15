@php
    $payload = json_decode((string) $record->payload, true);
    $payload = is_array($payload) ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $record->payload;
@endphp

<div
    class="fi-queue-exception flex flex-col gap-4"
    x-data="{
        copied: null,
        copy(key, text) {
            const done = () => {
                this.copied = key;
                setTimeout(() => { if (this.copied === key) this.copied = null }, 2000);
            };

            if (window.navigator.clipboard?.writeText) {
                window.navigator.clipboard.writeText(text).then(done).catch(() => this.fallback(text, done));

                return;
            }

            this.fallback(text, done);
        },
        fallback(text, done) {
            const area = document.createElement('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
            done();
        },
    }"
>
    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Job</dt>
            <dd class="mt-0.5 break-all font-mono text-gray-950 dark:text-white">{{ $record->jobName }}</dd>
        </div>

        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Falhou em</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $record->failed_at?->isoFormat('L LTS') ?? '—' }}</dd>
        </div>

        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Fila</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $record->connection }} / {{ $record->queue }}</dd>
        </div>

        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">UUID</dt>
            <dd class="mt-0.5 break-all font-mono text-gray-950 dark:text-white">{{ $record->uuid }}</dd>
        </div>
    </dl>

    <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 dark:border-danger-500/30 dark:bg-danger-500/10">
        <p class="break-all font-mono text-sm font-semibold text-danger-700 dark:text-danger-400">
            {{ $record->exceptionClass }}
        </p>
        <p class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-950 select-text dark:text-white">
            {{ $record->exceptionMessage }}
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <x-filament::button
            size="xs"
            color="gray"
            icon="heroicon-m-clipboard-document"
            x-on:click="copy('exception', @js((string) $record->exception))"
        >
            <span x-text="copied === 'exception' ? 'Copiado!' : 'Copiar exceção'">Copiar exceção</span>
        </x-filament::button>

        <x-filament::button
            size="xs"
            color="gray"
            icon="heroicon-m-clipboard-document"
            x-on:click="copy('message', @js($record->exceptionMessage))"
        >
            <span x-text="copied === 'message' ? 'Copiado!' : 'Copiar mensagem'">Copiar mensagem</span>
        </x-filament::button>

        <x-filament::button
            size="xs"
            color="gray"
            icon="heroicon-m-clipboard-document"
            x-on:click="copy('payload', @js($payload))"
        >
            <span x-text="copied === 'payload' ? 'Copiado!' : 'Copiar payload'">Copiar payload</span>
        </x-filament::button>
    </div>

    <div>
        <p class="mb-1.5 text-sm font-medium text-gray-500 dark:text-gray-400">Stack trace</p>
        <pre class="max-h-96 overflow-auto rounded-lg bg-gray-950 p-3 font-mono text-sm leading-5 text-gray-100 select-text dark:bg-black/50">{{ $record->exceptionTrace !== '' ? $record->exceptionTrace : $record->exception }}</pre>
    </div>

    <details class="group">
        <summary class="cursor-pointer text-sm font-medium text-gray-500 select-none hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
            Payload do job
        </summary>
        <pre class="mt-1.5 max-h-80 overflow-auto rounded-lg bg-gray-950 p-3 font-mono text-sm leading-5 text-gray-100 select-text dark:bg-black/50">{{ $payload }}</pre>
    </details>
</div>
