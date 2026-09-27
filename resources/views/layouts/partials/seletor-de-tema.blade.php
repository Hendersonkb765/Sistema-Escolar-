{{-- Claro, escuro ou o do aparelho. A escolha fica no navegador. --}}
@php
    $opcoes = [
        'claro' => ['Claro', 'M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z'],
        'escuro' => ['Escuro', 'M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z'],
        'sistema' => ['Como o aparelho', 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25'],
    ];
@endphp

<div x-data="seletorDeTema" class="relative" @keydown.escape.window="aberto = false">
    <button type="button" @click="aberto = ! aberto" @click.outside="aberto = false"
            class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
            :aria-expanded="aberto.toString()"
            aria-haspopup="menu"
            :title="'Tema: ' + ({ claro: 'claro', escuro: 'escuro', sistema: 'como o aparelho' })[escolha]"
            aria-label="Escolher o tema">
        @foreach ($opcoes as $valor => [$rotulo, $caminho])
            <svg x-show="escolha === '{{ $valor }}'" x-cloak class="h-5 w-5"
                 fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $caminho }}"/>
            </svg>
        @endforeach
    </button>

    <div x-show="aberto" x-cloak x-transition role="menu"
         class="absolute right-0 z-40 mt-2 w-52 rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-800 dark:bg-slate-900">
        @foreach ($opcoes as $valor => [$rotulo, $caminho])
            <button type="button" role="menuitemradio" @click="escolher('{{ $valor }}')"
                    :aria-checked="(escolha === '{{ $valor }}').toString()"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $caminho }}"/>
                </svg>
                <span class="flex-1">{{ $rotulo }}</span>
                {{-- A marca não é só a cor de fundo: o estado precisa
                     de um símbolo para quem não distingue tons. --}}
                <span x-show="escolha === '{{ $valor }}'" x-cloak class="text-marca-600 dark:text-marca-400"
                      aria-hidden="true">✓</span>
            </button>
        @endforeach
    </div>
</div>
