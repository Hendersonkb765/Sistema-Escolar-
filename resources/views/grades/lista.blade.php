<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                <x-campo rotulo="Buscar curso" para="busca">
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

                <x-campo rotulo="Situação" para="filtro-status">
                    <x-select id="filtro-status" wire:model.live="filtroStatus">
                        <option value="">Todas</option>
                        @foreach ($situacoes as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                </x-campo>
            </div>

            <x-botao variante="secundario" href="{{ route('cursos.index') }}" wire:navigate class="shrink-0">
                Publicar pelo curso
            </x-botao>
        </div>
    </x-cartao>

    <x-cartao descricao="Cada versão é uma foto das disciplinas do curso. Para publicar uma nova, abra o curso.">
        @if ($grades->isEmpty())
            <x-vazio titulo="Nenhuma grade publicada"
                     descricao="Abra um curso, cadastre as disciplinas de cada período e publique a grade."/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Curso</th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('versao')" class="font-semibold">Versão {{ $this->setaDaColuna('versao') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">
                                <button type="button" wire:click="ordenar('ano_vigencia')" class="font-semibold">Vigência {{ $this->setaDaColuna('ano_vigencia') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 md:table-cell">Disciplinas</th>
                            <th class="hidden px-4 py-2 md:table-cell">Turmas</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($grades as $grade)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-900 sm:px-6 dark:text-slate-100">{{ $grade->curso->nome }}</td>
                                <td class="px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">v{{ $grade->versao }}</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 sm:table-cell dark:text-slate-300">{{ $grade->ano_vigencia }}</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">{{ $grade->disciplinas_count }}</td>
                                <td class="hidden px-4 py-3 md:table-cell">
                                    @if ($grade->turmas_count > 0)
                                        <span class="tabular-nums text-slate-600 dark:text-slate-300">{{ $grade->turmas_count }}</span>
                                        <span class="ml-1 text-xs text-slate-400">congelada</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :cor="$grade->status->cor()" :rotulo="$grade->status->rotulo()"/>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-6">
                                    <x-botao variante="discreto" href="{{ route('grades.show', $grade) }}" wire:navigate>Ver</x-botao>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $grades->links() }}</div>
        @endif
    </x-cartao>
</div>
