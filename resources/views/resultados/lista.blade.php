<div class="space-y-4">
    <x-cartao titulo="Recorte">
        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3 sm:p-6">
            <x-campo rotulo="Turma" para="filtro-turma">
                <x-select id="filtro-turma" wire:model.live="filtroTurma">
                    <option value="">Todas</option>
                    @foreach ($turmas as $opcao)
                        <option value="{{ $opcao->id }}">
                            {{ $opcao->nome }} — {{ $opcao->curso->nome }} ({{ $opcao->periodo_letivo }})
                        </option>
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

            <x-campo rotulo="Prova" para="prova">
                <x-select id="prova" wire:model.live="prova_id">
                    <option value="">Selecione…</option>
                    @foreach ($provas as $opcao)
                        <option value="{{ $opcao->id }}">
                            {{ $opcao->titulo }} · {{ $opcao->turma->nome }} · {{ $opcao->turma->curso->nome }}
                        </option>
                    @endforeach
                </x-select>
            </x-campo>
        </div>
    </x-cartao>

    @if ($prova === null)
        <x-cartao>
            {{--
                Chegar aqui agora só acontece quando não há prova nenhuma
                com resultado no recorte — o componente escolhe sozinho
                quando há. Por isso o texto fala do que falta, e não manda
                escolher algo que não está na lista.
            --}}
            <x-vazio titulo="Nenhuma prova com resultado{{ $bimestreEscolhido ? ' no '.$bimestreEscolhido : '' }}"
                     descricao="As notas aparecem depois que a importação dos resultados é confirmada.">
                <x-slot:acoes>
                    @can('create', App\Models\Importacao::class)
                        <x-botao href="{{ route('importacoes.criar') }}" wire:navigate>Importar resultados</x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        </x-cartao>
    @elseif ($resultados->isEmpty())
        <x-cartao>
            <x-vazio titulo="Esta prova ainda não tem resultados"
                     descricao="Importe a planilha do leitor de folhas para trazer os acertos.">
                <x-slot:acoes>
                    @can('create', App\Models\Importacao::class)
                        <x-botao href="{{ route('importacoes.criar', ['prova' => $prova->id]) }}" wire:navigate>
                            Importar resultados
                        </x-botao>
                    @endcan
                </x-slot:acoes>
            </x-vazio>
        </x-cartao>
    @else
        {{--
            De que prova são estas notas.

            A tabela sozinha não diz, e conferir o select lá em cima é
            trabalho de quem só queria ler a nota. O bimestre vem da
            coluna da prova, e não do título: o título é escrito à mão na
            montagem e pode dizer qualquer coisa.
        --}}
        <section data-identificacao
                 class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                        {{ $prova->titulo }}
                    </h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Turma {{ $prova->turma->nome }} · {{ $prova->turma->curso->nome }}
                        @if ($prova->data_aplicacao)
                            · Aplicada em {{ $prova->data_aplicacao->format('d/m/Y') }}
                        @endif
                    </p>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <x-badge cor="azul" :rotulo="$prova->bimestre->rotulo()"/>
                    <x-badge :cor="$prova->status->cor()" :rotulo="$prova->status->rotulo()"/>
                    <span class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $resultados->count() }} {{ $resultados->count() === 1 ? 'aluno' : 'alunos' }}
                    </span>
                </div>
            </div>
        </section>

        {{--
            Quais disciplinas a tabela mostra.

            Caixas, e não um select de uma opção: a tarefa é olhar três de
            seis ao mesmo tempo para transcrever, e um select só deixa ver
            uma por vez ou todas.
        --}}
        @if ($disciplinasDaProva->count() > 1)
            <x-cartao>
                <x-slot:titulo>Disciplinas mostradas</x-slot:titulo>
                <x-slot:descricao>
                    {{ $disciplinas->count() }} de {{ $disciplinasDaProva->count() }} marcadas.
                    Desmarcar uma só a tira desta tela — a nota continua lá.
                </x-slot:descricao>

                <x-slot:acoes>
                    @if ($minhasDisciplinas !== [])
                        <x-botao variante="discreto" wire:click="mostrarSoAsMinhas"
                                 title="Deixa marcadas apenas as disciplinas em que você leciona.">
                            Só as minhas
                        </x-botao>
                    @endif
                    <x-botao variante="discreto" wire:click="mostrarTodasAsDisciplinas">Todas</x-botao>
                </x-slot:acoes>

                <div class="grid gap-2 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                    @foreach ($disciplinasDaProva as $id => $nome)
                        <label wire:key="disc-{{ $id }}"
                               class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                            <input type="checkbox" value="{{ $id }}" wire:model.live="disciplinasEscolhidas"
                                   class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                            <span class="min-w-0">
                                <span class="block truncate text-slate-900 dark:text-slate-100">{{ $nome }}</span>
                                @if (in_array((string) $id, $minhasDisciplinas, true))
                                    <span class="block text-xs text-marca-600 dark:text-marca-400">você leciona</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-cartao>
        @endif

        @if ($disciplinas->isEmpty())
            <x-cartao>
                <x-vazio titulo="Nenhuma disciplina marcada"
                         descricao="Marque ao menos uma disciplina acima para ver as notas.">
                    <x-slot:acoes>
                        <x-botao wire:click="mostrarTodasAsDisciplinas">Mostrar todas</x-botao>
                    </x-slot:acoes>
                </x-vazio>
            </x-cartao>
        @else
        <x-cartao titulo="Notas por disciplina"
                  descricao="O peso vale dentro da disciplina: cada uma é medida contra a soma dos pesos dela.">
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                {{--
                    `data-notas` marca a tabela para os testes. Procurar o
                    nome de uma disciplina na página inteira não diz nada:
                    ele está escrito também na lista de caixas, onde as
                    desmarcadas continuam aparecendo — e é assim que tem de
                    ser, senão não haveria como marcá-las de volta.
                --}}
                <table data-notas class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Aluno</th>
                            @foreach ($disciplinas as $nome)
                                <th class="px-4 py-2 text-center">{{ $nome }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($resultados as $resultado)
                            <tr wire:key="nota-{{ $resultado->id }}">
                                <td class="px-4 py-3 sm:px-6">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $resultado->aluno->nome }}</span>
                                    <span class="block text-xs text-slate-400">RA {{ $resultado->aluno->ra }}</span>
                                </td>
                                @foreach ($disciplinas as $disciplinaId => $nome)
                                    @php $nota = $resultado->notas->firstWhere('disciplina_id', $disciplinaId); @endphp
                                    <td class="px-4 py-3 text-center">
                                        @if ($nota)
                                            <span @class([
                                                'text-base font-semibold tabular-nums',
                                                'text-emerald-700 dark:text-emerald-400' => (float) $nota->nota >= 6,
                                                'text-rose-700 dark:text-rose-400' => (float) $nota->nota < 6,
                                            ])>
                                                {{ number_format((float) $nota->nota, 2, ',', '.') }}
                                            </span>
                                            <span class="block text-xs text-slate-400 tabular-nums">
                                                {{ number_format((float) $nota->soma_pesos_acertos, 2, ',', '.') }}
                                                de {{ number_format((float) $nota->soma_pesos_total, 2, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-cartao>


        {{--
            O acerto questão a questão fica fechado.

            A tarefa desta tela é a nota — abrir com uma tabela de trinta
            colunas de ✓ e ✗ na frente atrapalha quem veio transcrever.
            Mas é a única leitura nominal por questão que existe no
            sistema (a Análise lê o índice da turma, não o aluno), então
            ela fica, a um clique.
        --}}
        <x-cartao>
            <div class="flex flex-wrap items-center justify-between gap-2 p-4 sm:p-6">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Acerto de cada aluno, questão a questão.
                </p>
                <x-botao variante="secundario" wire:click="$toggle('mostrarQuestoes')">
                    {{ $mostrarQuestoes ? 'Esconder' : 'Ver questão a questão' }}
                </x-botao>
            </div>
        </x-cartao>

        @if ($mostrarQuestoes)
        <x-cartao titulo="Questão a questão"
                  descricao="✓ acertou · ✗ errou. O número é o mesmo que o aluno viu na folha.">
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 text-left sm:px-6">Aluno</th>
                            @foreach ($questoes as $questao)
                                <th class="px-1 py-2 text-center font-normal"
                                    title="{{ $questao->disciplina->nome }} · peso {{ number_format((float) $questao->peso, 2, ',', '.') }}">
                                    {{ $questao->numero }}
                                </th>
                            @endforeach
                            <th class="px-4 py-2 text-center">Acertos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($resultados as $resultado)
                            @php $porQuestao = $resultado->respostas->keyBy('prova_questao_id'); @endphp
                            <tr wire:key="respostas-{{ $resultado->id }}">
                                <td class="px-4 py-2 sm:px-6">
                                    <span class="text-slate-900 dark:text-slate-100">{{ $resultado->aluno->nome }}</span>
                                </td>
                                @foreach ($questoes as $questao)
                                    @php $resposta = $porQuestao->get($questao->id); @endphp
                                    <td class="px-1 py-2 text-center">
                                        @if ($resposta === null)
                                            <span class="text-slate-300" title="Sem resposta importada">·</span>
                                        @elseif ($resposta->acertou)
                                            <span class="font-semibold text-emerald-600 dark:text-emerald-400"
                                                  title="Questão {{ $questao->numero }} — acertou (peso {{ number_format((float) $questao->peso, 2, ',', '.') }})">✓</span>
                                        @else
                                            <span class="font-semibold text-rose-500"
                                                  title="Questão {{ $questao->numero }} — errou (peso {{ number_format((float) $questao->peso, 2, ',', '.') }})">✗</span>
                                        @endif
                                    </td>
                                @endforeach
                                {{--
                                    Conta só as questões mostradas. Somar
                                    as dez enquanto a tabela mostra três
                                    da disciplina escolhida dava "8/10" ao
                                    lado de três colunas — número certo
                                    para pergunta nenhuma.
                                --}}
                                @php
                                    $acertos = $questoes
                                        ->filter(fn ($q) => $porQuestao->get($q->id)?->acertou)
                                        ->count();
                                @endphp
                                <td class="px-4 py-2 text-center tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ $acertos }}/{{ $questoes->count() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-cartao>
        @endif
        @endif
    @endif
</div>
