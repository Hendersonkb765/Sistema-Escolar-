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
            ['Back-end', 'BKD', 2, 80],
            ['Front-end', 'FRT', 2, 80],
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
                        'matricula' => now()->format('Y').$periodo.str_pad((string) $indice, 3, '0', STR_PAD_LEFT),
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

        // Um PAEET que também leciona: mesma conta, vínculo docente à parte.
        foreach (['RED', 'FRT'] as $codigo) {
            $paeetTecnologia->vinculosDocentes()->firstOrCreate(
                ['disciplina_id' => $disciplinas->firstWhere('codigo', $codigo)->id, 'turma_id' => null],
                ['ativo' => true]
            );
        }

        $modeloTecnologia = $this->modeloDeProva($tecnologia, $admin);
        $this->modeloDeProva($gestao, $admin);

        $this->solicitacoes($curso, $admin, $professor, $paeetTecnologia, $disciplinas);
        $this->provaMontada($admin, $modeloTecnologia);
        $this->resultadosImportados($admin);

        $this->command?->info('Demonstração pronta. Senha de todos: senha-forte-123');
    }

    /** Sem ao menos um modelo não há como montar prova; cada Eixo tem o seu. */
    protected function modeloDeProva(Eixo $eixo, User $autor): ModeloProva
    {
        return ModeloProva::query()->firstOrCreate(
            ['eixo_id' => $eixo->id, 'nome' => 'Padrão — '.$eixo->nome],
            [
                'instituicao' => config('app.name'),
                'nome_avaliacao' => 'Avaliação Bimestral',
                'cabecalho' => 'Eixo de '.$eixo->nome,
                'instrucoes' => 'Leia cada questão com atenção e marque apenas uma alternativa. '
                    .'Não é permitido consulta.',
                'rodape' => 'Boa prova!',
                'campos_identificacao' => ['aluno', 'matricula', 'turma', 'data'],
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
    protected function solicitacoes(
        Curso $curso,
        User $admin,
        User $professor,
        User $paeetQueLeciona,
        $disciplinas,
    ): void {
        if (SolicitacaoProva::query()->exists()) {
            return;
        }

        $turmaDoPrimeiro = Turma::query()->where('curso_id', $curso->id)->where('periodo', 1)->first();
        $turmaDoSegundo = Turma::query()->where('curso_id', $curso->id)->where('periodo', 2)->first();

        $criar = app(CriarSolicitacaoAction::class);

        // Prova do 1º período: Lógica com um professor, Redes com outro.
        $criar->executar(
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
            bimestre: Bimestre::Segundo,
            titulo: 'Avaliação do 2º bimestre',
            observacoes: 'Priorize conteúdo do segundo bimestre.',
        );

        // Prova do 2º período, com prazo já vencido.
        $criar->executar(
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
        );
    }

    /**
     * Fecha o ciclo da primeira solicitação — professores respondem, a
     * coordenação aprova — e monta a prova, para que a montagem, a
     * pré-visualização e os downloads tenham o que mostrar.
     */
    protected function provaMontada(User $admin, ModeloProva $modelo): void
    {
        if (Prova::query()->exists()) {
            return;
        }

        $solicitacao = SolicitacaoProva::query()
            ->where('titulo', 'Avaliação do 2º bimestre')
            ->with('turma')
            ->first();

        if ($solicitacao === null) {
            return;
        }

        $salvar = app(SalvarQuestaoAction::class);
        $enviar = app(EnviarParteAction::class);
        $analisar = app(AnalisarQuestaoAction::class);

        $partes = $solicitacao->partes()->with('professor')->orderBy('ordem')->get();

        foreach ($partes as $parte) {
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

        foreach ($solicitacao->questoes()->get() as $questao) {
            $analisar->aprovar($questao, $admin);
        }

        app(MontarProvaAction::class)->executar(
            autor: $admin,
            turma: $solicitacao->turma,
            modelo: $modelo,
            titulo: 'Avaliação do 2º bimestre',
            dataAplicacao: now()->addWeek(),
            configuracao: ['colunas' => 2, 'gabarito' => true, 'mostrar_pesos' => false],
        );
    }

    /**
     * Uma importação já confirmada, para as telas de resultado abrirem
     * com o que mostrar. Os acertos são sorteados por aluno, mas com
     * semente fixa: a demonstração é a mesma a cada `migrate:fresh`.
     */
    protected function resultadosImportados(User $admin): void
    {
        $prova = Prova::query()->with('turma')->first();

        if ($prova === null || ResultadoAluno::query()->exists()) {
            return;
        }

        $questoes = $prova->questoes()->orderBy('numero')->get();
        $alunos = $prova->turma->alunos()->orderBy('nome')->get();

        if ($questoes->isEmpty() || $alunos->isEmpty()) {
            return;
        }

        $importacao = Importacao::query()->create([
            'prova_id' => $prova->id,
            'usuario_id' => $admin->id,
            'arquivo' => 'importacoes/demonstracao.xlsx',
            'nome_original' => 'resultados-demonstracao.xlsx',
            'hash' => hash('sha256', 'demonstracao'),
            'status' => StatusImportacao::Confirmada,
            'total_linhas' => $alunos->count(),
            'total_erros' => 0,
            'confirmada_em' => now(),
        ]);

        $calcular = app(CalcularNotasAction::class);

        foreach ($alunos->values() as $posicao => $aluno) {
            $resultado = ResultadoAluno::query()->create([
                'prova_id' => $prova->id,
                'aluno_id' => $aluno->id,
                'importacao_id' => $importacao->id,
            ]);

            foreach ($questoes as $indice => $questao) {
                // Padrão fixo: o primeiro aluno acerta tudo, o último
                // quase nada, e os do meio variam.
                $acertou = ($posicao + $indice) % ($posicao + 2) !== 0;

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

        $this->command?->info('Resultados de demonstração importados.');
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
