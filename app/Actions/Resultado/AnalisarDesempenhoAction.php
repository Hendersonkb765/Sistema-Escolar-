<?php

namespace App\Actions\Resultado;

use App\Enums\Bimestre;
use App\Enums\StatusAluno;
use App\Models\Aluno;
use App\Models\NotaDisciplina;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\RespostaAluno;
use App\Models\User;
use App\Support\CriteriosDaAnalise;
use Illuminate\Database\Eloquent\Collection as ColecaoDeModels;
use Illuminate\Support\Collection;

/**
 * Onde a turma teve dificuldade.
 *
 * A leitura por número de questão diz *onde* erraram; a leitura por
 * habilidade diz *o quê* — e é essa que permite intervir. Duas questões
 * de habilidades diferentes com o mesmo índice de acerto pedem aulas
 * diferentes.
 *
 * A habilidade vem do snapshot da prova, não da questão original: uma
 * análise de prova antiga tem de continuar dizendo o que aquela questão
 * avaliava na época.
 */
class AnalisarDesempenhoAction
{
    public function __construct(public readonly CriteriosDaAnalise $criterios) {}

    /**
     * @param  Collection<int, Prova>  $provas
     * @return Collection<int, array<string, mixed>>
     */
    public function porHabilidade(Collection $provas, User $leitor, ?int $disciplinaId = null): Collection
    {
        return $this->respostas($provas, $leitor, $disciplinaId)
            ->groupBy(fn (RespostaAluno $resposta) => self::chave($resposta->provaQuestao->habilidade_snapshot))
            ->map(function (Collection $respostas) {
                $questao = $respostas->first()->provaQuestao;

                return [
                    'habilidade' => $questao->habilidade_snapshot ?: 'Sem habilidade informada',
                    'disciplinas' => $respostas
                        ->map(fn (RespostaAluno $r) => $r->provaQuestao->disciplina->nome)
                        ->unique()->sort()->values()->all(),
                    // Com o id junto: na tela cada número é um botão que
                    // abre a questão, e o número sozinho não abre nada.
                    'questoes' => $respostas
                        ->map(fn (RespostaAluno $r) => [
                            'id' => $r->provaQuestao->id,
                            'numero' => $r->provaQuestao->numero,
                            'prova' => $r->provaQuestao->prova->titulo,
                        ])
                        ->unique('id')
                        ->sortBy('numero')
                        ->values()->all(),
                    ...$this->contagem($respostas),
                ];
            })
            // A que mais precisa de atenção primeiro.
            ->sortBy('percentual')
            ->values();
    }

    /**
     * @param  Collection<int, Prova>  $provas
     * @return Collection<int, array<string, mixed>>
     */
    public function porQuestao(Collection $provas, User $leitor, ?int $disciplinaId = null): Collection
    {
        return $this->respostas($provas, $leitor, $disciplinaId)
            ->groupBy('prova_questao_id')
            ->map(function (Collection $respostas) {
                $questao = $respostas->first()->provaQuestao;

                return [
                    'id' => $questao->id,
                    'numero' => $questao->numero,
                    'prova_id' => $questao->prova_id,
                    'prova' => $questao->prova->titulo,
                    'bimestre' => $questao->prova->bimestre,
                    'disciplina' => $questao->disciplina->nome,
                    'habilidade' => $questao->habilidade_snapshot ?: 'Sem habilidade informada',
                    'peso' => (float) $questao->peso,
                    'enunciado' => $questao->enunciado_snapshot,
                    // Uma questão é uma medição só: o mínimo de questões
                    // não se aplica, e o que resta julgar é quantos
                    // alunos a responderam.
                    ...$this->contagem($respostas, agregaQuestoes: false),
                ];
            })
            ->sortBy('percentual')
            ->values();
    }

    /**
     * Qual alternativa cada questão atraiu — e qual **errada** atraiu mais.
     *
     * "A turma errou a 7" diz que houve erro; "dois terços marcaram a C,
     * que é o resultado de somar antes de multiplicar" diz qual é o erro.
     * É a diferença entre repetir a aula e desfazer a confusão.
     *
     * Só existe quando a planilha trouxe a letra marcada. No formato de
     * acerto e erro o banco guarda `null`, e inventar uma distribuição a
     * partir disso seria desenhar um gráfico de dado nenhum — por isso a
     * questão sai marcada como sem marcação, e a tela diz por quê.
     *
     * Ordenado pela errada que mais atraiu: é por onde se começa.
     *
     * @param  Collection<int, Prova>  $provas
     * @return Collection<int, array<string, mixed>>
     */
    public function porAlternativa(Collection $provas, User $leitor, ?int $disciplinaId = null): Collection
    {
        $respostas = $this->respostas($provas, $leitor, $disciplinaId, comAlternativas: true);

        // O formato é decidido por prova, e não por questão: uma questão
        // que a turma inteira deixou em branco também chega sem letra
        // nenhuma, e as duas coisas não são a mesma.
        $provasComMarcacao = $respostas
            ->filter(fn (RespostaAluno $r) => $r->alternativa_marcada !== null)
            ->map(fn (RespostaAluno $r) => $r->provaQuestao->prova_id)
            ->unique()
            ->flip();

        return $respostas
            ->groupBy('prova_questao_id')
            ->map(function (Collection $daQuestao) use ($provasComMarcacao) {
                $questao = $daQuestao->first()->provaQuestao;

                return [
                    'id' => $questao->id,
                    'numero' => $questao->numero,
                    'prova_id' => $questao->prova_id,
                    'prova' => $questao->prova->titulo,
                    'bimestre' => $questao->prova->bimestre,
                    'disciplina' => $questao->disciplina->nome,
                    'habilidade' => $questao->habilidade_snapshot ?: 'Sem habilidade informada',
                    'enunciado' => $questao->enunciado_snapshot,
                    ...$this->contagem($daQuestao, agregaQuestoes: false),
                    ...$this->escolhas($daQuestao, $provasComMarcacao->has($questao->prova_id)),
                ];
            })
            // A errada que mais atraiu primeiro; depois, a que teve mais
            // erro. Questão sem marcação vai para o fim: não há o que ler.
            ->sortByDesc(fn (array $item) => [
                $item['tem_marcacao'] ? 1 : 0,
                $item['distrator']['total'] ?? -1,
                100 - $item['percentual'],
            ])
            ->values();
    }

    /**
     * Tudo o que o painel de uma questão mostra, numa consulta só.
     *
     * O painel abre de qualquer aba — da grade, da lista por habilidade,
     * do ✓ de um aluno —, e recalcular a análise inteira para desenhar
     * uma questão seria percorrer o recorte à toa.
     *
     * Devolve `null` quando a questão está fora do alcance do leitor: o
     * escopo é o mesmo das outras leituras, e um id na URL não abre
     * questão de outro Eixo nem questão de colega.
     *
     * @return array<string, mixed>|null
     */
    public function questaoEmFoco(ProvaQuestao $questao, User $leitor): ?array
    {
        $visivel = Prova::query()
            ->visivelPara($leitor)
            ->whereKey($questao->prova_id)
            ->exists();

        if (! $visivel) {
            return null;
        }

        if (! $leitor->ehGestao() && (int) $questao->professor_id !== $leitor->getKey()) {
            return null;
        }

        $respostas = RespostaAluno::query()
            ->where('prova_questao_id', $questao->getKey())
            // Só de resultados fora da Lixeira: o aluno excluído não conta mais.
            ->whereHas('resultado')
            ->with(['resultado:id,aluno_id', 'resultado.aluno:id,nome,ra'])
            ->get();

        if ($respostas->isEmpty()) {
            return null;
        }

        $questao->loadMissing(['prova:id,titulo,bimestre', 'disciplina:id,nome']);

        // Aqui a pergunta é da questão, e não da prova: é a única
        // resposta que o painel tem à mão.
        $temMarcacao = $respostas->contains(fn (RespostaAluno $r) => $r->alternativa_marcada !== null);

        return [
            'id' => $questao->id,
            'numero' => $questao->numero,
            'prova_id' => $questao->prova_id,
            'prova' => $questao->prova->titulo,
            'bimestre' => $questao->prova->bimestre,
            'disciplina' => $questao->disciplina->nome,
            'habilidade' => $questao->habilidade_snapshot ?: 'Sem habilidade informada',
            'peso' => (float) $questao->peso,
            'enunciado' => $questao->enunciado_snapshot,
            ...$this->contagem($respostas, agregaQuestoes: false),
            ...$this->escolhas($respostas, $temMarcacao, $questao),
            'nominais' => $respostas
                ->map(fn (RespostaAluno $r) => [
                    'aluno_id' => $r->resultado->aluno_id,
                    'aluno' => $r->resultado->aluno->nome,
                    'ra' => $r->resultado->aluno->ra,
                    'acertou' => (bool) $r->acertou,
                    'marcada' => $r->alternativa_marcada,
                ])
                // Quem errou primeiro: é com esses que o professor vai falar.
                ->sortBy([['acertou', 'asc'], ['aluno', 'asc']])
                ->values(),
        ];
    }

    /**
     * A contagem por alternativa de um conjunto de respostas da **mesma**
     * questão.
     *
     * `em_branco` é quem não marcou nada tendo a folha sido lida por
     * letra; `tem_marcacao` falso é a planilha que não trouxe letra
     * alguma. Os dois chegam como `null` no banco, e separá-los é o que
     * evita dizer "12 alunos deixaram em branco" sobre uma importação
     * que nunca soube quem marcou o quê.
     *
     * @param  Collection<int, RespostaAluno>  $respostas
     * @return array<string, mixed>
     */
    protected function escolhas(Collection $respostas, bool $temMarcacao, ?ProvaQuestao $questao = null): array
    {
        $questao ??= $respostas->first()->provaQuestao;

        $correta = mb_strtoupper((string) $questao->letra_correta);
        $total = $respostas->count();

        $contagem = [];

        foreach ($questao->alternativas_snapshot ?? [] as $alternativa) {
            $contagem[mb_strtoupper((string) $alternativa['letra'])] = 0;
        }

        $emBranco = 0;

        foreach ($respostas as $resposta) {
            $letra = $resposta->alternativa_marcada === null
                ? null
                : mb_strtoupper($resposta->alternativa_marcada);

            if ($letra === null) {
                $emBranco++;

                continue;
            }

            // Letra que não existe entre as alternativas: leitura errada
            // da folha. A conferência já avisou; aqui ela entra assim
            // mesmo, para a soma fechar com o total de respostas.
            $contagem[$letra] = ($contagem[$letra] ?? 0) + 1;
        }

        $alternativas = [];

        foreach ($questao->alternativas_snapshot ?? [] as $alternativa) {
            $letra = mb_strtoupper((string) $alternativa['letra']);

            $alternativas[] = [
                'letra' => $alternativa['letra'],
                'texto' => $alternativa['texto'] ?? '',
                'correta' => $letra === $correta,
                'total' => $temMarcacao ? $contagem[$letra] : 0,
                'percentual' => $temMarcacao && $total > 0
                    ? round(100 * $contagem[$letra] / $total, 1)
                    : 0.0,
            ];
        }

        $escolhidas = collect($alternativas)->where('total', '>', 0);
        $erradas = $escolhidas->where('correta', false);
        $maiorErrada = (int) $erradas->max('total');

        $distratores = $erradas->where('total', $maiorErrada)->values();

        return [
            'letra_correta' => $questao->letra_correta,
            'tem_marcacao' => $temMarcacao,
            'em_branco' => $temMarcacao ? $emBranco : 0,
            'alternativas' => $alternativas,
            'mais_escolhida' => $escolhidas->sortByDesc('total')->first()['letra'] ?? null,
            // A errada que mais atraiu. Empate não se desempata no
            // chute: as duas vão, e a tela mostra as duas.
            'distrator' => $distratores->first(),
            'distratores' => $distratores->all(),
        ];
    }

    /**
     * O painel da turma: quem foi avaliado, como foi, e quem ficou de
     * fora.
     *
     * A média sozinha mente por omissão — uma turma com média 7 pode ter
     * um terço dos alunos abaixo de 5. Por isso vêm juntas a mediana, a
     * distribuição por faixa e a contagem de quem está abaixo do
     * critério.
     *
     * Aluno sem avaliação registrada **não vira zero**: ele entra como
     * pendente e fica de fora das contas. Somar o zero de quem faltou
     * rebaixa a turma inteira por um dado que não existe.
     *
     * @param  Collection<int, Prova>  $provas
     * @return array<string, mixed>
     */
    public function visaoGeral(Collection $provas, User $leitor, ?int $disciplinaId = null): array
    {
        $visiveis = $this->visiveis($provas, $leitor);

        $vazio = [
            'matriculados' => 0, 'avaliados' => 0, 'pendentes' => 0, 'respostas' => 0,
            'notas' => 0, 'media' => null, 'mediana' => null,
            'faixas' => $this->faixasVazias(), 'no_criterio' => 0, 'abaixo' => 0,
            'percentual_no_criterio' => null, 'alunos_abaixo' => 0,
            'percentual_alunos_abaixo' => null, 'pendentes_nomes' => [],
        ];

        if ($visiveis->isEmpty()) {
            return $vazio;
        }

        $turmas = $visiveis->pluck('turma_id')->unique();

        /*
         * Matriculado é quem está ativo na turma hoje. Um aluno
         * transferido no meio do bimestre não é "pendente": ele não
         * deveria ter feito a prova.
         */
        $matriculados = Aluno::query()
            ->visivelPara($leitor)
            ->whereIn('turma_id', $turmas)
            ->where('status', StatusAluno::Ativo)
            ->get(['id', 'nome', 'ra', 'turma_id']);

        $notas = $this->notasDoRecorte($visiveis, $leitor, $disciplinaId);
        $quantasRespostas = $this->contarRespostas($visiveis, $leitor, $disciplinaId);

        $avaliados = $notas->pluck('aluno_id')->unique();

        $valores = $notas->pluck('nota')->map(fn ($n) => (float) $n)->sort()->values();

        $pendentes = $matriculados->reject(fn (Aluno $a) => $avaliados->contains($a->id))->values();

        $noCriterio = $valores->filter(fn (float $n) => $n >= $this->criterios->notaEsperada)->count();

        // Um aluno conta como "abaixo" se QUALQUER nota dele no recorte
        // está abaixo — é essa leitura que encontra o grupo em
        // dificuldade, e não a média do aluno.
        $alunosAbaixo = $notas
            ->filter(fn (array $n) => (float) $n['nota'] < $this->criterios->notaEsperada)
            ->pluck('aluno_id')->unique()->count();

        return [
            'matriculados' => $matriculados->count(),
            'avaliados' => $avaliados->count(),
            'respostas' => $quantasRespostas,
            'pendentes' => $pendentes->count(),
            'pendentes_nomes' => $pendentes->take(12)->map(
                fn (Aluno $a) => ['nome' => $a->nome, 'ra' => $a->ra]
            )->values()->all(),
            'notas' => $valores->count(),
            'media' => $valores->isEmpty() ? null : round($valores->avg(), 2),
            'mediana' => $this->mediana($valores),
            'faixas' => $this->distribuicao($valores),
            'no_criterio' => $noCriterio,
            'abaixo' => $valores->count() - $noCriterio,
            'percentual_no_criterio' => $valores->isEmpty()
                ? null
                : round(100 * $noCriterio / $valores->count(), 1),
            'alunos_abaixo' => $alunosAbaixo,
            'percentual_alunos_abaixo' => $avaliados->isEmpty()
                ? null
                : round(100 * $alunosAbaixo / $avaliados->count(), 1),
        ];
    }

    /**
     * Quantas respostas entram nos cálculos.
     *
     * Conta no banco em vez de hidratar as linhas: a tela só precisa do
     * número, e com três provas de uma turma já são centenas de
     * registros que seriam carregados para serem contados e jogados
     * fora.
     *
     * @param  ColecaoDeModels<int, Prova>  $provas
     */
    public function contarRespostas(ColecaoDeModels $provas, User $leitor, ?int $disciplinaId = null): int
    {
        if ($provas->isEmpty()) {
            return 0;
        }

        return RespostaAluno::query()
            ->whereHas('resultado', fn ($q) => $q->whereIn('prova_id', $provas->modelKeys()))
            ->when(
                ! $leitor->ehGestao(),
                fn ($q) => $q->whereHas('provaQuestao', fn ($p) => $p->where('professor_id', $leitor->getKey()))
            )
            ->when(
                $disciplinaId !== null,
                fn ($q) => $q->whereHas('provaQuestao', fn ($p) => $p->where('disciplina_id', $disciplinaId))
            )
            ->count();
    }

    /**
     * As notas de disciplina do recorte.
     *
     * A unidade é a nota **por disciplina**, e não uma nota única por
     * aluno: somar questões de disciplinas diferentes numa nota só
     * apagaria justamente o que a escola quer ver.
     *
     * @param  ColecaoDeModels<int, Prova>  $provas
     * @return Collection<int, array{aluno_id: int, disciplina: string, nota: float, prova: string}>
     */
    protected function notasDoRecorte(ColecaoDeModels $provas, User $leitor, ?int $disciplinaId): Collection
    {
        return NotaDisciplina::query()
            ->whereHas('resultado', fn ($q) => $q->whereIn('prova_id', $provas->modelKeys()))
            ->when($disciplinaId !== null, fn ($q) => $q->where('disciplina_id', $disciplinaId))
            ->when(
                ! $leitor->ehGestao(),
                // O professor lê as notas das disciplinas em que tem
                // questão na prova — o mesmo recorte das outras leituras.
                fn ($q) => $q->whereHas('resultado.prova.questoes', fn ($p) => $p
                    ->where('professor_id', $leitor->getKey())
                    ->whereColumn('prova_questoes.disciplina_id', 'notas_disciplina.disciplina_id'))
            )
            ->with(['disciplina:id,nome', 'resultado:id,prova_id,aluno_id', 'resultado.prova:id,titulo'])
            ->get()
            ->map(fn (NotaDisciplina $nota) => [
                'aluno_id' => $nota->resultado->aluno_id,
                'disciplina' => $nota->disciplina->nome,
                'nota' => (float) $nota->nota,
                'prova' => $nota->resultado->prova->titulo,
            ]);
    }

    /** @param  Collection<int, float>  $ordenadas */
    protected function mediana(Collection $ordenadas): ?float
    {
        $total = $ordenadas->count();

        if ($total === 0) {
            return null;
        }

        $meio = intdiv($total, 2);

        return round(
            $total % 2 === 1
                ? $ordenadas[$meio]
                : ($ordenadas[$meio - 1] + $ordenadas[$meio]) / 2,
            2,
        );
    }

    /**
     * @param  Collection<int, float>  $valores
     * @return array<int, array{rotulo: string, cor: string, quantidade: int, percentual: float}>
     */
    protected function distribuicao(Collection $valores): array
    {
        $faixas = $this->faixasVazias();

        foreach ($valores as $nota) {
            $faixas[$this->criterios->faixaDaNota($nota)]['quantidade']++;
        }

        $total = $valores->count();

        return array_map(fn (array $faixa) => [
            ...$faixa,
            'percentual' => $total === 0 ? 0.0 : round(100 * $faixa['quantidade'] / $total, 1),
        ], $faixas);
    }

    /** @return array<int, array{rotulo: string, cor: string, quantidade: int}> */
    protected function faixasVazias(): array
    {
        return array_map(
            fn (array $faixa) => ['rotulo' => $faixa['rotulo'], 'cor' => $faixa['cor'], 'quantidade' => 0],
            $this->criterios->faixas,
        );
    }

    /**
     * As respostas das provas escolhidas, já com o que a análise lê.
     *
     * O escopo é o do leitor: o professor enxerga as questões dele, a
     * coordenação as do seu Eixo.
     *
     * @param  Collection<int, Prova>  $provas
     * @return Collection<int, RespostaAluno>
     */
    protected function respostas(
        Collection $provas,
        User $leitor,
        ?int $disciplinaId,
        bool $comAlternativas = false,
    ): Collection {
        if ($provas->isEmpty()) {
            return collect();
        }

        $visiveis = $this->visiveis($provas, $leitor);

        if ($visiveis->isEmpty()) {
            return collect();
        }

        return RespostaAluno::query()
            ->whereHas('resultado', fn ($q) => $q->whereIn('prova_id', $visiveis->modelKeys()))
            ->when(
                ! $leitor->ehGestao(),
                // O professor analisa o que é dele.
                fn ($q) => $q->whereHas('provaQuestao', fn ($p) => $p->where('professor_id', $leitor->getKey()))
            )
            ->when(
                $disciplinaId !== null,
                fn ($q) => $q->whereHas('provaQuestao', fn ($p) => $p->where('disciplina_id', $disciplinaId))
            )
            ->with([
                // O snapshot das alternativas é um JSON por questão, e
                // só a leitura por alternativa precisa dele: fora dela,
                // carregá-lo seria arrastar a prova inteira por engano.
                'provaQuestao:id,prova_id,numero,disciplina_id,habilidade_snapshot,peso,enunciado_snapshot,professor_id'
                    .($comAlternativas ? ',letra_correta,alternativas_snapshot' : ''),
                'provaQuestao.disciplina:id,nome', 'provaQuestao.prova:id,titulo,turma_id,bimestre',
                'resultado:id,prova_id,aluno_id', 'resultado.aluno:id,nome,ra',
            ])
            ->get();
    }

    /**
     * @param  Collection<int, Prova>  $provas
     * @return Collection<int, Prova>
     */
    /**
     * Filtra pelo ESCOPO da prova, e não pela Policy dela.
     *
     * São perguntas diferentes: "esta prova me diz respeito?" e "posso
     * abrir o documento dela?". O professor não abre a folha montada —
     * ela é da coordenação, e traz as questões dos colegas —, mas os
     * resultados das questões dele são dele. Filtrar aqui pela Policy
     * esvaziava a análise inteira do professor no dia em que o acesso ao
     * documento foi fechado.
     *
     * O recorte fino continua adiante: `respostas()` já limita o
     * professor às questões que ele escreveu.
     */
    protected function visiveis(Collection $provas, User $leitor): Collection
    {
        if ($provas->isEmpty()) {
            return $provas;
        }

        $permitidas = Prova::query()
            ->visivelPara($leitor)
            ->whereKey($provas->modelKeys())
            ->pluck('id');

        return $provas->filter(
            fn (Prova $prova) => $permitidas->contains($prova->getKey())
        )->values();
    }

    /**
     * @param  Collection<int, RespostaAluno>  $respostas
     * @return array{respostas: int, acertos: int, erros: int, percentual: float, atencao: bool}
     */
    /**
     * A conta que todas as leituras desta tela usam.
     *
     * **Ponderada pelos pesos**, e não pela contagem de acertos: numa
     * habilidade com uma questão de peso 3 e duas de peso 1, acertar só a
     * de peso 3 dá 33% contando respostas e 60% contando pontos. A
     * segunda é a que o professor usa para decidir, e é a que a escola
     * definiu ao dar peso às questões.
     *
     * `pontuacao` e `peso` já vêm gravados em cada resposta pela
     * importação, então isto é leitura, não recálculo.
     *
     * @param  Collection<int, RespostaAluno>  $respostas
     * @return array<string, mixed>
     */
    protected function contagem(Collection $respostas, bool $agregaQuestoes = true): array
    {
        $total = $respostas->count();
        $acertos = $respostas->where('acertou', true)->count();

        $pontosObtidos = round((float) $respostas->sum('pontuacao'), 2);
        $pontosMaximos = round((float) $respostas->sum('peso'), 2);

        $percentual = $pontosMaximos <= 0
            ? 0.0
            : round(100 * $pontosObtidos / $pontosMaximos, 1);

        $questoes = $respostas->pluck('prova_questao_id')->unique()->count();
        $alunos = $respostas->map(fn (RespostaAluno $r) => $r->resultado->aluno_id)->unique()->count();

        $situacao = $this->criterios->situacao($percentual, $questoes, $total, $agregaQuestoes);

        return [
            'respostas' => $total,
            'alunos' => $alunos,
            'acertos' => $acertos,
            'erros' => $total - $acertos,
            'pontos_obtidos' => $pontosObtidos,
            'pontos_maximos' => $pontosMaximos,
            'percentual' => $percentual,
            'situacao' => $situacao,
            'falta_evidencia' => $this->criterios->faltaDeEvidencia($questoes, $total, $agregaQuestoes),
            // Mantido porque as telas e os testes já leem por este nome;
            // agora só é verdade quando há evidência para afirmá-lo.
            'atencao' => $situacao === 'atencao',
        ];
    }

    /**
     * Quem acertou e quem errou uma habilidade.
     *
     * É a leitura que fecha o ciclo: a lista por habilidade diz que a
     * turma foi mal em "aplicar condicionais"; esta diz em quem.
     *
     * @param  Collection<int, Prova>  $provas
     * @return Collection<int, array<string, mixed>>
     */
    public function porAluno(Collection $provas, User $leitor, string $habilidade, ?int $disciplinaId = null): Collection
    {
        $procurada = self::chave($habilidade);

        return $this->respostas($provas, $leitor, $disciplinaId)
            ->filter(fn (RespostaAluno $r) => self::chave($r->provaQuestao->habilidade_snapshot) === $procurada)
            ->groupBy(fn (RespostaAluno $r) => $r->resultado->aluno_id)
            ->map(function (Collection $respostas) {
                $aluno = $respostas->first()->resultado->aluno;

                return [
                    'aluno_id' => $aluno->getKey(),
                    'aluno' => $aluno->nome,
                    'ra' => $aluno->ra,
                    'questoes' => $respostas
                        ->sortBy(fn (RespostaAluno $r) => $r->provaQuestao->numero)
                        ->map(fn (RespostaAluno $r) => [
                            'id' => $r->provaQuestao->id,
                            'numero' => $r->provaQuestao->numero,
                            'prova' => $r->provaQuestao->prova->titulo,
                            'acertou' => (bool) $r->acertou,
                        ])
                        ->values()
                        ->all(),
                    ...$this->contagem($respostas),
                ];
            })
            // Quem mais precisa de ajuda primeiro.
            ->sortBy([['percentual', 'asc'], ['aluno', 'asc']])
            ->values();
    }

    /**
     * Quem acertou e quem errou **uma** questão, com nome.
     *
     * Fecha a leitura por questão: a grade diz que a 7 teve 33% de
     * acerto; esta lista diz quem são os dois terços.
     *
     * O escopo é o mesmo das outras leituras — a questão precisa estar
     * numa prova visível ao leitor, e o professor só alcança as que
     * escreveu. Sem isso, um id na URL abriria questão de outro Eixo.
     *
     * @return Collection<int, array{aluno: string, ra: ?string, acertou: bool}>
     */
    public function respostasDaQuestao(ProvaQuestao $questao, User $leitor): Collection
    {
        $visivel = Prova::query()
            ->visivelPara($leitor)
            ->whereKey($questao->prova_id)
            ->exists();

        if (! $visivel) {
            return collect();
        }

        if (! $leitor->ehGestao() && (int) $questao->professor_id !== $leitor->getKey()) {
            return collect();
        }

        return RespostaAluno::query()
            ->where('prova_questao_id', $questao->getKey())
            // Só de resultados fora da Lixeira: o aluno excluído não conta mais.
            ->whereHas('resultado')
            ->with(['resultado:id,aluno_id', 'resultado.aluno:id,nome,ra'])
            ->get()
            ->map(fn (RespostaAluno $resposta) => [
                'aluno_id' => $resposta->resultado->aluno_id,
                'aluno' => $resposta->resultado->aluno->nome,
                'ra' => $resposta->resultado->aluno->ra,
                'acertou' => (bool) $resposta->acertou,
                // Nula quando a planilha veio no formato de acerto e erro.
                'marcada' => $resposta->alternativa_marcada,
            ])
            // Quem errou primeiro: é com esses que o professor vai falar.
            ->sortBy([['acertou', 'asc'], ['aluno', 'asc']])
            ->values();
    }

    /**
     * O ✓ e o ✗ de cada aluno, questão a questão.
     *
     * É a única leitura **nominal** por questão do sistema: as outras
     * daqui contam a turma. Serve para conferir a leitura da folha e para
     * ver, de um aluno só, onde ele tropeçou.
     *
     * Exige uma prova, e não um recorte: o número da questão só quer
     * dizer alguma coisa dentro da prova em que ela saiu. Duas provas
     * lado a lado teriam duas questões "1" diferentes na mesma coluna.
     *
     * @return array{questoes: array<int, array<string, mixed>>, alunos: array<int, array<string, mixed>>}
     */
    public function acertoDeCadaAluno(Prova $prova, User $leitor, ?int $disciplinaId = null): array
    {
        // Coleção do Eloquent, e não `collect()`: o filtro interno usa
        // `modelKeys()`, que só existe nela.
        $respostas = $this->respostas(new ColecaoDeModels([$prova]), $leitor, $disciplinaId);

        $questoes = $respostas
            ->map(fn (RespostaAluno $r) => $r->provaQuestao)
            ->unique('id')
            ->sortBy('numero')
            ->map(fn (ProvaQuestao $q) => [
                'id' => $q->id,
                'numero' => $q->numero,
                'disciplina' => $q->disciplina->nome,
                'peso' => (float) $q->peso,
                'habilidade' => $q->habilidade_snapshot,
            ])
            ->values();

        $alunos = $respostas
            ->groupBy(fn (RespostaAluno $r) => $r->resultado->aluno_id)
            ->map(function (Collection $doAluno) {
                $aluno = $doAluno->first()->resultado->aluno;
                $porQuestao = $doAluno->keyBy(fn (RespostaAluno $r) => $r->provaQuestao->id);

                return [
                    'aluno_id' => $aluno->id,
                    'aluno' => $aluno->nome,
                    'ra' => $aluno->ra,
                    'acertou' => $porQuestao->map(fn (RespostaAluno $r) => (bool) $r->acertou)->all(),
                    'acertos' => $doAluno->where('acertou', true)->count(),
                    'total' => $doAluno->count(),
                ];
            })
            ->sortBy('aluno')
            ->values();

        return ['questoes' => $questoes->all(), 'alunos' => $alunos->all()];
    }

    /**
     * A nota média de cada disciplina, bimestre a bimestre.
     *
     * Uma prova sem resultado não vira zero: o bimestre fica sem ponto e
     * a linha se interrompe, que é a verdade — não houve avaliação.
     *
     * @param  Collection<int, Prova>  $provas
     * @return array<string, array<int, ?float>> disciplina => nota por bimestre
     */
    public function evolucaoPorBimestre(Collection $provas, User $leitor, ?int $disciplinaId = null): array
    {
        $notas = NotaDisciplina::query()
            ->whereHas('resultado', fn ($q) => $q->whereIn('prova_id', $this->visiveis($provas, $leitor)->modelKeys()))
            ->when($disciplinaId !== null, fn ($q) => $q->where('disciplina_id', $disciplinaId))
            ->with(['disciplina:id,nome', 'resultado:id,prova_id', 'resultado.prova:id,bimestre'])
            ->get();

        $evolucao = [];

        foreach ($notas->groupBy(fn (NotaDisciplina $nota) => $nota->disciplina->nome) as $nome => $daDisciplina) {
            $porBimestre = $daDisciplina->groupBy(fn (NotaDisciplina $nota) => $nota->resultado->prova->bimestre->value);

            foreach (Bimestre::cases() as $bimestre) {
                $doBimestre = $porBimestre->get($bimestre->value);

                $evolucao[$nome][] = $doBimestre === null
                    ? null
                    : round((float) $doBimestre->avg('nota'), 2);
            }
        }

        ksort($evolucao);

        return $evolucao;
    }

    /** Agrupa "Interpretar Gráficos" e "interpretar  gráficos" juntas. */
    public static function chave(?string $habilidade): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $habilidade)) ?? '');
    }
}
