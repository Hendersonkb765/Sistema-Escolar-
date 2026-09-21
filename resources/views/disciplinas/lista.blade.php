<div class="space-y-4">
    <x-cartao>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                <x-campo rotulo="Buscar" para="busca">
                    <x-input id="busca" wire:model.live.debounce.400ms="busca" placeholder="Nome ou código"/>
                </x-campo>

                <x-campo rotulo="Eixo" para="filtro-eixo">
                    <x-select id="filtro-eixo" wire:model.live="filtroEixo">
                        <option value="">Todos</option>
                        @foreach ($eixos as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </x-select>
                </x-campo>
            </div>

            @can('create', App\Models\Disciplina::class)
                <x-botao href="{{ route('disciplinas.criar') }}" wire:navigate class="shrink-0">Nova disciplina</x-botao>
            @endcan
        </div>
    </x-cartao>

    <x-cartao descricao="Uma disciplina não tem ano fixo: a mesma pode aparecer em cursos e anos diferentes, pela grade.">
        @if ($disciplinas->isEmpty())
            <x-vazio titulo="Nenhuma disciplina encontrada"
                     descricao="Cadastre disciplinas para montar as grades curriculares."/>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">
                                <button type="button" wire:click="ordenar('nome')" class="font-semibold">Disciplina {{ $this->setaDaColuna('nome') }}</button>
                            </th>
                            <th class="px-4 py-2">
                                <button type="button" wire:click="ordenar('codigo')" class="font-semibold">Código {{ $this->setaDaColuna('codigo') }}</button>
                            </th>
                            <th class="hidden px-4 py-2 sm:table-cell">Eixo</th>
                            <th class="hidden px-4 py-2 md:table-cell">Em grades</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($disciplinas as $disciplina)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-900 sm:px-6 dark:text-slate-100">{{ $disciplina->nome }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $disciplina->codigo }}</td>
                                <td class="hidden px-4 py-3 text-slate-600 sm:table-cell dark:text-slate-300">{{ $disciplina->eixo->nome }}</td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-600 md:table-cell dark:text-slate-300">{{ $disciplina->grade_disciplinas_count }}</td>
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
