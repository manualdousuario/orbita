<div class="pointer-events-none fixed inset-x-0 top-20 z-50 flex justify-center"
     aria-live="assertive" aria-atomic="true">
    <div id="app-toast-box"
         class="pointer-events-auto hidden max-w-[90%] items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-lg md:max-w-[640px] dark:border-red-900 dark:bg-red-950 dark:text-red-200">
        <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
        <p id="app-toast-message" class="flex-1"></p>
        <button type="button" id="app-toast-close"
                class="-mr-1 -mt-1 shrink-0 rounded p-1 text-red-600 transition hover:bg-red-100 dark:text-red-300 dark:hover:bg-red-900">
            <x-heroicon-o-x-mark class="h-4 w-4" aria-hidden="true" />
            <span class="sr-only">Fechar</span>
        </button>
    </div>
</div>
