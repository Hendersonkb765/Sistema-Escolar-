<?php

/*
 * Critério de aceite 2:
 * um PAEET do Eixo "Tecnologia" recebe 403 ao tocar qualquer recurso do
 * Eixo "Administração" — inclusive adivinhando o id na URL (IDOR).
 */

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\Turma;

beforeEach(function () {
    $this->tecnologia = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->administracao = Eixo::factory()->create(['nome' => 'Administração', 'codigo' => 'ADM']);

    $this->paeetTecnologia = paeet($this->tecnologia);

    $this->cursoTecnologia = Curso::factory()->noEixo($this->tecnologia)->create(['nome' => 'Desenvolvimento de Sistemas']);
    $this->cursoAdministracao = Curso::factory()->noEixo($this->administracao)->create(['nome' => 'Logística']);
});

it('abre um recurso do próprio eixo', function () {
    $this->actingAs($this->paeetTecnologia)
        ->get(route('cursos.show', $this->cursoTecnologia))
        ->assertOk()
        ->assertSee('Desenvolvimento de Sistemas');
});

it('responde 403 ao acessar por id um curso de outro eixo', function () {
    $this->actingAs($this->paeetTecnologia)
        ->get(route('cursos.show', $this->cursoAdministracao))
        ->assertForbidden();
});

it('responde 403 na URL montada à mão com o id de outro eixo (IDOR)', function () {
    $this->actingAs($this->paeetTecnologia)
        ->get('/cursos/'.$this->cursoAdministracao->id)
        ->assertForbidden();
});

it('responde 403 ao editar um eixo fora do escopo', function () {
    $this->actingAs(paeetAdmin($this->tecnologia))
        ->get(route('eixos.editar', $this->administracao))
        ->assertForbidden();
});

it('não vaza recursos de outro eixo na listagem de cursos', function () {
    $resposta = $this->actingAs($this->paeetTecnologia)->get(route('cursos.index'));

    $resposta->assertOk()
        ->assertSee('Desenvolvimento de Sistemas')
        ->assertDontSee('Logística');
});

it('não vaza eixos de fora do escopo na listagem de eixos', function () {
    // Compara pelo código do eixo: a palavra "Administração" também é o
    // título de um grupo da navegação lateral.
    $this->actingAs($this->paeetTecnologia)
        ->get(route('eixos.index'))
        ->assertOk()
        ->assertSee('TEC')
        ->assertDontSee('ADM');
});

it('filtra pelo escopo toda consulta que usa visivelPara', function () {
    $consulta = fn (string $model) => $model::query()->visivelPara($this->paeetTecnologia)->pluck('id');

    $gradeTecnologia = GradeCurricular::factory()->doCurso($this->cursoTecnologia)->create();
    $gradeAdministracao = GradeCurricular::factory()->doCurso($this->cursoAdministracao)->create();

    $turmaTecnologia = Turma::factory()->doCurso($this->cursoTecnologia, $gradeTecnologia)->create();
    $turmaAdministracao = Turma::factory()->doCurso($this->cursoAdministracao, $gradeAdministracao)->create(['identificacao' => '1LG']);

    $alunoTecnologia = Aluno::factory()->naTurma($turmaTecnologia)->create();
    $alunoAdministracao = Aluno::factory()->naTurma($turmaAdministracao)->create();

    $disciplinaTecnologia = Disciplina::factory()->noEixo($this->tecnologia)->create();
    $disciplinaAdministracao = Disciplina::factory()->noEixo($this->administracao)->create();

    expect($consulta(Curso::class)->all())->toBe([$this->cursoTecnologia->id])
        ->and($consulta(GradeCurricular::class)->all())->toBe([$gradeTecnologia->id])
        ->and($consulta(Turma::class)->all())->toBe([$turmaTecnologia->id])
        ->and($consulta(Aluno::class)->all())->toBe([$alunoTecnologia->id])
        ->and($consulta(Disciplina::class)->all())->toBe([$disciplinaTecnologia->id])
        ->and($consulta(Eixo::class)->all())->toBe([$this->tecnologia->id]);

    // Os registros do outro eixo existem — apenas não são visíveis.
    expect(Curso::query()->count())->toBe(2)
        ->and($gradeAdministracao->exists)->toBeTrue()
        ->and($alunoAdministracao->exists)->toBeTrue()
        ->and($disciplinaAdministracao->exists)->toBeTrue();
});

it('nega a policy para cada tipo de recurso de outro eixo', function () {
    $gradeAdministracao = GradeCurricular::factory()->doCurso($this->cursoAdministracao)->create();
    $turmaAdministracao = Turma::factory()->doCurso($this->cursoAdministracao, $gradeAdministracao)->create(['identificacao' => '1LG']);
    $alunoAdministracao = Aluno::factory()->naTurma($turmaAdministracao)->create();
    $disciplinaAdministracao = Disciplina::factory()->noEixo($this->administracao)->create();

    $usuario = $this->paeetTecnologia;

    expect($usuario->can('view', $this->cursoAdministracao))->toBeFalse()
        ->and($usuario->can('update', $this->cursoAdministracao))->toBeFalse()
        ->and($usuario->can('delete', $this->cursoAdministracao))->toBeFalse()
        ->and($usuario->can('view', $gradeAdministracao))->toBeFalse()
        ->and($usuario->can('view', $turmaAdministracao))->toBeFalse()
        ->and($usuario->can('view', $alunoAdministracao))->toBeFalse()
        ->and($usuario->can('view', $disciplinaAdministracao))->toBeFalse()
        ->and($usuario->can('view', $this->administracao))->toBeFalse();
});

it('permite a policy para os mesmos recursos dentro do escopo', function () {
    $gradeTecnologia = GradeCurricular::factory()->doCurso($this->cursoTecnologia)->create();
    $turmaTecnologia = Turma::factory()->doCurso($this->cursoTecnologia, $gradeTecnologia)->create();
    $alunoTecnologia = Aluno::factory()->naTurma($turmaTecnologia)->create();

    $usuario = $this->paeetTecnologia;

    expect($usuario->can('view', $this->cursoTecnologia))->toBeTrue()
        ->and($usuario->can('update', $this->cursoTecnologia))->toBeTrue()
        ->and($usuario->can('view', $gradeTecnologia))->toBeTrue()
        ->and($usuario->can('view', $turmaTecnologia))->toBeTrue()
        ->and($usuario->can('view', $alunoTecnologia))->toBeTrue();
});

it('um PAEET Admin também é limitado pelos eixos vinculados', function () {
    $admin = paeetAdmin($this->tecnologia);

    expect($admin->can('view', $this->cursoTecnologia))->toBeTrue()
        ->and($admin->can('view', $this->cursoAdministracao))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('cursos.show', $this->cursoAdministracao))
        ->assertForbidden();
});

it('não enxerga nada quando a conta não tem eixo vinculado', function () {
    $semEixo = paeet();

    expect(Curso::query()->visivelPara($semEixo)->count())->toBe(0);

    $this->actingAs($semEixo)
        ->get(route('cursos.show', $this->cursoTecnologia))
        ->assertForbidden();
});
