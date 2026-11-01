<div class="mx-auto max-w-3xl space-y-4">
    @if ($porDia->isEmpty())
        <x-cartao>
            <x-vazio titulo="Nenhuma aula sua no quadro de horários"
                     descricao="Quem monta o quadro é a coordenação, na tela de cada turma. Se faltar alguma aula, avise o PAEET."/>
        </x-cartao>
    @else
        @foreach ($porDia as $aulas)
            <x-cartao :titulo="$aulas->first()->dia_semana->rotulo()" wire:key="dia-{{ $aulas->first()->dia_semana->value }}">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($aulas as $aula)
                        <li class="flex flex-wrap items-baseline gap-x-4 gap-y-1 py-2" wire:key="aula-{{ $aula->id }}">
                            <span class="w-28 tabular-nums text-slate-500 dark:text-slate-400">{{ $aula->intervalo() }}</span>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $aula->disciplina->nome }}</span>
                            <span class="text-slate-600 dark:text-slate-300">Turma {{ $aula->turma->nome }}</span>
                            <span class="text-xs text-slate-400">{{ $aula->aula_numero }}ª aula</span>
                        </li>
                    @endforeach
                </ul>
            </x-cartao>
        @endforeach
    @endif
</div>
