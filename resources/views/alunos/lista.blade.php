<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Nome ou RA"/>
                </x-campo>

                <x-campo rotulo="Turma" para="filtro-turma">
                    <x-select id="filtro-turma" wire:model.live="filtroTurma">
                        <option value="">Todas</option>
                        @foreach ($turmas as $turma)
                            <option value="{{ $turma->id }}">{{ $turma->nome }} · {{ $turma->periodo_letivo }}</option>
                        @endforeach
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

            @can('create', App\Models\Aluno::class)
                <x-botao href="{{ route('alunos.criar') }}" wire:navigate class="shrink-0">Novo aluno</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao>
        @if ($alunos->isEmpty())
            <x-vazio titulo="Nenhum aluno encontrado"
                     descricao="Matricule alunos em uma turma para começar."/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold">Aluno {{ $this->setaDaColuna('nome') }}</button>
                            </th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('ra')" class="font-semibold">RA {{ $this->setaDaColuna('ra') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">Turma</th>
                            <th class="hidden px-4 py-2 md:table-cell">Curso</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($alunos as $aluno)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-900 sm:px-6 dark:text-slate-100">{{ $aluno->nome }}</td>
                                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $aluno->ra }}</td>
                                <td class="hidden px-4 py-3 sm:table-cell">
                                    <a href="{{ route('turmas.show', $aluno->turma) }}" wire:navigate
                                       class="text-marca-600 hover:underline dark:text-marca-400">{{ $aluno->turma->nome }}</a>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 md:table-cell dark:text-slate-300">{{ $aluno->turma->curso->nome }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$aluno->status->cor()" :rotulo="$aluno->status->rotulo()"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    @can('update', $aluno)
                                        <x-botao variante="discreto" href="{{ route('alunos.editar', $aluno) }}" wire:navigate>Editar</x-botao>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $alunos->links() }}</div>
        @endif
    </x-cartao>
</div>
