<?php

namespace Database\Seeders;

use App\Actions\Academico\RegistrarHistoricoDeTurma;
use App\Enums\EventoHistorico;
use App\Enums\PerfilUsuario;
use App\Enums\StatusGrade;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
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

        $tecnologia = Eixo::query()->firstOrCreate(
            ['codigo' => 'TEC'],
            ['nome' => 'Tecnologia da Informação']
        );

        $administracao = Eixo::query()->firstOrCreate(
            ['codigo' => 'ADM'],
            ['nome' => 'Gestão e Negócios']
        );

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@paeet.local'],
            [
                'nome' => 'Coordenação PAEET',
                'password' => Hash::make('senha-forte-123'),
                'perfil' => PerfilUsuario::PaeetAdmin,
                'ativo' => true,
            ]
        );
        $admin->eixos()->syncWithoutDetaching([$tecnologia->id, $administracao->id]);

        $paeetTecnologia = User::query()->firstOrCreate(
            ['email' => 'paeet.tec@paeet.local'],
            [
                'nome' => 'Paula Enes (PAEET Tecnologia)',
                'password' => Hash::make('senha-forte-123'),
                'perfil' => PerfilUsuario::Paeet,
                'ativo' => true,
                'criado_por' => $admin->id,
            ]
        );
        $paeetTecnologia->eixos()->syncWithoutDetaching([$tecnologia->id]);

        $paeetAdministracao = User::query()->firstOrCreate(
            ['email' => 'paeet.adm@paeet.local'],
            [
                'nome' => 'Adriano Mota (PAEET Gestão)',
                'password' => Hash::make('senha-forte-123'),
                'perfil' => PerfilUsuario::Paeet,
                'ativo' => true,
                'criado_por' => $admin->id,
            ]
        );
        $paeetAdministracao->eixos()->syncWithoutDetaching([$administracao->id]);

        $curso = Curso::query()->firstOrCreate(
            ['eixo_id' => $tecnologia->id, 'codigo' => 'DS'],
            ['nome' => 'Desenvolvimento de Sistemas', 'duracao_anos' => 3]
        );

        $disciplinas = collect([
            'Lógica de Programação' => 'LOG',
            'Processos de Desenvolvimento' => 'PDS',
            'Banco de Dados' => 'BDD',
        ])->map(fn (string $codigo, string $nome) => Disciplina::query()->firstOrCreate(
            ['eixo_id' => $tecnologia->id, 'codigo' => $codigo],
            ['nome' => $nome]
        ));

        $grade = GradeCurricular::query()->firstOrCreate(
            ['curso_id' => $curso->id, 'versao' => 1],
            [
                'ano_vigencia' => (int) now()->format('Y'),
                'status' => StatusGrade::Vigente,
                'criado_por' => $admin->id,
            ]
        );

        foreach ($disciplinas->values() as $indice => $disciplina) {
            $grade->disciplinas()->firstOrCreate(
                ['disciplina_id' => $disciplina->id, 'ano_curso' => $indice + 1],
                ['carga_horaria' => 80]
            );
        }

        foreach ([1 => '1DS', 2 => '2DS', 3 => '3DS'] as $ano => $identificacao) {
            $turma = Turma::query()->firstOrCreate(
                [
                    'curso_id' => $curso->id,
                    'identificacao' => $identificacao,
                    'periodo_letivo' => (string) now()->format('Y'),
                ],
                [
                    'grade_curricular_id' => $grade->id,
                    'ano_curso' => $ano,
                ]
            );

            if ($turma->alunos()->doesntExist()) {
                foreach (range(1, 6) as $indice) {
                    Aluno::query()->create([
                        'turma_id' => $turma->id,
                        'nome' => fake('pt_BR')->name(),
                        'matricula' => now()->format('Y').$ano.str_pad((string) $indice, 3, '0', STR_PAD_LEFT),
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

        $professor = User::query()->firstOrCreate(
            ['email' => 'professor@paeet.local'],
            [
                'nome' => 'Prof. Renato Lima',
                'password' => Hash::make('senha-forte-123'),
                'perfil' => PerfilUsuario::Professor,
                'ativo' => true,
                'criado_por' => $paeetTecnologia->id,
            ]
        );
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
}
