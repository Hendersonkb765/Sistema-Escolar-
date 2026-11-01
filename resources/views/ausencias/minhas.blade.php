<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex justify-end">
        @can('create', App\Models\Ausencia::class)
            <x-botao href="{{ route('ausencias.criar') }}" wire:navigate>Informar ausência</x-botao>
        @endcan
    </div>

    @foreach (['Próximas' => $proximas, 'Passadas' => $passadas] as $titulo => $lista)
        <x-cartao :titulo="$titulo" wire:key="grupo-{{ $titulo }}">
            @if ($lista->isEmpty())
                <x-vazio :titulo="$titulo === 'Próximas' ? 'Nenhuma ausência marcada' : 'Nenhuma ausência passada'"
                         :descricao="$titulo === 'Próximas' ? 'Quando souber que vai faltar, informe o dia: as aulas vêm do quadro de horários.' : null"/>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($lista as $ausencia)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="ausencia-{{ $ausencia->id }}">
                            <div class="min-w-0">
                                <a href="{{ route('ausencias.show', $ausencia) }}" wire:navigate
                                   class="font-medium text-slate-900 hover:underline dark:text-slate-100">
                                    {{ $ausencia->data->format('d/m/Y') }}
                                </a>
                                <span class="text-sm text-slate-500 dark:text-slate-400">· {{ $ausencia->aulas_count }} aula(s)</span>
                                @if ($ausencia->motivo)
                                    <p class="truncate text-sm text-slate-600 dark:text-slate-300">{{ $ausencia->motivo }}</p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <x-badge :cor="$ausencia->status->cor()">{{ $ausencia->status->rotulo() }}</x-badge>
                                @can('cancelar', $ausencia)
                                    @if ($ausencia->podeSerCancelada())
                                        <x-botao variante="discreto" class="text-xs"
                                                 wire:click="cancelar({{ $ausencia->id }})"
                                                 wire:confirm="Cancelar a ausência de {{ $ausencia->data->format('d/m/Y') }}?">Cancelar</x-botao>
                                    @endif
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-cartao>
    @endforeach
</div>
