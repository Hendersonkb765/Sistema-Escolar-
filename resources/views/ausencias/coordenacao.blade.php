<div class="space-y-4">
    <x-cartao>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-campo rotulo="Situação" para="situacao">
                <x-select id="situacao" wire:model.live="situacao">
                    @foreach ($situacoes as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($situacao === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Quando" para="quando">
                <x-select id="quando" wire:model.live="quando">
                    @foreach ($periodos as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($quando === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </x-select>
            </x-campo>
        </div>
    </x-cartao>

    @if ($ausencias->isEmpty())
        <x-cartao>
            <x-vazio titulo="Nenhuma ausência com esse filtro"
                     descricao="Aparecem aqui as ausências com aula em turmas do seu Eixo, informadas pelos próprios professores."/>
        </x-cartao>
    @else
        <x-cartao>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($ausencias as $ausencia)
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3" wire:key="ausencia-{{ $ausencia->id }}">
                        <div class="min-w-0 space-y-1">
                            <p>
                                <a href="{{ route('ausencias.show', $ausencia) }}" wire:navigate
                                   class="font-medium text-slate-900 hover:underline dark:text-slate-100">{{ $ausencia->rotuloDoDia() }}</a>
                                <span class="text-slate-600 dark:text-slate-300">· {{ $ausencia->professor->nome }}</span>
                            </p>
                            <p class="text-sm text-slate-600 dark:text-slate-300">
                                @foreach ($ausencia->aulas as $aula)
                                    <span class="whitespace-nowrap">{{ $aula->intervalo() }} {{ $aula->disciplina->nome }} ({{ $aula->turma->nome }})</span>{{ $loop->last ? '' : ' ·' }}
                                @endforeach
                            </p>
                            @if ($ausencia->motivo)
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $ausencia->motivo }}</p>
                            @endif
                            @if ($ausencia->status === App\Enums\StatusAusencia::Ciente)
                                <p class="text-xs text-slate-400">Ciência de {{ $ausencia->cientePor?->nome ?? 'conta removida' }} em {{ $ausencia->ciente_em->format('d/m/Y') }}</p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <x-badge :cor="$ausencia->status->cor()">{{ $ausencia->status->rotulo() }}</x-badge>
                            @can('tomarCiencia', $ausencia)
                                @if ($ausencia->status === App\Enums\StatusAusencia::Pendente)
                                    <x-botao variante="secundario" class="text-xs"
                                             wire:click="tomarCiencia({{ $ausencia->id }})">Tomar ciência</x-botao>
                                @endif
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-cartao>
    @endif
</div>
