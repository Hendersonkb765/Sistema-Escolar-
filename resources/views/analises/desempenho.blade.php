@use('Illuminate\Support\Js')

<div class="space-y-4">
    <x-cartao titulo="Recorte">
        @if ($turmaDoRecorte)
            <x-slot:acoes>
                <x-botao variante="secundario"
                         href="{{ route('resultados.boletim', array_filter([
                            'turma' => $turmaDoRecorte->id,
                            'bimestre' => $filtroBimestre ?: null,
                         ])) }}"
                         title="PDF com uma página por aluno, para entregar a folha certa a cada um.">
                    Gerar boletins
                </x-botao>
            </x-slot:acoes>
        @endif
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <x-campo rotulo="Ano letivo" para="filtro-ano">
                <x-select id="filtro-ano" wire:model.live="filtroAno">
                    <option value="">Todos</option>
                    @foreach ($anos as $ano)
                        <option value="{{ $ano }}">{{ $ano }}</option>
                    @endforeach
                </x-select>
            </x-campo>

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

            <x-campo rotulo="Disciplina" para="filtro-disciplina">
                <x-select id="filtro-disciplina" wire:model.live="filtroDisciplina">
                    <option value="">Todas</option>
                    @foreach ($disciplinas as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </x-select>
            </x-campo>
        </div>

        {{--
            As provas do recorte, uma caixa cada.

            Consolidar duas provas do mesmo bimestre é o caso comum de
            quem quer olhar a habilidade com evidência bastante — e um
            select de uma opção só não deixa.
        --}}
        @if ($provasDisponiveis->isNotEmpty())
            <div class="mt-4">
                <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                    Provas consideradas
                    <span class="font-normal text-slate-500 dark:text-slate-400">
                        — nenhuma marcada vale por todas as {{ $provasDisponiveis->count() }} do recorte
                    </span>
                </p>

                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($provasDisponiveis as $opcao)
                        <label wire:key="prova-{{ $opcao->id }}"
                               class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                            <input type="checkbox" value="{{ $opcao->id }}" wire:model.live="provasEscolhidas"
                                   class="mt-0.5 rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                            <span class="min-w-0">
                                <span class="block truncate text-slate-900 dark:text-slate-100">{{ $opcao->titulo }}</span>
                                <span class="block text-xs text-slate-400">
                                    {{ $opcao->turma->nome }} · {{ $opcao->bimestre->rotulo() }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($provas->isNotEmpty())
            {{-- Quantos dados entram na conta: um percentual sem o tamanho da amostra não se lê. --}}
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                {{ $provas->count() }} prova(s),
                {{ $geral['avaliados'] }} aluno(s) avaliado(s) e
                {{ collect($porQuestao)->sum('respostas') }} resposta(s) entram nos cálculos.
            </p>
        @endif

        @foreach ($limitacoes as $aviso)
            <x-alerta tipo="atencao" class="mt-3">{{ $aviso }}</x-alerta>
        @endforeach
    </x-cartao>

    @unless ($grafico->vazio())
        {{--
            O painel da turma.

            A média sozinha mente por omissão: uma turma com média 7 pode
            ter um terço dos alunos abaixo de 5. Por isso ela vem ao lado
            da mediana, da distribuição e de quantos alunos têm ao menos
            uma nota abaixo do critério — que é o número que encontra o
            grupo em dificuldade.

            Cada indicador diz como foi calculado: um número sem a conta
            atrás dele não dá para contestar nem para usar em reunião.
        --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-indicador rotulo="Alunos avaliados"
                         :valor="$geral['avaliados'].' de '.$geral['matriculados']"
                         :cor="$geral['pendentes'] > 0 ? 'amarelo' : 'verde'"
                         :detalhe="$geral['pendentes'] === 0
                            ? 'Todos os matriculados têm resultado importado.'
                            : $geral['pendentes'].' sem resultado importado — não entram nas contas.'"/>

            <x-indicador rotulo="Média das notas"
                         :valor="$geral['media'] === null ? '—' : number_format($geral['media'], 2, ',', '.')"
                         cor="azul"
                         :detalhe="$geral['notas'].' nota(s) de disciplina lançadas no recorte.'"/>

            <x-indicador rotulo="Mediana"
                         :valor="$geral['mediana'] === null ? '—' : number_format($geral['mediana'], 2, ',', '.')"
                         cor="azul"
                         detalhe="O valor do meio. Longe da média, indica turma partida em dois grupos."/>

            <x-indicador rotulo="Alunos abaixo do critério"
                         :valor="$geral['percentual_alunos_abaixo'] === null
                            ? '—'
                            : $geral['alunos_abaixo'].' ('.number_format($geral['percentual_alunos_abaixo'], 1, ',', '.').'%)'"
                         :cor="($geral['alunos_abaixo'] ?? 0) > 0 ? 'vermelho' : 'verde'"
                         :detalhe="'Com ao menos uma nota abaixo de '
                            .number_format($criterios->notaEsperada, 1, ',', '.').', entre os avaliados.'"/>
        </div>

        @if ($geral['notas'] > 0)
            <x-cartao titulo="Distribuição das notas"
                      :descricao="'Por faixa, entre as '.$geral['notas'].' notas de disciplina do recorte. '
                        .$geral['no_criterio'].' atingiram o critério de '
                        .number_format($criterios->notaEsperada, 1, ',', '.').'.'">
                <div class="space-y-2 p-4 sm:p-6">
                    @foreach ($geral['faixas'] as $faixa)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-16 shrink-0 tabular-nums text-slate-600 dark:text-slate-300">
                                {{ $faixa['rotulo'] }}
                            </span>
                            <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                {{-- A cor não é o único sinal: o número vem ao lado. --}}
                                <div @class([
                                        'h-full rounded-full',
                                        'bg-rose-500' => $faixa['cor'] === 'vermelho',
                                        'bg-amber-500' => $faixa['cor'] === 'amarelo',
                                        'bg-emerald-500' => $faixa['cor'] === 'verde',
                                     ])
                                     style="width: {{ $faixa['percentual'] }}%"></div>
                            </div>
                            <span class="w-28 shrink-0 text-right tabular-nums text-slate-500 dark:text-slate-400">
                                {{ $faixa['quantidade'] }} ({{ number_format($faixa['percentual'], 1, ',', '.') }}%)
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($geral['pendentes'] > 0)
                    <div class="border-t border-slate-200 px-4 py-3 text-sm sm:px-6 dark:border-slate-800">
                        <p class="font-medium text-slate-700 dark:text-slate-200">
                            {{ $geral['pendentes'] }} aluno(s) sem avaliação registrada
                        </p>
                        @php
                            $nomes = collect($geral['pendentes_nomes'])->pluck('nome')->join(', ');
                            $sobraram = $geral['pendentes'] - count($geral['pendentes_nomes']);
                        @endphp
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Ficam de fora da média e da distribuição: nota que não existe não é zero.
                            {{ $nomes }}{{ $sobraram > 0 ? " e mais {$sobraram}" : '' }}.
                        </p>
                    </div>
                @endif
            </x-cartao>
        @endif

        <x-cartao titulo="Evolução por bimestre"
                  descricao="Nota média de cada disciplina. Bimestre sem avaliação não vira zero — a linha se interrompe.">
            {!! $grafico->svg() !!}

            {{-- A leitura em tabela é obrigatória: quem não distingue as
                 cores, ou lê por leitor de tela, precisa dos números. --}}
            <details class="mt-2">
                <summary class="cursor-pointer text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                    Ver os mesmos números em tabela
                </summary>

                <div class="-mx-4 mt-2 overflow-x-auto sm:-mx-6">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-2 sm:px-6">Disciplina</th>
                                @foreach ($grafico->bimestres as $rotulo)
                                    <th class="px-4 py-2 text-right">{{ $rotulo }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($grafico->series as $serie)
                                <tr wire:key="serie-{{ $loop->index }}">
                                    <td class="px-4 py-2 sm:px-6">
                                        <span class="mr-1 inline-block h-2 w-4 rounded-full align-middle"
                                              style="background: {{ $serie['cor'] }}"></span>
                                        <span class="text-slate-900 dark:text-slate-100">{{ $serie['nome'] }}</span>
                                    </td>
                                    @foreach ($serie['pontos'] as $ponto)
                                        <td class="px-4 py-2 text-right tabular-nums text-slate-600 dark:text-slate-300">
                                            {{ $ponto === null ? '—' : number_format($ponto, 2, ',', '.') }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </x-cartao>
    @endunless

    @if ($porHabilidade->isEmpty())
        <x-cartao>
            <x-vazio titulo="Nada para analisar neste recorte"
                     descricao="A análise usa as provas que já tiveram os resultados importados. Ajuste os filtros ou importe uma planilha."/>
        </x-cartao>
    @else
        <x-cartao titulo="Por habilidade avaliada"
                  :descricao="'Ordenado da que mais precisa de atenção. Abaixo de '.$criterios->criterioEsperado.'% de aproveitamento vai destacado.'">
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2 sm:px-6">Habilidade</th>
                            <th class="hidden px-4 py-2 md:table-cell">Disciplina</th>
                            <th class="hidden px-4 py-2 lg:table-cell">Questões</th>
                            <th class="px-4 py-2 text-center">Acertos</th>
                            <th class="px-4 py-2">Índice de acerto</th>
                            <th class="px-4 py-2 text-right sm:px-6">Alunos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($porHabilidade as $item)
                            <tr wire:key="hab-{{ $loop->index }}"
                                @class(['bg-rose-50/60 dark:bg-rose-950/20' => $item['atencao']])>
                                <td class="px-4 py-3 sm:px-6">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $item['habilidade'] }}</span>

                                    {{--
                                        Três estados, e não dois. "Evidências
                                        insuficientes" não é um meio-termo entre
                                        bom e ruim: é a recusa de classificar sem
                                        base, e some da lista de prioridades em
                                        vez de entrar como problema.
                                    --}}
                                    <x-badge class="ml-1"
                                             :cor="App\Support\CriteriosDaAnalise::cores()[$item['situacao']]"
                                             :rotulo="App\Support\CriteriosDaAnalise::rotulos()[$item['situacao']]"/>

                                    @if ($item['falta_evidencia'] !== '')
                                        <span class="block pt-0.5 text-xs text-slate-400">
                                            {{ $item['falta_evidencia'] }}
                                        </span>
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
                                <td class="px-4 py-3 text-right sm:px-6">
                                    {{-- `{{ }}`, e não `@js(...)`: dentro da tag de um
                                         componente a diretiva não é compilada e sai
                                         literal no HTML, deixando o botão morto. --}}
                                    <x-botao variante="discreto"
                                             wire:click="verAlunos({{ Js::from($item['habilidade']) }})">
                                        {{ $habilidadeAberta === $item['habilidade'] ? 'Fechar' : 'Ver alunos' }}
                                    </x-botao>
                                </td>
                            </tr>

                            @if ($habilidadeAberta === $item['habilidade'])
                                <tr wire:key="alunos-{{ $loop->index }}">
                                    <td colspan="6" class="bg-slate-50 px-4 py-3 sm:px-6 dark:bg-slate-900/40">
                                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                            Aluno a aluno — de quem mais precisa de ajuda para quem menos
                                        </p>

                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead>
                                                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                                        <th class="py-1 pr-4">Aluno</th>
                                                        <th class="py-1 pr-4">Questões</th>
                                                        <th class="py-1 pr-4 text-center">Acertos</th>
                                                        <th class="py-1">Índice</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($alunosDaHabilidade as $aluno)
                                                        <tr wire:key="aluno-{{ $loop->index }}">
                                                            <td class="py-1.5 pr-4">
                                                                <span class="text-slate-900 dark:text-slate-100">{{ $aluno['aluno'] }}</span>
                                                                <span class="block text-xs text-slate-400">RA {{ $aluno['ra'] }}</span>
                                                            </td>
                                                            <td class="py-1.5 pr-4">
                                                                @foreach ($aluno['questoes'] as $questao)
                                                                    <span @class([
                                                                        'mr-1 inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-xs',
                                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' => $questao['acertou'],
                                                                        'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' => ! $questao['acertou'],
                                                                    ]) title="{{ $questao['prova'] }}">
                                                                        {{ $questao['acertou'] ? '✓' : '✗' }} {{ $questao['numero'] }}
                                                                    </span>
                                                                @endforeach
                                                            </td>
                                                            <td class="py-1.5 pr-4 text-center tabular-nums text-slate-600 dark:text-slate-300">
                                                                {{ $aluno['acertos'] }}/{{ $aluno['respostas'] }}
                                                            </td>
                                                            <td class="py-1.5">
                                                                <x-barra-de-acerto :percentual="$aluno['percentual']" :atencao="$aluno['atencao']"/>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-cartao>

        {{--
            "Índice de acerto por questão", e não "Questão a questão": a
            outra tabela desta página, logo abaixo, é o ✓ e o ✗ de cada
            aluno. Duas tabelas com o mesmo nome e leituras diferentes —
            uma da turma, outra nominal — é o tipo de coisa que faz
            alguém ler o número errado em reunião.
        --}}
        <x-cartao titulo="Índice de acerto por questão"
                  descricao="A mesma leitura pelo número, com a habilidade ao lado. É a turma, não o aluno.">
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

        {{--
            O ✓ e o ✗ de cada aluno.

            Veio da tela de notas, onde atrapalhava quem só queria
            transcrever a nota, e aqui fica junto das outras leituras por
            questão. É a única nominal das três: as de cima contam a
            turma.

            Exige uma prova, e não um recorte — o número da questão só
            quer dizer alguma coisa dentro da prova em que ela saiu. Duas
            provas lado a lado teriam duas questões "1" diferentes na
            mesma coluna.
        --}}
        <x-cartao titulo="Acerto de cada aluno">
            <x-slot:descricao>
                @if ($provaDoRecorte)
                    ✓ acertou · ✗ errou, em {{ $provaDoRecorte->titulo }}.
                    O número é o mesmo que o aluno viu na folha.
                @else
                    Escolha uma prova no recorte: o número da questão só vale dentro de uma prova.
                @endif
            </x-slot:descricao>

            @if ($acertoNominal === null)
                <div class="p-4 sm:p-6">
                    <x-vazio titulo="Uma prova por vez"
                             descricao="Esta leitura é aluno a aluno, questão a questão. Escolha uma prova no recorte acima para vê-la."/>
                </div>
            @elseif ($acertoNominal['alunos'] === [])
                <div class="p-4 sm:p-6">
                    <x-vazio titulo="Nenhum resultado neste recorte"
                             descricao="Ajuste a disciplina ou importe a planilha do leitor de folhas."/>
                </div>
            @else
                <div class="-mx-4 overflow-x-auto sm:-mx-6">
                    {{-- `data-nominal` marca a tabela para os testes: "1/2"
                         aparece em mais de um lugar desta página. --}}
                    <table data-nominal class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead>
                            <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-2 text-left sm:px-6">Aluno</th>
                                @foreach ($acertoNominal['questoes'] as $questao)
                                    <th class="px-1 py-2 text-center font-normal"
                                        title="{{ $questao['disciplina'] }} · {{ $questao['habilidade'] ?: 'sem habilidade' }} · peso {{ number_format($questao['peso'], 2, ',', '.') }}">
                                        {{ $questao['numero'] }}
                                    </th>
                                @endforeach
                                <th class="px-4 py-2 text-center">Acertos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($acertoNominal['alunos'] as $linha)
                                <tr wire:key="nominal-{{ $loop->index }}">
                                    <td class="px-4 py-2 sm:px-6">
                                        <span class="text-slate-900 dark:text-slate-100">{{ $linha['aluno'] }}</span>
                                        <span class="block text-xs text-slate-400">RA {{ $linha['ra'] }}</span>
                                    </td>
                                    @foreach ($acertoNominal['questoes'] as $questao)
                                        @php $acertou = $linha['acertou'][$questao['id']] ?? null; @endphp
                                        <td class="px-1 py-2 text-center">
                                            @if ($acertou === null)
                                                <span class="text-slate-300" title="Sem resposta importada">·</span>
                                            @elseif ($acertou)
                                                <span class="font-semibold text-emerald-600 dark:text-emerald-400"
                                                      title="Questão {{ $questao['numero'] }} — acertou">✓</span>
                                            @else
                                                <span class="font-semibold text-rose-500"
                                                      title="Questão {{ $questao['numero'] }} — errou">✗</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-2 text-center tabular-nums text-slate-600 dark:text-slate-300">
                                        {{ $linha['acertos'] }}/{{ $linha['total'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-cartao>
    @endif
</div>
