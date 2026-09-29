<?php

/*
 * CRUD da estrutura acadêmica: o que Henderson não conseguia fazer antes
 * do milestone 2 — cadastrar curso, disciplina, grade, turma e aluno.
 */

use App\Enums\EventoHistorico;
use App\Enums\StatusGrade;
use App\Livewire\Alunos\FormularioAluno;
use App\Livewire\Cursos\DetalheCurso;
use App\Livewire\Cursos\FormularioCurso;
use App\Livewire\Disciplinas\FormularioDisciplina;
use App\Livewire\Turmas\FormularioTurma;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\Turma;
use App\Models\TurmaHistorico;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
});

it('cadastra um curso', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioCurso::class)
        ->set('eixo_id', $this->eixo->id)
        ->set('nome', 'Desenvolvimento de Sistemas')
        ->set('codigo', 'DS')
        ->set('duracao_anos', 3)
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertRedirect(route('cursos.index'));

    $curso = Curso::query()->where('codigo', 'DS')->first();

    expect($curso)->not->toBeNull()
        ->and($curso->nome)->toBe('Desenvolvimento de Sistemas')
        ->and($curso->eixo_id)->toBe($this->eixo->id)
        ->and($curso->duracao_anos)->toBe(3);
});

it('recusa código de curso repetido no mesmo eixo', function () {
    Curso::factory()->noEixo($this->eixo)->create(['codigo' => 'DS']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioCurso::class)
        ->set('eixo_id', $this->eixo->id)
        ->set('nome', 'Outro curso')
        ->set('codigo', 'DS')
        ->call('salvar')
        ->assertHasErrors('codigo');
});

it('aceita o mesmo código de curso em eixos diferentes', function () {
    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);
    Curso::factory()->noEixo($outroEixo)->create(['codigo' => 'DS']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioCurso::class)
        ->set('eixo_id', $this->eixo->id)
        ->set('nome', 'Desenvolvimento de Sistemas')
        ->set('codigo', 'DS')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Curso::query()->where('codigo', 'DS')->count())->toBe(2);
});

it('recusa cadastrar curso em eixo fora do escopo', function () {
    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioCurso::class)
        ->set('eixo_id', $outroEixo->id)
        ->set('nome', 'Curso Intruso')
        ->set('codigo', 'INT')
        ->call('salvar')
        ->assertHasErrors('eixo_id');

    expect(Curso::query()->where('codigo', 'INT')->exists())->toBeFalse();
});

it('impede encurtar um curso abaixo do período de suas turmas', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 3]);
    Turma::factory()->doCurso($curso)->create(['periodo' => 3, 'nome' => '3 A']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioCurso::class, ['curso' => $curso])
        ->set('duracao_anos', 2)
        ->call('salvar')
        ->assertHasErrors('duracao_anos');

    expect($curso->refresh()->duracao_anos)->toBe(3);
});

it('cadastra uma disciplina no curso e no período', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 2]);

    Livewire::actingAs($this->paeet)
        ->test(FormularioDisciplina::class)
        ->set('curso_id', $curso->id)
        ->set('nome', 'Lógica de Programação')
        ->set('codigo', 'LOG')
        ->set('periodo', 1)
        ->set('carga_horaria', 80)
        ->call('salvar')
        ->assertHasNoErrors();

    $disciplina = Disciplina::query()->where('codigo', 'LOG')->first();

    expect($disciplina)->not->toBeNull()
        ->and($disciplina->curso_id)->toBe($curso->id)
        ->and($disciplina->periodo)->toBe(1)
        ->and($disciplina->carga_horaria)->toBe(80);
});

it('recusa disciplina em período além da duração do curso', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 2]);

    Livewire::actingAs($this->paeet)
        ->test(FormularioDisciplina::class)
        ->set('curso_id', $curso->id)
        ->set('nome', 'Fora do curso')
        ->set('codigo', 'FOR')
        ->set('periodo', 3)
        ->call('salvar')
        ->assertHasErrors('periodo');

    expect(Disciplina::query()->where('codigo', 'FOR')->exists())->toBeFalse();
});

it('recusa disciplina em curso fora do escopo', function () {
    $cursoAlheio = Curso::factory()->noEixo(Eixo::factory()->create(['codigo' => 'ADM']))->create();

    Livewire::actingAs($this->paeet)
        ->test(FormularioDisciplina::class)
        ->set('curso_id', $cursoAlheio->id)
        ->set('nome', 'Intrusa')
        ->set('codigo', 'INT')
        ->set('periodo', 1)
        ->call('salvar')
        ->assertHasErrors('curso_id');

    expect(Disciplina::query()->where('codigo', 'INT')->exists())->toBeFalse();
});

it('aceita o mesmo código de disciplina em cursos diferentes', function () {
    $a = Curso::factory()->noEixo($this->eixo)->create();
    $b = Curso::factory()->noEixo($this->eixo)->create();

    Disciplina::factory()->doCurso($a)->create(['codigo' => 'LOG']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioDisciplina::class)
        ->set('curso_id', $b->id)
        ->set('nome', 'Lógica de Programação')
        ->set('codigo', 'LOG')
        ->set('periodo', 1)
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Disciplina::query()->where('codigo', 'LOG')->count())->toBe(2);
});

it('recusa código de disciplina repetido no mesmo curso', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create();
    Disciplina::factory()->doCurso($curso)->create(['codigo' => 'LOG']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioDisciplina::class)
        ->set('curso_id', $curso->id)
        ->set('nome', 'Outra Lógica')
        ->set('codigo', 'LOG')
        ->set('periodo', 1)
        ->call('salvar')
        ->assertHasErrors('codigo');
});

it('publica a grade a partir das disciplinas do curso', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 2]);
    Disciplina::factory()->doCurso($curso)->noPeriodo(1)->create(['nome' => 'Lógica']);
    Disciplina::factory()->doCurso($curso)->noPeriodo(2)->create(['nome' => 'Back-end']);

    Livewire::actingAs($this->paeet)
        ->test(DetalheCurso::class, ['curso' => $curso])
        ->call('publicarGrade')
        ->assertHasNoErrors();

    $grade = GradeCurricular::query()->where('curso_id', $curso->id)->first();

    expect($grade)->not->toBeNull()
        ->and($grade->status)->toBe(StatusGrade::Vigente)
        ->and($grade->versao)->toBe(1)
        ->and($grade->disciplinas()->count())->toBe(2)
        ->and($grade->disciplinas()->where('periodo', 2)->count())->toBe(1);
});

it('avisa quando o cadastro divergiu da grade publicada', function () {
    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica'], 2 => ['Back-end']], autor: $this->paeet);

    $montagem['disciplinas']['Back-end']->update(['periodo' => 1]);

    Livewire::actingAs($this->paeet)
        ->test(DetalheCurso::class, ['curso' => $montagem['curso']])
        ->assertSee('Há mudanças ainda não publicadas')
        ->assertSee('2º → 1º período');
});

it('cadastra uma turma congelando a versão da grade', function () {
    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);

    Livewire::actingAs($this->paeet)
        ->test(FormularioTurma::class)
        ->set('curso_id', $montagem['curso']->id)
        ->set('grade_curricular_id', $montagem['grade']->id)
        ->set('periodo', 1)
        ->set('nome', '1 A')
        ->set('periodo_letivo', '2026')
        ->call('salvar')
        ->assertHasNoErrors();

    $turma = Turma::query()->where('nome', '1 A')->first();

    expect($turma->periodo)->toBe(1)
        ->and($turma->grade_curricular_id)->toBe($montagem['grade']->id)
        // Criar a turma já abre o histórico dela.
        ->and(TurmaHistorico::query()->where('turma_id', $turma->id)->count())->toBe(1);
});

it('recusa turma com grade de outro curso', function () {
    $a = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    $b = cursoComGrade($this->eixo, [1 => ['Redes']], autor: $this->paeet);

    Livewire::actingAs($this->paeet)
        ->test(FormularioTurma::class)
        ->set('curso_id', $a['curso']->id)
        ->set('grade_curricular_id', $b['grade']->id)
        ->set('nome', '1 X')
        ->call('salvar')
        ->assertHasErrors('grade_curricular_id');
});

it('matricula um aluno e abre o histórico dele', function () {
    $turma = Turma::factory()
        ->doCurso(Curso::factory()->noEixo($this->eixo)->create())
        ->create(['nome' => '1 A']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioAluno::class)
        ->set('turma_id', $turma->id)
        ->set('nome', 'Marina Alves')
        ->set('ra', '20260001')
        ->call('salvar')
        ->assertHasNoErrors();

    $aluno = Aluno::query()->where('ra', '20260001')->first();

    expect($aluno->turma_id)->toBe($turma->id)
        ->and($aluno->historicos()->count())->toBe(1)
        ->and($aluno->historicos()->first()->evento)->toBe(EventoHistorico::AlunoMatriculado);
});

it('recusa RA repetido na mesma turma', function () {
    $turma = Turma::factory()
        ->doCurso(Curso::factory()->noEixo($this->eixo)->create())
        ->create();

    Aluno::factory()->naTurma($turma)->create(['ra' => '123']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioAluno::class)
        ->set('turma_id', $turma->id)
        ->set('nome', 'Homônimo')
        ->set('ra', '123')
        ->call('salvar')
        ->assertHasErrors('ra');
});

it('grava histórico ao trocar o aluno de turma pelo formulário', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create();
    $origem = Turma::factory()->doCurso($curso)->create(['nome' => '1 A']);
    $destino = Turma::factory()->doCurso($curso)->create(['nome' => '1 B']);

    $aluno = Aluno::factory()->naTurma($origem)->create(['ra' => '999']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioAluno::class, ['aluno' => $aluno])
        ->set('turma_id', $destino->id)
        ->set('motivoMovimentacao', 'Remanejamento interno')
        ->call('salvar')
        ->assertHasNoErrors();

    $aluno->refresh();
    $historico = $aluno->historicos()->latest('id')->first();

    expect($aluno->turma_id)->toBe($destino->id)
        ->and($historico->evento)->toBe(EventoHistorico::AlunoTrocaTurma)
        ->and($historico->turma_anterior_id)->toBe($origem->id)
        ->and($historico->motivo)->toBe('Remanejamento interno');
});

it('oferece o caminho visível para cadastrar um curso', function () {
    // A queixa que originou o milestone 2: a listagem não dava nenhum
    // caminho para criar. O botão e a rota precisam existir para quem pode.
    $this->actingAs($this->paeet)
        ->get(route('cursos.index'))
        ->assertOk()
        ->assertSee('Novo curso')
        ->assertSee(route('cursos.criar'), escape: false);

    $this->actingAs($this->paeet)->get(route('cursos.criar'))->assertOk();
});

it('não oferece o botão de novo curso a quem não pode criar', function () {
    $this->actingAs(professor($this->eixo))
        ->get(route('cursos.index'))
        ->assertForbidden();
});

it('leva do curso recém-criado à disciplina e à turma', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create();

    $this->actingAs($this->paeet)
        ->get(route('cursos.show', $curso))
        ->assertOk()
        ->assertSee('Nova disciplina')
        ->assertSee('Nova turma')
        // Curso sem disciplina ainda não tem grade publicada.
        ->assertSee('Nenhuma grade publicada ainda');
});

it('aceita nomes de turma em formato livre', function (string $nome) {
    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);

    Livewire::actingAs($this->paeet)
        ->test(FormularioTurma::class)
        ->set('curso_id', $montagem['curso']->id)
        ->set('grade_curricular_id', $montagem['grade']->id)
        ->set('periodo', 1)
        ->set('nome', $nome)
        ->set('periodo_letivo', '2026')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Turma::query()->where('nome', $nome)->exists())->toBeTrue();
})->with(['2 A', '3B', 'Noturno A', '1 A - Manhã']);

it('publica a v1 sozinha ao abrir a primeira turma de um curso', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 2]);
    Disciplina::factory()->doCurso($curso)->noPeriodo(1)->create(['nome' => 'Lógica']);

    expect($curso->grades()->count())->toBe(0);

    Livewire::actingAs($this->paeet)
        ->test(FormularioTurma::class)
        ->set('curso_id', $curso->id)
        ->set('periodo', 1)
        ->set('nome', '1 A')
        ->set('periodo_letivo', '2026')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($curso->grades()->count())->toBe(1)
        ->and(Turma::query()->where('nome', '1 A')->value('grade_curricular_id'))
        ->toBe($curso->grades()->value('id'));
});

it('recusa abrir turma em curso sem nenhuma disciplina', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create();

    Livewire::actingAs($this->paeet)
        ->test(FormularioTurma::class)
        ->set('curso_id', $curso->id)
        ->set('periodo', 1)
        ->set('nome', '1 A')
        ->set('periodo_letivo', '2026')
        ->call('salvar')
        ->assertHasErrors('grade_curricular_id');

    expect(Turma::query()->where('nome', '1 A')->exists())->toBeFalse();
});
