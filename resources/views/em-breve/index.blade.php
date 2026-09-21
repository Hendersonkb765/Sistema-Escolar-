<x-layouts.app :titulo="$titulo" :subtitulo="$subtitulo">
    <x-cartao>
        <div class="py-8 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="mt-4 text-base font-semibold text-slate-900 dark:text-slate-100">{{ $titulo }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">{{ $descricao }}</p>
            <x-badge cor="azul" class="mt-4">{{ $etapa }}</x-badge>

            <div class="mt-6">
                <x-botao variante="secundario" href="{{ route('painel') }}">Voltar ao painel</x-botao>
            </div>
        </div>
    </x-cartao>
</x-layouts.app>
