<?php

use App\Enums\PerfilUsuario;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => proibirLazyLoading())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Proteção contra lazy loading
|--------------------------------------------------------------------------
*/

/**
 * Proíbe lazy loading em TODO model hidratado, inclusive nos que vêm
 * sozinhos numa consulta.
 *
 * O `Model::preventLazyLoading()` do framework só marca a instância
 * quando a consulta devolve mais de um registro (Builder::hydrate), o que
 * faz um teste com uma linha só passar enquanto a tela de quem tem duas
 * quebra. Marcando no evento `retrieved`, qualquer relação esquecida no
 * eager load aparece já no primeiro registro.
 */
function proibirLazyLoading(): void
{
    Model::preventLazyLoading();

    Event::listen('eloquent.retrieved: *', function (string $evento, array $models) {
        foreach ($models as $model) {
            if ($model instanceof Model) {
                $model->preventsLazyLoading = true;
            }
        }
    });
}

/*
|--------------------------------------------------------------------------
| Helpers de domínio
|--------------------------------------------------------------------------
*/

/** Cria um usuário de gestão já vinculado aos Eixos indicados. */
function gestor(PerfilUsuario $perfil, Eixo ...$eixos): User
{
    $usuario = User::factory()->create(['perfil' => $perfil]);

    if ($eixos !== []) {
        $usuario->eixos()->sync(collect($eixos)->map->getKey()->all());
        $usuario->esquecerEscopo();
    }

    return $usuario;
}

function paeetAdmin(Eixo ...$eixos): User
{
    return gestor(PerfilUsuario::PaeetAdmin, ...$eixos);
}

function paeet(Eixo ...$eixos): User
{
    return gestor(PerfilUsuario::Paeet, ...$eixos);
}

function professor(Eixo ...$eixos): User
{
    return gestor(PerfilUsuario::Professor, ...$eixos);
}

/**
 * Monta um curso com grade vigente e disciplinas posicionadas por ano.
 *
 * @param  array<int, array<int, string>>  $porAno  ano => [nomes das disciplinas]
 * @return array{curso: Curso, grade: GradeCurricular, disciplinas: Collection<string, Disciplina>}
 */
function cursoComGrade(Eixo $eixo, array $porAno, int $duracaoAnos = 3, int $versao = 1): array
{
    $curso = Curso::factory()->noEixo($eixo)->create([
        'duracao_anos' => $duracaoAnos,
    ]);

    $grade = GradeCurricular::factory()->doCurso($curso)->versao($versao)->create();

    $disciplinas = collect();

    foreach ($porAno as $ano => $nomes) {
        foreach ($nomes as $nome) {
            $disciplina = $disciplinas->get($nome) ?? Disciplina::factory()
                ->noEixo($eixo)
                ->create(['nome' => $nome]);

            $disciplinas->put($nome, $disciplina);

            $grade->disciplinas()->create([
                'disciplina_id' => $disciplina->getKey(),
                'ano_curso' => $ano,
                'carga_horaria' => 80,
            ]);
        }
    }

    return [
        'curso' => $curso,
        'grade' => $grade->refresh(),
        'disciplinas' => $disciplinas,
    ];
}
