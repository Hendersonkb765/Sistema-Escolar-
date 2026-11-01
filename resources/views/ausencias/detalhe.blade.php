<div class="mx-auto max-w-3xl space-y-4">
    <x-cartao :titulo="$ausencia->rotuloDoDia()" :descricao="$ausencia->motivo">
        <x-slot:acoes>
            <x-badge :cor="$ausencia->status->cor()">{{ $ausencia->status->rotulo() }}</x-badge>
        </x-slot:acoes>

        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($ausencia->aulas as $aula)
                <li class="space-y-1 py-3" wire:key="aula-{{ $aula->id }}">
                    <p class="flex flex-wrap items-baseline gap-x-3">
                        <span class="w-28 tabular-nums text-slate-500 dark:text-slate-400">{{ $aula->intervalo() }}</span>
                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ $aula->disciplina->nome }}</span>
                        <span class="text-slate-600 dark:text-slate-300">Turma {{ $aula->turma->nome }}</span>
                        <span class="text-xs text-slate-400">{{ $aula->aula_numero }}ª aula</span>
                    </p>

                    <p class="text-sm text-slate-700 dark:text-slate-200">
                        <span class="text-slate-500 dark:text-slate-400">Escopo:</span>
                        {{ $aula->escopo_titulo ?? 'nenhuma aula do escopo escolhida' }}
                    </p>

                    @if ($aula->conteudo_planejado)
                        <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200"><span class="text-slate-500 dark:text-slate-400">Conteúdo planejado:</span> {{ $aula->conteudo_planejado }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-cartao>

    <div class="flex justify-end">
        <x-botao variante="secundario" href="{{ route('ausencias.index') }}" wire:navigate>Minhas ausências</x-botao>
    </div>
</div>
