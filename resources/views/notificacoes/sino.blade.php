{{-- O contador é consultado a cada 30 s sem abrir o banco: ver a nota em App\Livewire\Notificacoes\Sino. --}}
<div x-data="sinoDeAvisos({{ Js::from($sinal) }}, {{ $versao }})" data-sinal="{{ $sinal }}" class="relative">
    <button type="button" @click="aberto = ! aberto" @click.outside="aberto = false"
            class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
            aria-label="Avisos{{ $naoLidas > 0 ? ': '.$naoLidas.' não lido(s)' : '' }}"
            title="{{ $naoLidas > 0 ? $naoLidas.' aviso(s) não lido(s)' : 'Nenhum aviso novo' }}">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
        </svg>
        @if ($naoLidas > 0)
            <span data-contador-de-avisos
                  class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[11px] font-semibold tabular-nums text-white">{{ $naoLidas > 99 ? '99+' : $naoLidas }}</span>
        @endif
    </button>

    <div x-show="aberto" x-cloak x-transition
         class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2 dark:border-slate-800">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Avisos</p>
            @if ($naoLidas > 0)
                <button type="button" wire:click="marcarTodasComoLidas"
                        class="text-xs font-medium text-marca-600 hover:underline dark:text-marca-400">Marcar todos como lidos</button>
            @endif
        </div>

        @if ($recentes->isEmpty())
            <p class="px-3 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                Nenhum aviso ainda. Ausências e envios de questões dos professores do seu Eixo aparecem aqui.
            </p>
        @else
            <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                @foreach ($recentes as $aviso)
                    <li wire:key="aviso-{{ $aviso->id }}">
                        <button type="button" wire:click="abrir({{ Js::from($aviso->id) }})"
                                class="flex w-full gap-2 px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <span @class([
                                'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                'bg-marca-600' => $aviso->read_at === null,
                                'bg-transparent' => $aviso->read_at !== null,
                            ]) aria-hidden="true"></span>
                            <span class="min-w-0">
                                <span @class([
                                    'block text-sm',
                                    'font-semibold text-slate-900 dark:text-slate-100' => $aviso->read_at === null,
                                    'text-slate-700 dark:text-slate-300' => $aviso->read_at !== null,
                                ])>{{ $aviso->data['titulo'] }}</span>
                                <span class="block text-xs text-slate-600 dark:text-slate-400">{{ $aviso->data['mensagem'] }}</span>
                                <span class="block text-[11px] text-slate-400">{{ $aviso->created_at->diffForHumans() }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
