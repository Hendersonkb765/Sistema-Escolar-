<?php

namespace Database\Seeders;

use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Actions\Academico\RegistrarHistoricoDeTurma;
use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Enums\EventoHistorico;
use App\Enums\PerfilUsuario;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
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

        $this->solicitacoes($curso, $admin, $professor, $paeetTecnologia, $disciplinas);

        $this->command?->info('Demonstração pronta. Senha de todos: senha-forte-123');
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
            titulo: 'Recuperação',
        );
    }

    protected function usuario(string $email, string $nome, PerfilUsuario $perfil, ?User $autor = null): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'nome' => $nome,
                'password' => Hash::make('senha-forte-123'),
                'perfil' => $perfil,
                'ativo' => true,
                'criado_por' => $autor?->id,
            ]
        );
    }
}
