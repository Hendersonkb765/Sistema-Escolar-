<?php

/*
 * Critério de aceite 5:
 * uma nova versão de grade não altera turmas nem provas anteriores.
 *
 * O cadastro de disciplinas é o estado vivo; a grade é a foto tirada ao
 * publicar. Mudar o período de uma disciplina não reescreve o percurso de
 * quem já começou o curso.
 */

use App\Actions\Academico\CompararGradeComCursoAction;
use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Enums\StatusGrade;
use App\Enums\StatusRegistro;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    // Curso de 2 anos, como no exemplo: Lógica e Redes no 1º período,
    // Back-end e Front-end no 2º.
    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes de Computadores'],
        2 => ['Back-end', 'Front-end'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->curso = $montagem['curso'];
    $this->v1 = $montagem['grade'];
    $this->disciplinas = $montagem['disciplinas'];

    $this->turmaAntiga = Turma::factory()->doCurso($this->curso, $this->v1)->create([
        'periodo' => 2,
        'nome' => '2 A',
        'periodo_letivo' => '2026',
    ]);

    $this->publicar = app(PublicarVersaoDeGradeAction::class);
    $this->comparar = app(CompararGradeComCursoAction::class);
});

it('publica a primeira versão como foto das disciplinas do curso', function () {
    expect($this->v1->versao)->toBe(1)
        ->and($this->v1->status)->toBe(StatusGrade::Vigente)
        ->and($this->v1->disciplinas()->count())->toBe(4)
        ->and($this->v1->disciplinas()->where('disciplina_id', $this->disciplinas['Back-end']->id)->value('periodo'))
        ->toBe(2);
});

it('mover Back-end do 2º para o 1º período não muda a turma que já existia', function () {
    $backend = $this->disciplinas['Back-end'];

    // Antes: a turma do 2º período cursa Back-end e Front-end.
    expect($this->turmaAntiga->disciplinasDoPeriodo()->pluck('disciplina_id')->sort()->values()->all())
        ->toBe(collect([$backend->id, $this->disciplinas['Front-end']->id])->sort()->values()->all());

    // O coordenador move Back-end para o 1º período e publica a v2.
    $backend->update(['periodo' => 1]);
    $v2 = $this->publicar->executar($this->curso, $this->paeet);

    $this->turmaAntiga->refresh();

    expect($this->turmaAntiga->grade_curricular_id)->toBe($this->v1->id)
        // A turma continua vendo exatamente o que via antes.
        ->and($this->turmaAntiga->disciplinasDoPeriodo()->pluck('disciplina_id')->all())
        ->toContain($backend->id);

    // A foto v1 permanece intacta; a v2 é que registra a mudança.
    expect($this->v1->refresh()->disciplinas()->where('disciplina_id', $backend->id)->value('periodo'))->toBe(2)
        ->and($v2->disciplinas()->where('disciplina_id', $backend->id)->value('periodo'))->toBe(1);
});

it('uma turma nova nasce na versão vigente e enxerga a grade nova', function () {
    $backend = $this->disciplinas['Back-end'];
    $backend->update(['periodo' => 1]);

    $v2 = $this->publicar->executar($this->curso, $this->paeet);

    $turmaNova = Turma::factory()->doCurso($this->curso, $v2)->create([
        'periodo' => 2,
        'nome' => '2 B',
        'periodo_letivo' => '2027',
    ]);

    expect($turmaNova->disciplinasDoPeriodo()->pluck('disciplina_id')->all())
        ->not->toContain($backend->id)
        ->and($turmaNova->disciplinasDoPeriodo(1)->pluck('disciplina_id')->all())
        ->toContain($backend->id);
});

it('publicar arquiva a versão anterior sem apagá-la', function () {
    $v2 = $this->publicar->executar($this->curso, $this->paeet);

    expect($this->v1->refresh()->status)->toBe(StatusGrade::Arquivada)
        ->and($this->v1->exists)->toBeTrue()
        ->and($this->v1->disciplinas()->count())->toBe(4)
        ->and($v2->status)->toBe(StatusGrade::Vigente)
        ->and($v2->origem_grade_id)->toBe($this->v1->id)
        // A turma antiga segue ligada à versão arquivada.
        ->and($this->turmaAntiga->refresh()->grade_curricular_id)->toBe($this->v1->id);
});

it('a grade é uma foto e nunca é editada nem apagada', function () {
    expect($this->paeet->can('update', $this->v1))->toBeFalse()
        ->and($this->paeet->can('delete', $this->v1))->toBeFalse()
        ->and($this->v1->editavel())->toBeFalse()
        ->and($this->v1->emUso())->toBeTrue();
});

it('recusa publicar um curso sem disciplinas ativas', function () {
    $vazio = Curso::factory()->noEixo($this->eixo)->create();

    expect(fn () => $this->publicar->executar($vazio, $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'ao menos uma disciplina ativa');

    expect(GradeCurricular::query()->where('curso_id', $vazio->id)->exists())->toBeFalse();
});

it('não fotografa disciplina inativa', function () {
    $this->disciplinas['Front-end']->update(['status' => StatusRegistro::Inativo]);

    $v2 = $this->publicar->executar($this->curso, $this->paeet);

    expect($v2->disciplinas()->count())->toBe(3)
        // Mas a v1 segue registrando que ela existia.
        ->and($this->v1->disciplinas()->count())->toBe(4);
});

it('recusa publicar com disciplina em período além da duração do curso', function () {
    // O curso tem 2 anos; força uma disciplina no 3º para simular um
    // encurtamento posterior do curso.
    Disciplina::query()->where('id', $this->disciplinas['Back-end']->id)->update(['periodo' => 3]);

    expect(fn () => $this->publicar->executar($this->curso->refresh(), $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'além da duração do curso');
});

it('numera as versões em sequência por curso', function () {
    $v2 = $this->publicar->executar($this->curso, $this->paeet);
    $v3 = $this->publicar->executar($this->curso, $this->paeet);

    expect([$this->v1->versao, $v2->versao, $v3->versao])->toBe([1, 2, 3]);

    $outro = cursoComGrade($this->eixo, [1 => ['Contabilidade']], autor: $this->paeet);
    expect($outro['grade']->versao)->toBe(1);
});

it('nega a publicação a um PAEET de outro eixo', function () {
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => $this->publicar->executar($this->curso, $intruso))
        ->toThrow(AuthorizationException::class);

    expect(GradeCurricular::query()->where('curso_id', $this->curso->id)->count())->toBe(1);
});

it('registra a publicação e o arquivamento na auditoria', function () {
    $this->publicar->executar($this->curso, $this->paeet);

    $descricoes = Activity::query()
        ->where('log_name', 'grade')
        ->pluck('description');

    expect($descricoes)->toContain('Versão 1 arquivada')
        ->and($descricoes)->toContain('Versão 2 publicada com 4 disciplina(s)');
});

it('aponta o que mudou no cadastro desde a última publicação', function () {
    expect($this->comparar->executar($this->curso, $this->v1)['divergente'])->toBeFalse();

    $this->disciplinas['Back-end']->update(['periodo' => 1]);
    $nova = Disciplina::factory()->doCurso($this->curso)->noPeriodo(2)->create(['nome' => 'Mobile']);
    $this->disciplinas['Redes de Computadores']->update(['status' => StatusRegistro::Inativo]);

    $diferencas = $this->comparar->executar($this->curso->refresh(), $this->v1);

    expect($diferencas['divergente'])->toBeTrue()
        ->and($diferencas['incluidas']->pluck('id')->all())->toBe([$nova->id])
        ->and($diferencas['removidas']->pluck('nome')->all())->toBe(['Redes de Computadores'])
        ->and($diferencas['movidas']->first()['disciplina']->nome)->toBe('Back-end')
        ->and($diferencas['movidas']->first()['periodo_publicado'])->toBe(2)
        ->and($diferencas['movidas']->first()['periodo_atual'])->toBe(1);
});

it('garantirVigente publica a primeira versão e reaproveita a existente', function () {
    $novoCurso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 2]);
    Disciplina::factory()->doCurso($novoCurso)->noPeriodo(1)->create();

    $primeira = $this->publicar->garantirVigente($novoCurso, $this->paeet);
    $segunda = $this->publicar->garantirVigente($novoCurso->refresh(), $this->paeet);

    expect($primeira->versao)->toBe(1)
        ->and($segunda->id)->toBe($primeira->id)
        ->and(GradeCurricular::query()->where('curso_id', $novoCurso->id)->count())->toBe(1);
});
