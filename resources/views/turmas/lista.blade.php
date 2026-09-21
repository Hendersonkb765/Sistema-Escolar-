<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-4">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Ex.: 2DS"/>
                </x-campo>

                <x-campo rotulo="Curso" para="filtro-curso">
                    <x-select id="filtro-curso" wire:model.live="filtroCurso">
                        <option value="">Todos</option>
                        @foreach ($cursos as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </x-select>
                </x-campo>

                <x-campo rotulo="Período" para="filtro-periodo">
                    <x-select id="filtro-periodo" wire:model.live="filtroPeriodo">
                        <option value="">Todos</option>
                        <option value="1">1º período</option>
                        <option value="2">2º período</option>
                        <option value="3">3º período</option>
                    </x-select>
                </x-campo>

                <x-campo rotulo="Situação" para="filtro-status">
                    <x-select id="filtro-status" wire:model.live="filtroStatus">
                        <option value="">Todas</option>
                        @foreach ($situacoes as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                </x-campo>
            </div>

            @can('create', App\Models\Turma::class)
                <x-botao href="{{ route('turmas.criar') }}" wire:navigate class="shrink-0">Nova turma</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao>
        @if ($turmas->isEmpty())
            <x-vazio titulo="Nenhuma turma encontrada"
                     descricao="Uma turma precisa de um curso com grade curricular publicada."/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold">Turma {{ $this->setaDaColuna('nome') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">Curso</th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('periodo')" class="font-semibold">Ano {{ $this->setaDaColuna('periodo') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 md:table-cell">Grade</th>
                            <th class="hidden px-4 py-2 md:table-cell">Período</th>
                            <th class="hidden px-4 py-2 lg:table-cell">Alunos</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($turmas as $turma)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-900 sm:px-6 dark:text-slate-100">{{ $turma->nome }}</td>
                                <td class="hidden px-4 py-3 text-slate-600 sm:table-cell dark:text-slate-300">{{ $turma->curso->nome }}</td>
                                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $turma->periodo }}º</td>
                                <td class="hidden px-4 py-3 md:table-cell">
                                    <x-badge cor="cinza">v{{ $turma->grade->versao }}</x-badge>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 md:table-cell dark:text-slate-300">{{ $turma->periodo_letivo }}</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 lg:table-cell dark:text-slate-300">{{ $turma->alunos_count }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$turma->status->cor()" :rotulo="$turma->status->rotulo()"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    <x-botao variante="discreto" href="{{ route('turmas.show', $turma) }}" wire:navigate>Ver</x-botao>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $turmas->links() }}</div>
        @endif
    </x-cartao>
</div>
