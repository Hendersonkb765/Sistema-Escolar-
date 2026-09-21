<?php

/*
 * O vínculo que estava furado: disciplina presa só ao Eixo deixava montar
 * a grade de um curso com disciplina de outro. Agora ela pertence a um
 * curso e a um período dele.
 */

use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Livewire\Cursos\FormularioCurso;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Turma;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
});

it('amarra a disciplina a um curso, não apenas ao eixo', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create();
    $disciplina = Disciplina::factory()->doCurso($curso)->create();

    expect($disciplina->curso_id)->toBe($curso->id)
        ->and($disciplina->curso->eixo_id)->toBe($this->eixo->id)
        // O escopo por Eixo continua valendo, agora pelo curso.
        ->and(Disciplina::caminhoDoEixo())->toBe('curso.eixo')
        ->and($disciplina->eixoId())->toBe($this->eixo->id);
});

it('a grade de um curso nunca recebe disciplina de outro curso', function () {
    $desenvolvimento = Curso::factory()->noEixo($this->eixo)->create(['nome' => 'Desenvolvimento']);
    $logistica = Curso::factory()->noEixo($this->eixo)->create(['nome' => 'Logística']);

    Disciplina::factory()->doCurso($desenvolvimento)->noPeriodo(1)->create(['nome' => 'Lógica']);
    Disciplina::factory()->doCurso($logistica)->noPeriodo(1)->create(['nome' => 'Armazenagem']);

    $grade = app(PublicarVersaoDeGradeAction::class)->executar($desenvolvimento, $this->paeet);

    $nomes = $grade->disciplinas()->with('disciplina')->get()->pluck('disciplina.nome');

    expect($nomes)->toContain('Lógica')
        ->and($nomes)->not->toContain('Armazenagem')
        ->and($grade->disciplinas()->count())->toBe(1);
});

it('organiza as disciplinas do curso por período', function () {
    // O exemplo do enunciado: curso de dois anos, Lógica e Redes no 1º,
    // Back-end e Front-end no 2º.
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 2]);

    Disciplina::factory()->doCurso($curso)->noPeriodo(1)->create(['nome' => 'Lógica']);
    Disciplina::factory()->doCurso($curso)->noPeriodo(1)->create(['nome' => 'Redes']);
    Disciplina::factory()->doCurso($curso)->noPeriodo(2)->create(['nome' => 'Back-end']);
    Disciplina::factory()->doCurso($curso)->noPeriodo(2)->create(['nome' => 'Front-end']);

    $porPeriodo = $curso->disciplinasPorPeriodo();

    expect($porPeriodo->keys()->all())->toBe([1, 2])
        ->and($porPeriodo[1]->pluck('nome')->all())->toBe(['Lógica', 'Redes'])
        ->and($porPeriodo[2]->pluck('nome')->all())->toBe(['Back-end', 'Front-end'])
        ->and($curso->periodos())->toBe([1, 2]);
});

it('a turma cursa as disciplinas do seu período', function () {
    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica', 'Redes'],
        2 => ['Back-end', 'Front-end'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $primeiro = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $segundo = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 2, 'nome' => '2 A',
    ]);

    expect($primeiro->disciplinasDoPeriodo()->pluck('disciplina.nome')->sort()->values()->all())
        ->toBe(['Lógica', 'Redes'])
        ->and($segundo->disciplinasDoPeriodo()->pluck('disciplina.nome')->sort()->values()->all())
        ->toBe(['Back-end', 'Front-end']);
});

it('mantém o escopo por eixo através do curso', function () {
    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);
    $cursoAlheio = Curso::factory()->noEixo($outroEixo)->create();

    $minha = Disciplina::factory()->doCurso(Curso::factory()->noEixo($this->eixo)->create())->create();
    $alheia = Disciplina::factory()->doCurso($cursoAlheio)->create();

    expect(Disciplina::query()->visivelPara($this->paeet)->pluck('id')->all())->toBe([$minha->id])
        ->and($this->paeet->can('view', $minha))->toBeTrue()
        ->and($this->paeet->can('view', $alheia))->toBeFalse();

    $this->actingAs($this->paeet)
        ->get(route('disciplinas.editar', $alheia))
        ->assertForbidden();
});

it('não apaga disciplina já fotografada em alguma grade', function () {
    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    $fotografada = $montagem['disciplinas']['Lógica'];

    $solta = Disciplina::factory()->doCurso($montagem['curso'])->create(['codigo' => 'SOL']);

    expect($this->paeet->can('delete', $fotografada))->toBeFalse()
        ->and($this->paeet->can('delete', $solta))->toBeTrue();
});

it('impede encurtar o curso abaixo do período de suas disciplinas', function () {
    $curso = Curso::factory()->noEixo($this->eixo)->create(['duracao_anos' => 3]);
    Disciplina::factory()->doCurso($curso)->noPeriodo(3)->create();

    Livewire\Livewire::actingAs($this->paeet)
        ->test(FormularioCurso::class, ['curso' => $curso])
        ->set('duracao_anos', 2)
        ->call('salvar')
        ->assertHasErrors('duracao_anos');

    expect($curso->refresh()->duracao_anos)->toBe(3);
});
