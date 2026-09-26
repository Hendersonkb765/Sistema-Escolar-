<div class="space-y-4">
    <x-cartao titulo="Recorte">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <x-campo rotulo="Turma" para="filtro-turma">
                <x-select id="filtro-turma" wire:model.live="filtroTurma">
                    <option value="">Todas</option>
                    @foreach ($turmas as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Bimestre" para="filtro-bimestre">
                <x-select id="filtro-bimestre" wire:model.live="filtroBimestre">
                    <option value="">Todos</option>
                    @foreach ($bimestres as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Prova" para="filtro-prova">
                <x-select id="filtro-prova" wire:model.live="filtroProva">
                    <option value="">Todas do recorte</option>
                    @foreach ($provasDisponiveis as $opcao)
                        <option value="{{ $opcao->id }}">
                            {{ $opcao->titulo }} · {{ $opcao->turma->nome }} · {{ $opcao->bimestre->sigla() }}
                        </option>
                    @endforeach
                </x-select>
            </x-campo>

            <x-campo rotulo="Disciplina" para="filtro-disciplina">
                <x-select id="filtro-disciplina" wire:model.live="filtroDisciplina">
                    <option value="">Todas</option>
                    @foreach ($disciplinas as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </x-select>
            </x-campo>
        </div>

        @if ($provas->isNotEmpty())
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                {{ $provas->count() }} prova(s) no recorte:
                {{ $provas->map(fn ($p) => $p->titulo.' ('.$p->bimestre->sigla().')')->join(', ') }}
            </p>
        @endif
    </x-cartao>

    @if ($porHabilidade->isEmpty())
        <x-cartao>
            <x-vazio titulo="Nada para analisar neste recorte"
                     descricao="A análise usa as provas que já tiveram os resultados importados. Ajuste os filtros ou importe uma planilha."/>
        </x-cartao>
    @else
        <x-cartao titulo="Por habilidade avaliada"
                  :descricao="'Ordenado da que mais precisa de atenção. Abaixo de '.$limite.'% de acerto vai destacado.'">
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Habilidade</th>
                            <th class="hidden px-4 py-2 md:table-cell">Disciplina</th>
                            <th class="hidden px-4 py-2 lg:table-cell">Questões</th>
                            <th class="px-4 py-2 text-center">Acertos</th>
                            <th class="px-4 py-2">Índice de acerto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($porHabilidade as $item)
                            <tr wire:key="hab-{{ $loop->index }}"
                                @class(['bg-rose-50/60 dark:bg-rose-950/20' => $item['atencao']])>
                                <td class="px-4 py-3 sm:px-6">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $item['habilidade'] }}</span>
                                    @if ($item['atencao'])
                                        <x-badge cor="vermelho" rotulo="Precisa de atenção" class="ml-1"/>
                                    @endif
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 md:table-cell dark:text-slate-300">
                                    {{ implode(', ', $item['disciplinas']) }}
                                </td>
                                <td class="hidden px-4 py-3 tabular-nums text-slate-500 lg:table-cell dark:text-slate-400">
                                    {{ implode(', ', $item['questoes']) }}
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ $item['acertos'] }}/{{ $item['respostas'] }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-barra-de-acerto :percentual="$item['percentual']" :atencao="$item['atencao']"/>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-cartao>

        <x-cartao titulo="Questão a questão"
                  descricao="A mesma leitura pelo número, com a habilidade ao lado.">
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Questão</th>
                            <th class="px-4 py-2">Habilidade</th>
                            <th class="hidden px-4 py-2 md:table-cell">Disciplina</th>
                            <th class="px-4 py-2 text-center">Acertos</th>
                            <th class="px-4 py-2">Índice de acerto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($porQuestao as $item)
                            <tr wire:key="quest-{{ $loop->index }}"
                                @class(['bg-rose-50/60 dark:bg-rose-950/20' => $item['atencao']])>
                                <td class="px-4 py-3 sm:px-6">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $item['numero'] }}</span>
                                    <span class="block max-w-xs truncate text-xs text-slate-400">{{ $item['enunciado'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $item['habilidade'] }}</td>
                                <td class="hidden px-4 py-3 text-slate-600 md:table-cell dark:text-slate-300">
                                    {{ $item['disciplina'] }}
                                    <span class="block text-xs text-slate-400">
                                        peso {{ number_format($item['peso'], 2, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ $item['acertos'] }}/{{ $item['respostas'] }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-barra-de-acerto :percentual="$item['percentual']" :atencao="$item['atencao']"/>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-cartao>
    @endif
</div>
