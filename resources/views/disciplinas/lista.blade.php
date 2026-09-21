<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Nome ou código"/>
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
            </div>

            @can('create', App\Models\Disciplina::class)
                <x-botao href="{{ route('disciplinas.criar') }}" wire:navigate class="shrink-0">Nova disciplina</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao descricao="Para ver as disciplinas organizadas por período, abra o curso.">
        @if ($disciplinas->isEmpty())
            <x-vazio titulo="Nenhuma disciplina encontrada"
                     descricao="Cadastre as disciplinas de cada período do curso.">
                <x-slot:acoes>
                    @can('create', App\Models\Disciplina::class)
                        <x-botao href="{{ route('disciplinas.criar') }}" wire:navigate>Nova disciplina</x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold">Disciplina {{ $this->setaDaColuna('nome') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">Curso</th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('periodo')" class="font-semibold">Período {{ $this->setaDaColuna('periodo') }}</button>
                            </th>
                            <th class="px-4 py-2">Código</th>
                            <th class="hidden px-4 py-2 md:table-cell">
                                <button type="button" wire:click="ordenar('carga_horaria')" class="font-semibold">Carga {{ $this->setaDaColuna('carga_horaria') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 lg:table-cell">Em grades</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($disciplinas as $disciplina)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-900 sm:px-6 dark:text-slate-100">{{ $disciplina->nome }}</td>
                                <td class="hidden px-4 py-3 sm:table-cell">
                                    <a href="{{ route('cursos.show', $disciplina->curso) }}" wire:navigate
                                       class="text-marca-600 hover:underline dark:text-marca-400">{{ $disciplina->curso->nome }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge cor="azul">{{ $disciplina->periodo }}º</x-badge>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $disciplina->codigo }}</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">{{ $disciplina->carga_horaria }}h</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 lg:table-cell dark:text-slate-300">{{ $disciplina->grade_disciplinas_count }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$disciplina->status->cor()" :rotulo="$disciplina->status->rotulo()"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    @can('update', $disciplina)
                                        <x-botao variante="discreto" href="{{ route('disciplinas.editar', $disciplina) }}" wire:navigate>Editar</x-botao>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $disciplinas->links() }}</div>
        @endif
    </x-cartao>
</div>
