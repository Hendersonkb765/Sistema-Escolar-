<?php

namespace Database\Seeders;

use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Actions\Academico\RegistrarHistoricoDeTurma;
use App\Enums\EventoHistorico;
use App\Enums\PerfilUsuario;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Seeder;
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
        $professor->vinculosDocentes()->firstOrCreate(
            ['disciplina_id' => $disciplinas->first()->id, 'turma_id' => null],
            ['ativo' => true]
        );

        // Um PAEET que também leciona: mesma conta, vínculo docente à parte.
        $paeetTecnologia->vinculosDocentes()->firstOrCreate(
            ['disciplina_id' => $disciplinas->last()->id, 'turma_id' => null],
            ['ativo' => true]
        );

        $this->command?->info('Demonstração pronta. Senha de todos: senha-forte-123');
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
