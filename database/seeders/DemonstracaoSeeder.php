<?php

namespace Database\Seeders;

use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Actions\Academico\RegistrarHistoricoDeTurma;
use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Actions\Avaliacao\EnviarParteAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\CalcularNotasAction;
use App\Enums\Bimestre;
use App\Enums\EventoHistorico;
use App\Enums\PerfilUsuario;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Questao;
use App\Models\QuestaoBloco;
use App\Models\ResultadoAluno;
use App\Models\SolicitacaoParte;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Dados de demonstração para desenvolvimento. Recusa-se a rodar em
 * produção: contas com senha conhecida jamais devem existir lá.
 */
class DemonstracaoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemonstracaoSeeder não roda em produção.');

            return;
        }

        $tecnologia = Eixo::query()->firstOrCreate(['codigo' => 'TEC'], ['nome' => 'Tecnologia da Informação']);
        $gestao = Eixo::query()->firstOrCreate(['codigo' => 'ADM'], ['nome' => 'Gestão e Negócios']);

        $admin = $this->usuario('admin@paeet.local', 'Coordenação PAEET', PerfilUsuario::PaeetAdmin);
        $admin->eixos()->syncWithoutDetaching([$tecnologia->id, $gestao->id]);
        $paeetTecnologia = $this->usuario('paeet.tec@paeet.local', 'Paula Enes (PAEET Tecnologia)', PerfilUsuario::Paeet, $admin);
        $paeetTecnologia->eixos()->syncWithoutDetaching([$tecnologia->id]);

        $paeetGestao = $this->usuario('paeet.adm@paeet.local', 'Adriano Mota (PAEET Gestão)', PerfilUsuario::Paeet, $admin);
        $paeetGestao->eixos()->syncWithoutDetaching([$gestao->id]);
        // Curso de 2 anos: o exemplo do enunciado — Lógica e Redes no 1º
        // período, Back-end e Front-end no 2º.
        $curso = Curso::query()->firstOrCreate(
            ['eixo_id' => $tecnologia->id, 'codigo' => 'DS'],
            ['nome' => 'Desenvolvimento de Sistemas', 'duracao_anos' => 2]
        );

        $disciplinas = collect([
            ['Lógica de Programação', 'LOG', 1, 80],
            ['Redes de Computadores', 'RED', 1, 60],
            ['Matemática Aplicada', 'MAT', 1, 60],
            ['Inglês Técnico', 'ING', 1, 40],
            ['Back-end', 'BKD', 2, 80],
            ['Front-end', 'FRT', 2, 80],
            ['Banco de Dados', 'BDD', 2, 80],
            ['Engenharia de Software', 'ESW', 2, 60],
        ])->map(fn (array $dados) => Disciplina::query()->firstOrCreate(
            ['curso_id' => $curso->id, 'codigo' => $dados[1]],
            ['nome' => $dados[0], 'periodo' => $dados[2], 'carga_horaria' => $dados[3]]
        ));

        $grade = app(PublicarVersaoDeGradeAction::class)->garantirVigente($curso, $admin);

        foreach ([1 => '1 A', 2 => '2 A'] as $periodo => $nome) {
            $turma = Turma::query()->firstOrCreate(
                [
                    'curso_id' => $curso->id,
                    'nome' => $nome,
                    'periodo_letivo' => (string) now()->format('Y'),
                ],
                [
                    'grade_curricular_id' => $grade->id,
                    'periodo' => $periodo,
                ]
            );

            if ($turma->alunos()->doesntExist()) {
                foreach (range(1, 6) as $indice) {
                    Aluno::query()->create([
                        'turma_id' => $turma->id,
                        'nome' => fake('pt_BR')->name(),
                        'ra' => now()->format('Y').$periodo.str_pad((string) $indice, 3, '0', STR_PAD_LEFT),
                    ]);
                }
            }

            if ($turma->historicos()->doesntExist()) {
                app(RegistrarHistoricoDeTurma::class)->executar(
                    turma: $turma,
                    evento: EventoHistorico::TurmaCriada,
                    autor: $admin,
                );
            }
        }
        $professor = $this->usuario('professor@paeet.local', 'Prof. Renato Lima', PerfilUsuario::Professor, $paeetTecnologia);
        $professor->eixos()->syncWithoutDetaching([$tecnologia->id]);
        foreach (['LOG', 'BKD'] as $codigo) {
            $professor->vinculosDocentes()->firstOrCreate(
                ['disciplina_id' => $disciplinas->firstWhere('codigo', $codigo)->id, 'turma_id' => null],
                ['ativo' => true]
            );
        }

        // Mais dois professores: as telas de solicitação e de análise só
        // ficam interessantes com mais de um nome em jogo.
        $professora = $this->usuario('professora@paeet.local', 'Profa. Marta Reis', PerfilUsuario::Professor, $paeetTecnologia);
        $professora->eixos()->syncWithoutDetaching([$tecnologia->id]);
        foreach (['MAT', 'BDD'] as $codigo) {
            $professora->vinculosDocentes()->firstOrCreate(
                ['disciplina_id' => $disciplinas->firstWhere('codigo', $codigo)->id, 'turma_id' => null],
                ['ativo' => true]
            );
        }

        $professorIngles = $this->usuario('ingles@paeet.local', 'Prof. Iara Melo', PerfilUsuario::Professor, $paeetTecnologia);
        $professorIngles->eixos()->syncWithoutDetaching([$tecnologia->id]);
        foreach (['ING', 'ESW'] as $codigo) {
            $professorIngles->vinculosDocentes()->firstOrCreate(
                ['disciplina_id' => $disciplinas->firstWhere('codigo', $codigo)->id, 'turma_id' => null],
                ['ativo' => true]
            );
        }

        // Um PAEET que também leciona: mesma conta, vínculo docente à parte.
        foreach (['RED', 'FRT'] as $codigo) {
            $paeetTecnologia->vinculosDocentes()->firstOrCreate(
                ['disciplina_id' => $disciplinas->firstWhere('codigo', $codigo)->id, 'turma_id' => null],
                ['ativo' => true]
            );
        }

        $modeloTecnologia = $this->modeloDeProva($tecnologia, $admin);
        $this->modeloDeProva($gestao, $admin);

        // O segundo Eixo existia só como linha na tabela. Com curso,
        // turma e professor, os testes de escopo que a gente faz à mão
        // passam a ter dos dois lados.
        $this->cursoDeGestao($gestao, $admin, $paeetGestao);

        $this->solicitacoes($curso, $admin, $professor, $paeetTecnologia, $disciplinas);

        // Três bimestres fechados de ponta a ponta: é o que faz o
        // gráfico de evolução ter o que comparar e a análise por
        // habilidade ter volume.
        $turmaDoPrimeiro = Turma::query()->where('curso_id', $curso->id)->where('periodo', 1)->sole();

        foreach ([
            [Bimestre::Primeiro, [['LOG', $professor, 4], ['MAT', $professora, 3]]],
            [Bimestre::Segundo, [['LOG', $professor, 4], ['RED', $paeetTecnologia, 3], ['MAT', $professora, 3]]],
            [Bimestre::Terceiro, [['RED', $paeetTecnologia, 4], ['ING', $professorIngles, 3], ['MAT', $professora, 3]]],
        ] as [$bimestre, $partes]) {
            $this->cicloDeAvaliacao(
                admin: $admin,
                modelo: $modeloTecnologia,
                turma: $turmaDoPrimeiro,
                bimestre: $bimestre,
                partes: array_map(fn (array $parte) => [
                    'disciplina' => $disciplinas->firstWhere('codigo', $parte[0]),
                    'professor' => $parte[1],
                    'questoes' => $parte[2],
                ], $partes),
            );
        }

        $this->command?->info('Demonstração pronta. Senha de todos: senha-forte-123');
    }

    /** Sem ao menos um modelo não há como montar prova; cada Eixo tem o seu. */
    protected function modeloDeProva(Eixo $eixo, User $autor): ModeloProva
    {
        return ModeloProva::query()->firstOrCreate(
            ['eixo_id' => $eixo->id, 'nome' => 'Padrão — '.$eixo->nome],
            [
                'instituicao' => config('instituicao.nome'),
                'nome_avaliacao' => 'Avaliação Bimestral',
                'cabecalho' => 'Eixo de '.$eixo->nome,
                'instrucoes' => 'Leia cada questão com atenção e marque apenas uma alternativa. '
                    .'Não é permitido consulta.',
                'rodape' => 'Boa prova!',
                'campos_identificacao' => ['aluno', 'ra', 'turma', 'data'],
                // Sob a ABNT o resto da tipografia é da norma, não do modelo.
                'layout' => ['norma' => 'abnt', 'fonte' => 'sans'],
                'ativo' => true,
                'criado_por' => $autor->id,
            ]
        );
    }

    /**
     * Uma prova reúne várias disciplinas: cada uma com seu professor e
     * sua cota de questões. Duas solicitações, uma no prazo e outra já
     * vencida, para a tela mostrar "Atrasada" sem bloquear o envio.
     *
     * @param  Collection<int, Disciplina>  $disciplinas
     */
    /**
     * Duas solicitações que **não** foram respondidas.
     *
     * Os ciclos fechados deixam todas as telas com dados, mas nenhuma
     * com trabalho pendente: sem estas, o professor entra e não tem o
     * que fazer, e o selo "Atrasada" nunca aparece.
     *
     * @param  Collection<int, Disciplina>  $disciplinas
     */
    protected function solicitacoes(
        Curso $curso,
        User $admin,
        User $professor,
        User $paeetQueLeciona,
        $disciplinas,
    ): void {
        $turmaDoPrimeiro = Turma::query()->where('curso_id', $curso->id)->where('periodo', 1)->sole();
        $turmaDoSegundo = Turma::query()->where('curso_id', $curso->id)->where('periodo', 2)->sole();

        $criar = app(CriarSolicitacaoAction::class);

        // No prazo: é o que o professor encontra para responder.
        $this->seNaoExistir('Avaliação do 4º bimestre', fn () => $criar->executar(
            autor: $admin,
            turma: $turmaDoPrimeiro,
            partes: [
                [
                    'disciplina_id' => $disciplinas->firstWhere('codigo', 'LOG')->id,
                    'professor_id' => $professor->id,
                    'quantidade_questoes' => 5,
                    'observacoes' => 'Foque em estruturas de repetição.',
                ],
                [
                    'disciplina_id' => $disciplinas->firstWhere('codigo', 'RED')->id,
                    'professor_id' => $paeetQueLeciona->id,
                    'quantidade_questoes' => 3,
                ],
            ],
            quantidadeAlternativas: 4,
            prazo: now()->addWeek(),
            bimestre: Bimestre::Quarto,
            titulo: 'Avaliação do 4º bimestre',
            observacoes: 'Priorize o conteúdo do último bimestre.',
        ));

        // Prazo vencido: o selo "Atrasada" precisa de um caso.
        $this->seNaoExistir('Recuperação', fn () => $criar->executar(
            autor: $admin,
            turma: $turmaDoSegundo,
            partes: [
                [
                    'disciplina_id' => $disciplinas->firstWhere('codigo', 'FRT')->id,
                    'professor_id' => $paeetQueLeciona->id,
                    'quantidade_questoes' => 2,
                ],
                [
                    'disciplina_id' => $disciplinas->firstWhere('codigo', 'BKD')->id,
                    'professor_id' => $professor->id,
                    'quantidade_questoes' => 2,
                ],
            ],
            quantidadeAlternativas: 5,
            prazo: now()->subDays(3),
            bimestre: Bimestre::Terceiro,
            titulo: 'Recuperação',
        ));
    }

    /** O seeder roda mais de uma vez; cada peça se guarda pelo título. */
    protected function seNaoExistir(string $titulo, callable $criar): void
    {
        if (SolicitacaoProva::query()->where('titulo', $titulo)->doesntExist()) {
            $criar();
        }
    }

    /**
     * Um curso no Eixo de Gestão, com turma, alunos e um professor.
     *
     * Sem ele o segundo Eixo fica vazio, e conferir na tela que o
     * escopo separa mesmo os dois exige inventar dados na hora.
     */
    protected function cursoDeGestao(Eixo $eixo, User $admin, User $paeet): void
    {
        $curso = Curso::query()->firstOrCreate(
            ['eixo_id' => $eixo->id, 'codigo' => 'ADM'],
            ['nome' => 'Administração', 'duracao_anos' => 2]
        );

        $disciplinas = collect([
            ['Contabilidade Básica', 'CTB', 1, 80],
            ['Matemática Financeira', 'MTF', 1, 60],
            ['Gestão de Pessoas', 'GPE', 1, 60],
            ['Marketing', 'MKT', 2, 60],
        ])->map(fn (array $dados) => Disciplina::query()->firstOrCreate(
            ['curso_id' => $curso->id, 'codigo' => $dados[1]],
            ['nome' => $dados[0], 'periodo' => $dados[2], 'carga_horaria' => $dados[3]]
        ));

        $grade = app(PublicarVersaoDeGradeAction::class)->garantirVigente($curso, $admin);

        $turma = Turma::query()->firstOrCreate(
            ['curso_id' => $curso->id, 'nome' => '1 B', 'periodo_letivo' => (string) now()->format('Y')],
            ['grade_curricular_id' => $grade->id, 'periodo' => 1]
        );

        if ($turma->alunos()->doesntExist()) {
            foreach (range(1, 5) as $indice) {
                Aluno::query()->create([
                    'turma_id' => $turma->id,
                    'nome' => fake('pt_BR')->name(),
                    'ra' => now()->format('Y').'9'.str_pad((string) $indice, 3, '0', STR_PAD_LEFT),
                ]);
            }
        }

        if ($turma->historicos()->doesntExist()) {
            app(RegistrarHistoricoDeTurma::class)->executar(
                turma: $turma,
                evento: EventoHistorico::TurmaCriada,
                autor: $admin,
            );
        }

        $professor = $this->usuario('contabilidade@paeet.local', 'Prof. Adriana Nunes', PerfilUsuario::Professor, $paeet);
        $professor->eixos()->syncWithoutDetaching([$eixo->id]);

        foreach (['CTB', 'MTF'] as $codigo) {
            $professor->vinculosDocentes()->firstOrCreate(
                ['disciplina_id' => $disciplinas->firstWhere('codigo', $codigo)->id, 'turma_id' => null],
                ['ativo' => true]
            );
        }

        $this->cicloDeAvaliacao(
            admin: $admin,
            modelo: ModeloProva::query()->where('eixo_id', $eixo->id)->sole(),
            turma: $turma,
            bimestre: Bimestre::Primeiro,
            partes: [
                ['disciplina' => $disciplinas->firstWhere('codigo', 'CTB'), 'professor' => $professor, 'questoes' => 4],
                ['disciplina' => $disciplinas->firstWhere('codigo', 'MTF'), 'professor' => $professor, 'questoes' => 3],
            ],
        );
    }

    /**
     * Um bimestre inteiro: a coordenação pede, os professores
     * respondem, ela aprova, monta a prova e importa os resultados.
     *
     * É o mesmo caminho que um usuário percorre na tela, feito pelas
     * mesmas Actions — nada aqui escreve no banco por fora das regras.
     *
     * @param  array<int, array{disciplina: Disciplina, professor: User, questoes: int}>  $partes
     */
    protected function cicloDeAvaliacao(
        User $admin,
        ModeloProva $modelo,
        Turma $turma,
        Bimestre $bimestre,
        array $partes,
    ): void {
        $titulo = "Avaliação do {$bimestre->value}º bimestre";

        if (Prova::query()->where('turma_id', $turma->id)->where('titulo', $titulo)->exists()) {
            return;
        }

        /*
         * O ciclo inteiro acontece na época dele. Sem viajar no tempo,
         * um prazo no passado somado a um envio agora marcaria toda
         * solicitação antiga como "Entregue em atraso" — e o selo
         * vermelho, que deveria apontar um caso, apareceria em todos.
         */
        Carbon::setTestNow(now()->subMonths(2 * (4 - $bimestre->value)));

        try {
            $solicitacao = app(CriarSolicitacaoAction::class)->executar(
                autor: $admin,
                turma: $turma,
                partes: array_map(fn (array $parte) => [
                    'disciplina_id' => $parte['disciplina']->id,
                    'professor_id' => $parte['professor']->id,
                    'quantidade_questoes' => $parte['questoes'],
                ], $partes),
                quantidadeAlternativas: 4,
                prazo: now()->addWeek(),
                bimestre: $bimestre,
                titulo: $titulo,
            );

            $this->responder($solicitacao);
            $this->aprovar($solicitacao, $admin);

            $prova = app(MontarProvaAction::class)->executar(
                autor: $admin,
                turma: $turma,
                modelo: $modelo,
                titulo: $titulo,
                bimestre: $bimestre,
                dataAplicacao: now()->addWeeks(2),
                // Só as deste bimestre: a montagem juntaria também as
                // aprovadas dos anteriores, que já viraram prova.
                questoesEscolhidas: $solicitacao->questoes()->pluck('id')->all(),
                configuracao: ['colunas' => 2, 'mostrar_pesos' => false],
            );

            $prova->update(['status' => StatusProva::Aplicada]);

            $this->importarResultados($prova, $admin, $bimestre);
        } finally {
            Carbon::setTestNow();
        }
    }

    /** Os professores preenchem e entregam cada parte. */
    protected function responder(SolicitacaoProva $solicitacao): void
    {
        $salvar = app(SalvarQuestaoAction::class);
        $enviar = app(EnviarParteAction::class);

        foreach ($solicitacao->partes()->with('professor')->orderBy('ordem')->get() as $parte) {
            foreach ($parte->questoes()->orderBy('ordem')->get() as $questao) {
                $salvar->executar(
                    questao: $questao,
                    autor: $parte->professor,
                    enunciado: $this->enunciado($parte, $questao),
                    alternativas: $this->alternativas($solicitacao->quantidade_alternativas),
                    peso: $questao->ordem === 1 ? 1.5 : 1,
                    habilidade: $this->habilidade($parte, $questao),
                );
            }

            $enviar->executar($parte->refresh(), $parte->professor);
        }

        // Uma questão com bloco de código, para a folha mostrar o
        // tratamento de trecho em monoespaçada.
        $comCodigo = $solicitacao->questoes()->orderBy('id')->first();

        QuestaoBloco::query()->firstOrCreate(
            ['questao_id' => $comCodigo->id, 'ordem' => 1],
            [
                'tipo' => 'codigo',
                'linguagem' => 'python',
                'conteudo' => "for i in range(1, 6):\n    print(i * i)",
            ]
        );
    }

    protected function aprovar(SolicitacaoProva $solicitacao, User $admin): void
    {
        $analisar = app(AnalisarQuestaoAction::class);

        foreach ($solicitacao->questoes()->get() as $questao) {
            $analisar->aprovar($questao, $admin);
        }
    }

    /**
     * Resultados de cada aluno.
     *
     * O acerto é sorteado com semente fixa, e a turma melhora um pouco
     * a cada bimestre: sem isso o gráfico de evolução seria uma reta e
     * não mostraria nada.
     */
    protected function importarResultados(Prova $prova, User $admin, Bimestre $bimestre): void
    {
        $alunos = $prova->turma->alunos()->orderBy('nome')->get();
        $questoes = $prova->questoes()->orderBy('numero')->get();

        if ($alunos->isEmpty() || $questoes->isEmpty()) {
            return;
        }

        $importacao = Importacao::query()->create([
            'prova_id' => $prova->id,
            'usuario_id' => $admin->id,
            'arquivo' => "importacoes/demonstracao-{$bimestre->value}.xlsx",
            'nome_original' => "resultados-{$bimestre->value}o-bimestre.xlsx",
            'hash' => hash('sha256', 'demonstracao-'.$prova->id),
            'status' => StatusImportacao::Confirmada,
            'total_linhas' => $alunos->count(),
            'total_erros' => 0,
            'confirmada_em' => now(),
        ]);

        $calcular = app(CalcularNotasAction::class);
        $sorteio = mt_rand(...);

        mt_srand(($bimestre->value * 100) + $prova->id);

        foreach ($alunos->values() as $posicao => $aluno) {
            $resultado = ResultadoAluno::query()->create([
                'prova_id' => $prova->id,
                'aluno_id' => $aluno->id,
                'importacao_id' => $importacao->id,
            ]);

            foreach ($questoes as $questao) {
                // Cada bimestre acerta ~8 pontos percentuais a mais, e
                // os alunos se espalham à volta dessa média.
                $chance = 45 + ($bimestre->value * 8) - ($posicao * 4);

                $acertou = $sorteio(1, 100) <= max(10, min(95, $chance));

                $resultado->respostas()->create([
                    'prova_questao_id' => $questao->id,
                    'alternativa_marcada' => null,
                    'acertou' => $acertou,
                    'peso' => $questao->peso,
                    'pontuacao' => $acertou ? $questao->peso : 0,
                ]);
            }

            $calcular->executar($resultado->refresh());
        }

        $this->command?->info("Bimestre {$bimestre->value}: prova montada e resultados importados.");
    }

    /**
     * Habilidades de demonstração, repetidas de propósito: a análise só
     * tem o que mostrar quando duas questões avaliam a mesma coisa.
     */
    protected function habilidade(SolicitacaoParte $parte, Questao $questao): string
    {
        $porDisciplina = [
            'LOG' => ['Interpretar estruturas de repetição', 'Aplicar condicionais', 'Depurar um algoritmo'],
            'RED' => ['Identificar camadas do modelo OSI', 'Calcular endereçamento IP'],
            'BKD' => ['Modelar entidades e relações', 'Escrever consultas com junção'],
            'FRT' => ['Estruturar um documento semântico', 'Aplicar estilos responsivos'],
        ];

        $disciplina = $parte->loadMissing('disciplina')->disciplina;
        $lista = $porDisciplina[$disciplina->codigo] ?? ['Habilidade de '.$disciplina->nome];

        return $lista[($questao->ordem - 1) % count($lista)];
    }

    protected function enunciado(SolicitacaoParte $parte, Questao $questao): string
    {
        return sprintf(
            '(%s) Questão %d — enunciado de demonstração.',
            $parte->loadMissing('disciplina')->disciplina->nome,
            $questao->ordem,
        );
    }

    /** @return array<int, array{letra: string, texto: string, correta: bool}> */
    protected function alternativas(int $quantidade): array
    {
        return collect(range(0, $quantidade - 1))
            ->map(fn (int $indice) => [
                'letra' => chr(65 + $indice),
                'texto' => 'Alternativa '.chr(65 + $indice),
                'correta' => $indice === 0,
            ])
            ->all();
    }

    protected function usuario(string $email, string $nome, PerfilUsuario $perfil, ?User $autor = null): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'nome' => $nome,
                'password' => Hash::make('senha-forte-123'),
                // Contas de demonstração entram direto, sem a tela de
                // primeira senha atrapalhar quem está só conhecendo.
                'senha_definida_em' => now(),
                'perfil' => $perfil,
                'ativo' => true,
                'criado_por' => $autor?->id,
            ]
        );
    }
}
