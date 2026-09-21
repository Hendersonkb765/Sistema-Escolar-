<?php

/*
 * As rotas novas do milestone 2 herdam as mesmas barreiras do milestone 1:
 * professor não entra, e PAEET de outro Eixo recebe 403 por id (IDOR).
 */

use App\Actions\Academico\MoverAlunoDeTurmaAction;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->tecnologia = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->administracao = Eixo::factory()->create(['nome' => 'Gestão', 'codigo' => 'ADM']);

    $this->paeetTecnologia = paeet($this->tecnologia);

    $alheio = cursoComGrade($this->administracao, [1 => ['Contabilidade']]);
    $this->cursoAlheio = $alheio['curso'];
    $this->gradeAlheia = $alheio['grade'];
    $this->turmaAlheia = Turma::factory()->doCurso($this->cursoAlheio, $this->gradeAlheia)->create();
    $this->alunoAlheio = Aluno::factory()->naTurma($this->turmaAlheia)->create();
    $this->disciplinaAlheia = Disciplina::factory()->noEixo($this->administracao)->create();
});

it('responde 403 ao professor em toda rota de escrita do milestone 2', function (string $rota) {
    $this->actingAs(professor($this->tecnologia))->get(route($rota))->assertForbidden();
})->with([
    'cursos.criar',
    'disciplinas.criar',
    'grades.criar',
    'turmas.criar',
    'alunos.criar',
]);

it('nega ao professor criar qualquer entidade acadêmica', function () {
    $professor = professor($this->tecnologia);

    expect($professor->can('create', Curso::class))->toBeFalse()
        ->and($professor->can('create', Disciplina::class))->toBeFalse()
        ->and($professor->can('create', GradeCurricular::class))->toBeFalse()
        ->and($professor->can('create', Turma::class))->toBeFalse()
        ->and($professor->can('create', Aluno::class))->toBeFalse();
});

it('responde 403 em recurso de outro eixo acessado por id', function (string $rota, string $propriedade) {
    $registro = $this->{$propriedade};

    $this->actingAs($this->paeetTecnologia)
        ->get(route($rota, $registro))
        ->assertForbidden();
})->with([
    ['cursos.editar', 'cursoAlheio'],
    ['cursos.show', 'cursoAlheio'],
    ['disciplinas.editar', 'disciplinaAlheia'],
    ['grades.show', 'gradeAlheia'],
    ['grades.editar', 'gradeAlheia'],
    ['turmas.show', 'turmaAlheia'],
    ['turmas.editar', 'turmaAlheia'],
    ['alunos.editar', 'alunoAlheio'],
]);

it('não vaza registros de outro eixo nas listagens novas', function () {
    $proprio = cursoComGrade($this->tecnologia, [1 => ['Lógica de Programação']]);
    $turmaPropria = Turma::factory()->doCurso($proprio['curso'], $proprio['grade'])->create([
        'identificacao' => '1DS',
    ]);
    Aluno::factory()->naTurma($turmaPropria)->create(['nome' => 'Aluna Visível']);
    $this->alunoAlheio->update(['nome' => 'Aluno Oculto']);
    $this->turmaAlheia->update(['identificacao' => '1CT']);
    $this->disciplinaAlheia->update(['nome' => 'Contabilidade Geral']);

    $como = fn (string $rota) => $this->actingAs($this->paeetTecnologia)->get(route($rota));

    $como('disciplinas.index')->assertOk()
        ->assertSee('Lógica de Programação')->assertDontSee('Contabilidade Geral');

    $como('turmas.index')->assertOk()
        ->assertSee('1DS')->assertDontSee('1CT');

    $como('alunos.index')->assertOk()
        ->assertSee('Aluna Visível')->assertDontSee('Aluno Oculto');

    $como('grades.index')->assertOk()
        ->assertDontSee($this->cursoAlheio->nome);
});

it('impede mover um aluno para turma fora do escopo', function () {
    $proprio = cursoComGrade($this->tecnologia, [1 => ['Lógica']]);
    $turmaPropria = Turma::factory()->doCurso($proprio['curso'], $proprio['grade'])->create();
    $aluno = Aluno::factory()->naTurma($turmaPropria)->create();

    expect(fn () => app(MoverAlunoDeTurmaAction::class)->executar(
        aluno: $aluno,
        destino: $this->turmaAlheia,
        autor: $this->paeetTecnologia,
    ))->toThrow(AuthorizationException::class);

    expect($aluno->refresh()->turma_id)->toBe($turmaPropria->id);
});
