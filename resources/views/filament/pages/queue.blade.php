<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
        <x-filament::section>
            <x-slot name="heading">Jobs pendentes</x-slot>
            <p class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                {{ number_format($this->pendingCount()) }}
            </p>
            <p class="text-sm text-gray-500">Aguardando processamento nas filas <code>emails</code> e <code>default</code>.</p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Jobs com falha</x-slot>
            <p class="text-3xl font-bold text-danger-600 dark:text-danger-400">
                {{ number_format($this->failedCount()) }}
            </p>
            <p class="text-sm text-gray-500">Registrados na tabela <code>failed_jobs</code>.</p>
        </x-filament::section>
    </div>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
