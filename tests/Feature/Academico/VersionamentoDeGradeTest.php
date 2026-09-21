<?php

/*
 * Critério de aceite 5:
 * uma nova versão de grade não altera turmas nem provas anteriores.
 */

use App\Actions\Academico\CriarNovaVersaoDeGradeAction;
use App\Actions\Academico\PublicarGradeAction;
use App\Enums\StatusGrade;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação'],
        2 => ['Banco de Dados'],
        3 => ['Processos de Desenvolvimento'],
    ]);

    $this->curso = $montagem['curso'];
    $this->v1 = $montagem['grade'];
    $this->disciplinas = $montagem['disciplinas'];

    $this->turmaAntiga = Turma::factory()->doCurso($this->curso, $this->v1)->create([
        'ano_curso' => 2,
        'identificacao' => '2DS',
        'periodo_letivo' => '2026',
    ]);

    $this->novaVersao = app(CriarNovaVersaoDeGradeAction::class);
    $this->publicar = app(PublicarGradeAction::class);
});

it('cria a próxima versão copiando as disciplinas, em rascunho', function () {
    $v2 = $this->novaVersao->executar($this->v1, $this->paeet);

    expect($v2->versao)->toBe(2)
        ->and($v2->status)->toBe(StatusGrade::Rascunho)
        ->and($v2->origem_grade_id)->toBe($this->v1->id)
        ->and($v2->disciplinas)->toHaveCount(3)
        ->and($v2->disciplinas->pluck('disciplina_id')->sort()->values()->all())
        ->toBe($this->v1->disciplinas->pluck('disciplina_id')->sort()->values()->all());
});

it('mover Banco de Dados do 2º para o 3º ano não muda a turma que já existia', function () {
    $bancoDeDados = $this->disciplinas['Banco de Dados'];

    // Estado antes: a turma 2DS cursa Banco de Dados no 2º ano.
    expect($this->turmaAntiga->disciplinasDoAno()->pluck('disciplina_id')->all())
        ->toBe([$bancoDeDados->id]);

    // Nova versão move Banco de Dados para o 3º ano e publica.
    $v2 = $this->novaVersao->executar($this->v1, $this->paeet);
    $v2->disciplinas()
        ->where('disciplina_id', $bancoDeDados->id)
        ->update(['ano_curso' => 3]);
    $this->publicar->executar($v2->refresh(), $this->paeet);

    $this->turmaAntiga->refresh();

    expect($this->turmaAntiga->grade_curricular_id)->toBe($this->v1->id)
        // A turma continua vendo exatamente o que via antes.
        ->and($this->turmaAntiga->disciplinasDoAno()->pluck('disciplina_id')->all())
        ->toBe([$bancoDeDados->id]);

    // E a versão 1 permanece intacta.
    expect($this->v1->refresh()->disciplinas()->where('disciplina_id', $bancoDeDados->id)->value('ano_curso'))
        ->toBe(2);
});

it('uma turma nova nasce na versão vigente e enxerga a grade nova', function () {
    $bancoDeDados = $this->disciplinas['Banco de Dados'];

    $v2 = $this->novaVersao->executar($this->v1, $this->paeet);
    $v2->disciplinas()->where('disciplina_id', $bancoDeDados->id)->update(['ano_curso' => 3]);
    $this->publicar->executar($v2->refresh(), $this->paeet);

    $turmaNova = Turma::factory()->doCurso($this->curso, $v2)->create([
        'ano_curso' => 2,
        'identificacao' => '2DS-B',
        'periodo_letivo' => '2027',
    ]);

    // Na v2 o 2º ano ficou vazio e o 3º passou a ter Processos + Banco de Dados.
    expect($turmaNova->disciplinasDoAno()->pluck('disciplina_id')->all())->toBe([])
        ->and($turmaNova->disciplinasDoAno(3)->pluck('disciplina_id')->all())
        ->toContain($bancoDeDados->id)
        ->and($turmaNova->disciplinasDoAno(3))->toHaveCount(2);
});

it('publicar a nova versão arquiva a anterior sem apagá-la', function () {
    $v2 = $this->novaVersao->executar($this->v1, $this->paeet);

    $this->publicar->executar($v2, $this->paeet);

    expect($this->v1->refresh()->status)->toBe(StatusGrade::Arquivada)
        ->and($this->v1->exists)->toBeTrue()
        ->and($this->v1->disciplinas)->toHaveCount(3)
        ->and($v2->refresh()->status)->toBe(StatusGrade::Vigente)
        // A turma antiga segue ligada à versão arquivada.
        ->and($this->turmaAntiga->refresh()->grade_curricular_id)->toBe($this->v1->id);
});

it('não deixa editar uma versão já congelada por turmas', function () {
    expect($this->v1->emUso())->toBeTrue()
        ->and($this->v1->editavel())->toBeFalse()
        ->and($this->paeet->can('update', $this->v1))->toBeFalse();
});

it('deixa editar um rascunho que nenhuma turma usa', function () {
    $v2 = $this->novaVersao->executar($this->v1, $this->paeet);

    expect($v2->emUso())->toBeFalse()
        ->and($v2->editavel())->toBeTrue()
        ->and($this->paeet->can('update', $v2))->toBeTrue();
});

it('recusa publicar uma grade sem disciplinas', function () {
    $vazia = GradeCurricular::factory()->doCurso($this->curso)->rascunho()->create();

    expect($this->paeet->can('publicar', $vazia))->toBeFalse();

    expect(fn () => $this->publicar->executar($vazia, $this->paeet))
        ->toThrow(AuthorizationException::class);
});

it('numera as versões em sequência por curso', function () {
    $v2 = $this->novaVersao->executar($this->v1, $this->paeet);
    $v3 = $this->novaVersao->executar($v2, $this->paeet);

    expect([$this->v1->versao, $v2->versao, $v3->versao])->toBe([1, 2, 3]);

    // Outro curso reinicia em 1.
    $outro = cursoComGrade($this->eixo, [1 => ['Redes']]);
    expect($outro['grade']->versao)->toBe(1);
});

it('nega o versionamento a um PAEET de outro eixo', function () {
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => $this->novaVersao->executar($this->v1, $intruso))
        ->toThrow(AuthorizationException::class);

    expect(GradeCurricular::query()->where('curso_id', $this->curso->id)->count())->toBe(1);
});

it('registra o versionamento e a publicação na auditoria', function () {
    $v2 = $this->novaVersao->executar($this->v1, $this->paeet);
    $this->publicar->executar($v2, $this->paeet);

    $descricoes = Activity::query()
        ->where('log_name', 'grade')
        ->pluck('description');

    expect($descricoes)->toContain('Versão 2 criada a partir da versão 1')
        ->and($descricoes)->toContain('Versão 1 arquivada')
        ->and($descricoes)->toContain('Versão 2 entrou em vigência');
});
