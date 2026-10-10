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

        {{--
            As ressalvas numa linha só, abertas a pedido.

            Dois blocos amarelos no topo aparecem no recorte padrão — que
            junta todas as provas — e viram ruído de fundo: quem vê aviso
            toda vez para de ler aviso. O conteúdo continua inteiro, a um
            clique.
        --}}
        @if ($limitacoes !== [])
            <details class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                <summary class="cursor-pointer text-amber-900 dark:text-amber-200">
                    {{ count($limitacoes) }} ressalva(s) sobre consolidar estas provas
                </summary>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-amber-900/90 dark:text-amber-200/90">
                    @foreach ($limitacoes as $aviso)
                        <li>{{ $aviso }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </x-cartao>

    {{--
        A página tem três telas de rolagem e nenhuma pista do que vem
        abaixo. Os atalhos dizem o que existe e levam direto.
    --}}
    @if ($porHabilidade->isNotEmpty())
        <nav aria-label="Seções da análise"
             class="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
            <span class="mr-1">Ir para:</span>
            @foreach ([
                '#turma' => 'A turma',
                '#evolucao' => 'Evolução',
                '#habilidades' => 'Habilidades',
                '#questoes' => 'Questões',
                '#alunos' => 'Alunos',
            ] as $destino => $rotulo)
                <a href="{{ $destino }}"
                   class="rounded-md px-2 py-1 transition hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-slate-100">
                    {{ $rotulo }}
                </a>
            @endforeach
        </nav>
    @endif

    {{--
        O painel da turma sai de dentro do `@unless` do gráfico.

        Estavam acoplados: sem série para desenhar, a visão geral da
        turma sumia junto — e ela não depende do gráfico, depende de
        haver prova no recorte.
    --}}
    @if ($provas->isNotEmpty())
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
        <div id="turma" class="grid scroll-mt-20 gap-3 sm:grid-cols-2 lg:grid-cols-4">
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

    @endif

    @unless ($grafico->vazio())
        <x-cartao id="evolucao" class="scroll-mt-20" titulo="Evolução por bimestre">
            <x-slot:descricao>
                Nota média de cada disciplina, um painel por disciplina. A linha
                tracejada é o critério de
                {{ number_format($criterios->notaEsperada, 1, ',', '.') }}.
                Bimestre sem avaliação não tem coluna e aparece como “—”:
                ausência de prova não é queda de desempenho.
            </x-slot:descricao>
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
                                    {{-- Sem bolinha de cor: cada disciplina tem
                                         painel próprio, e a identidade vem do
                                         título dele, não de um tom. --}}
                                    <td class="px-4 py-2 sm:px-6">
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
        <x-cartao id="habilidades" class="scroll-mt-20" titulo="Por habilidade avaliada"
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
        {{--
            A grade de questões.

            Eram 27 linhas de tabela, uma por questão, ordenadas por
            percentual — metade da altura da página para um dado de
            consulta. Pior: o número da questão só identifica dentro de
            uma prova, e com três provas no recorte havia três "questão 2"
            na mesma lista, sem nada que as distinguisse.

            Agora é uma linha por prova, na ordem em que o aluno viu as
            questões. A cor é um atalho de cinco degraus — passados uns
            sete tons dois vizinhos deixam de se distinguir —, e o número
            do aproveitamento vai escrito dentro de cada célula: a cor
            nunca é o único canal.
        --}}
        <x-cartao id="questoes" class="scroll-mt-20" titulo="Acerto por questão">
            <x-slot:descricao>
                Uma linha por prova, na ordem em que o aluno viu as questões.
                Quanto mais forte a célula, mais a turma errou. Clique numa
                questão para ver o detalhe dela.
            </x-slot:descricao>

            @php
                /*
                 * Escritos por extenso, e não `'degrau-'.$n`: o Tailwind
                 * varre o código-fonte e remove do CSS toda classe que
                 * não encontrar escrita. Montada em tempo de execução, a
                 * regra existe no arquivo e some do bundle — as células
                 * saem sem cor nenhuma, sem erro em lugar algum.
                 */
                $degraus = ['degrau-0', 'degrau-1', 'degrau-2', 'degrau-3', 'degrau-4'];
            @endphp

            <div class="space-y-5 p-4 sm:p-6">
                @foreach ($questoesPorProva as $grupo)
                    <div wire:key="grade-{{ $loop->index }}">
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                            {{ $grupo['prova'] }}
                            <span class="font-normal text-slate-500 dark:text-slate-400">
                                · {{ $grupo['bimestre']->rotulo() }}
                            </span>
                        </p>

                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($grupo['questoes'] as $questao)
                                @php
                                    $degrau = $criterios->degrauDoAproveitamento($questao['percentual']);
                                    $aberta = $questaoAberta === $questao['id'];
                                @endphp

                                <button type="button" wire:click="verQuestao({{ $questao['id'] }})"
                                        wire:key="celula-{{ $questao['id'] }}"
                                        @class([
                                            'celula-questao',
                                            $degraus[$degrau],
                                            'aberta' => $aberta,
                                        ])
                                        aria-pressed="{{ $aberta ? 'true' : 'false' }}"
                                        title="Questão {{ $questao['numero'] }} — {{ $questao['habilidade'] }} — {{ number_format($questao['percentual'], 1, ',', '.') }}% de aproveitamento">
                                    <span class="numero">{{ $questao['numero'] }}</span>
                                    <span class="percentual">{{ round($questao['percentual']) }}%</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- A legenda nomeia os degraus: cor sem escala não se lê. --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-200 pt-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <span>Aproveitamento:</span>
                    @foreach ([[0, 'abaixo de 40%'], [1, '40 a 55%'], [2, '55 a 70%'], [3, '70 a 85%'], [4, '85% ou mais']] as [$degrau, $rotulo])
                        <span class="flex items-center gap-1.5">
                            <span class="celula-amostra {{ $degraus[$degrau] }}"></span>{{ $rotulo }}
                        </span>
                    @endforeach
                </div>
            </div>

            @if ($questaoEmDetalhe)
                <div class="border-t border-slate-200 bg-slate-50 px-4 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-950/40">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900 dark:text-slate-100">
                                Questão {{ $questaoEmDetalhe['numero'] }} · {{ $questaoEmDetalhe['prova'] }}
                            </p>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {{ $questaoEmDetalhe['disciplina'] }} · {{ $questaoEmDetalhe['habilidade'] }}
                                · peso {{ number_format($questaoEmDetalhe['peso'], 2, ',', '.') }}
                            </p>
                        </div>
                        <x-botao variante="discreto" wire:click="verQuestao({{ $questaoEmDetalhe['id'] }})">
                            Fechar
                        </x-botao>
                    </div>

                    <p class="mt-3 text-sm text-slate-700 dark:text-slate-200">
                        {{ $questaoEmDetalhe['enunciado'] }}
                    </p>

                    <dl class="mt-4 grid gap-3 sm:grid-cols-4">
                        @foreach ([
                            ['Responderam', $questaoEmDetalhe['respostas'].' de '.$questaoEmDetalhe['alunos'].' aluno(s)'],
                            ['Acertos', $questaoEmDetalhe['acertos'].' · erros '.$questaoEmDetalhe['erros']],
                            ['Pontos', number_format($questaoEmDetalhe['pontos_obtidos'], 2, ',', '.').' de '.number_format($questaoEmDetalhe['pontos_maximos'], 2, ',', '.')],
                            ['Aproveitamento', number_format($questaoEmDetalhe['percentual'], 1, ',', '.').'%'],
                        ] as [$rotulo, $valor])
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $rotulo }}</dt>
                                <dd class="mt-0.5 text-sm tabular-nums text-slate-900 dark:text-slate-100">{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    {{--
                        Hipótese, e não conclusão. Muita gente errar uma
                        questão não prova que o conteúdo não foi aprendido:
                        pode ser enunciado ambíguo, alternativa mal escrita
                        ou gabarito trocado. Quem decide é quem dá a aula.
                    --}}
                    @if ($questaoEmDetalhe['situacao'] === 'atencao')
                        <x-alerta tipo="atencao" class="mt-4">
                            <p class="font-medium">Vale investigar</p>
                            <p class="mt-0.5">
                                O aproveitamento ficou abaixo do critério de
                                {{ number_format($criterios->criterioEsperado, 0) }}%. Isso pode ser dificuldade
                                com “{{ $questaoEmDetalhe['habilidade'] }}”, mas também enunciado ambíguo,
                                alternativa mal formulada ou gabarito trocado — a leitura é de quem deu a aula.
                            </p>
                        </x-alerta>
                    @elseif ($questaoEmDetalhe['falta_evidencia'] !== '')
                        <x-alerta tipo="info" class="mt-4">
                            Evidências insuficientes para concluir:
                            {{ $questaoEmDetalhe['falta_evidencia'] }}.
                        </x-alerta>
                    @endif

                    <p class="mt-3 text-xs text-slate-400">
                        A distribuição das respostas por alternativa não aparece aqui porque a planilha
                        do leitor traz apenas acerto ou erro, nunca a letra que o aluno marcou.
                    </p>
                </div>
            @endif

            {{-- A grade é o atalho; a tabela é a consulta, e ela ordena. --}}
            <details class="border-t border-slate-200 px-4 py-3 sm:px-6 dark:border-slate-800">
                <summary class="cursor-pointer text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                    Ver as {{ $porQuestao->count() }} questões em tabela
                </summary>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500 dark:text-slate-400">Ordenar por:</span>
                    @foreach (['erro' => 'Maior erro', 'numero' => 'Ordem da prova', 'habilidade' => 'Habilidade'] as $chave => $rotulo)
                        <x-botao variante="{{ $ordemDasQuestoes === $chave ? 'secundario' : 'discreto' }}"
                                 wire:click="ordenarQuestoes('{{ $chave }}')">{{ $rotulo }}</x-botao>
                    @endforeach
                </div>

                <div class="-mx-4 mt-3 overflow-x-auto sm:-mx-6">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-2 sm:px-6">Questão</th>
                                <th class="px-4 py-2">Habilidade</th>
                                <th class="hidden px-4 py-2 md:table-cell">Disciplina</th>
                                <th class="px-4 py-2 text-center">Acertos</th>
                                <th class="px-4 py-2 text-right">Aproveitamento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($questoesOrdenadas as $item)
                                <tr wire:key="linha-q-{{ $item['id'] }}">
                                    <td class="px-4 py-2 sm:px-6">
                                        <span class="text-slate-900 dark:text-slate-100">{{ $item['numero'] }}</span>
                                        <span class="block text-xs text-slate-400">{{ $item['prova'] }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-700 dark:text-slate-200">{{ $item['habilidade'] }}</td>
                                    <td class="hidden px-4 py-2 text-slate-600 md:table-cell dark:text-slate-300">
                                        {{ $item['disciplina'] }}
                                    </td>
                                    <td class="px-4 py-2 text-center tabular-nums text-slate-600 dark:text-slate-300">
                                        {{ $item['acertos'] }}/{{ $item['respostas'] }}
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums text-slate-900 dark:text-slate-100">
                                        {{ number_format($item['percentual'], 1, ',', '.') }}%
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </x-cartao>

        <x-cartao id="alunos" class="scroll-mt-20" titulo="Acerto de cada aluno">
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
