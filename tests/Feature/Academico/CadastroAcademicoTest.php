<?php

/*
 * CRUD da estrutura acadêmica: o que Henderson não conseguia fazer antes
 * do milestone 2 — cadastrar curso, disciplina, grade, turma e aluno.
 */

use App\Enums\EventoHistorico;
use App\Enums\StatusGrade;
use App\Livewire\Alunos\FormularioAluno;
use App\Livewire\Cursos\FormularioCurso;
use App\Livewire\Disciplinas\FormularioDisciplina;
use App\Livewire\Grades\FormularioGrade;
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

it('impede encurtar um curso abaixo do ano de suas turmas', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 3]);
    Turma::factory()->doCurso($curso)->create(['ano_curso' => 3, 'identificacao' => '3DS']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioCurso::class, ['curso' => $curso])
        ->set('duracao_anos', 2)
        ->call('salvar')
        ->assertHasErrors('duracao_anos');

    expect($curso->refresh()->duracao_anos)->toBe(3);
});

it('cadastra uma disciplina sem amarrá-la a um ano', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioDisciplina::class)
        ->set('eixo_id', $this->eixo->id)
        ->set('nome', 'Lógica de Programação')
        ->set('codigo', 'LOG')
        ->call('salvar')
        ->assertHasNoErrors();

    $disciplina = Disciplina::query()->where('codigo', 'LOG')->first();

    expect($disciplina)->not->toBeNull()
        // O ano é definido na grade, não na disciplina.
        ->and($disciplina->getAttributes())->not->toHaveKey('ano_curso');
});

it('cadastra uma grade em rascunho com disciplinas por ano', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 3]);
    $logica = Disciplina::factory()->noEixo($this->eixo)->create(['nome' => 'Lógica']);
    $banco = Disciplina::factory()->noEixo($this->eixo)->create(['nome' => 'Banco de Dados']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioGrade::class)
        ->set('curso_id', $curso->id)
        ->set('ano_vigencia', 2026)
        ->set('itens', [
            ['disciplina_id' => $logica->id, 'ano_curso' => 1, 'carga_horaria' => 80],
            ['disciplina_id' => $banco->id, 'ano_curso' => 2, 'carga_horaria' => 60],
        ])
        ->call('salvar')
        ->assertHasNoErrors();

    $grade = GradeCurricular::query()->where('curso_id', $curso->id)->first();

    expect($grade->status)->toBe(StatusGrade::Rascunho)
        ->and($grade->versao)->toBe(1)
        ->and($grade->disciplinas()->count())->toBe(2)
        ->and($grade->disciplinas()->where('disciplina_id', $banco->id)->value('ano_curso'))->toBe(2);
});

it('recusa disciplina em ano que o curso não tem', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 2]);
    $disciplina = Disciplina::factory()->noEixo($this->eixo)->create();

    Livewire::actingAs($this->paeet)
        ->test(FormularioGrade::class)
        ->set('curso_id', $curso->id)
        ->set('itens', [
            ['disciplina_id' => $disciplina->id, 'ano_curso' => 3, 'carga_horaria' => 80],
        ])
        ->call('salvar')
        ->assertHasErrors('itens.0.ano_curso');
});

it('recusa a mesma disciplina duas vezes no mesmo ano', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create();
    $disciplina = Disciplina::factory()->noEixo($this->eixo)->create();

    Livewire::actingAs($this->paeet)
        ->test(FormularioGrade::class)
        ->set('curso_id', $curso->id)
        ->set('itens', [
            ['disciplina_id' => $disciplina->id, 'ano_curso' => 1, 'carga_horaria' => 80],
            ['disciplina_id' => $disciplina->id, 'ano_curso' => 1, 'carga_horaria' => 40],
        ])
        ->call('salvar')
        ->assertHasErrors('itens.1.disciplina_id');
});

it('cadastra uma turma congelando a versão da grade', function () {
    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']]);

    Livewire::actingAs($this->paeet)
        ->test(FormularioTurma::class)
        ->set('curso_id', $montagem['curso']->id)
        ->set('grade_curricular_id', $montagem['grade']->id)
        ->set('ano_curso', 1)
        ->set('identificacao', '1DS')
        ->set('periodo_letivo', '2026')
        ->call('salvar')
        ->assertHasNoErrors();

    $turma = Turma::query()->where('identificacao', '1DS')->first();

    expect($turma->grade_curricular_id)->toBe($montagem['grade']->id)
        // Criar a turma já abre o histórico dela.
        ->and(TurmaHistorico::query()->where('turma_id', $turma->id)->count())->toBe(1);
});

it('recusa turma com grade de outro curso', function () {
    $a = cursoComGrade($this->eixo, [1 => ['Lógica']]);
    $b = cursoComGrade($this->eixo, [1 => ['Redes']]);

    Livewire::actingAs($this->paeet)
        ->test(FormularioTurma::class)
        ->set('curso_id', $a['curso']->id)
        ->set('grade_curricular_id', $b['grade']->id)
        ->set('identificacao', '1XX')
        ->call('salvar')
        ->assertHasErrors('grade_curricular_id');
});

it('matricula um aluno e abre o histórico dele', function () {
    $turma = Turma::factory()
        ->doCurso(Curso::factory()->noEixo($this->eixo)->create())
        ->create(['identificacao' => '1DS']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioAluno::class)
        ->set('turma_id', $turma->id)
        ->set('nome', 'Marina Alves')
        ->set('matricula', '20260001')
        ->call('salvar')
        ->assertHasNoErrors();

    $aluno = Aluno::query()->where('matricula', '20260001')->first();

    expect($aluno->turma_id)->toBe($turma->id)
        ->and($aluno->historicos()->count())->toBe(1)
        ->and($aluno->historicos()->first()->evento)->toBe(EventoHistorico::AlunoMatriculado);
});

it('recusa matrícula repetida na mesma turma', function () {
    $turma = Turma::factory()
        ->doCurso(Curso::factory()->noEixo($this->eixo)->create())
        ->create();

    Aluno::factory()->naTurma($turma)->create(['matricula' => '123']);

    Livewire::actingAs($this->paeet)
        ->test(FormularioAluno::class)
        ->set('turma_id', $turma->id)
        ->set('nome', 'Homônimo')
        ->set('matricula', '123')
        ->call('salvar')
        ->assertHasErrors('matricula');
});

it('grava histórico ao trocar o aluno de turma pelo formulário', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create();
    $origem = Turma::factory()->doCurso($curso)->create(['identificacao' => '1DS-A']);
    $destino = Turma::factory()->doCurso($curso)->create(['identificacao' => '1DS-B']);

    $aluno = Aluno::factory()->naTurma($origem)->create(['matricula' => '999']);

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

it('leva do curso recém-criado à grade e à turma', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create();

    $this->actingAs($this->paeet)
        ->get(route('cursos.show', $curso))
        ->assertOk()
        ->assertSee('Nova grade')
        ->assertSee('Nova turma');
});
