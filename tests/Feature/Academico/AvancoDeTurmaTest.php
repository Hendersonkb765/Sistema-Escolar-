<?php

/*
 * Critério de aceite 4:
 * o avanço 2 A → 3 A atualiza as disciplinas e grava histórico.
 */

use App\Actions\Academico\AvancarTurmaAction;
use App\Enums\EventoHistorico;
use App\Enums\StatusAluno;
use App\Enums\StatusTurma;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\Eixo;
use App\Models\Turma;
use App\Models\TurmaHistorico;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    // Grade do enunciado: Lógica no 1º, Processos no 2º, Banco de Dados no 3º.
    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação'],
        2 => ['Processos de Desenvolvimento'],
        3 => ['Banco de Dados'],
    ]);

    $this->curso = $montagem['curso'];
    $this->grade = $montagem['grade'];
    $this->disciplinas = $montagem['disciplinas'];

    $this->turma = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 2,
        'nome' => '2 A',
        'periodo_letivo' => '2026',
    ]);

    $this->action = app(AvancarTurmaAction::class);
});

it('avança 2 A para 3 A e troca as disciplinas do ano', function () {
    expect($this->turma->disciplinasDoPeriodo()->pluck('disciplina_id')->all())
        ->toBe([$this->disciplinas['Processos de Desenvolvimento']->id]);

    $resultado = $this->action->executar($this->turma, $this->paeet);

    $turma = $resultado['turma'];

    expect($turma->periodo)->toBe(3)
        ->and($turma->nome)->toBe('3 A')
        ->and($resultado['disciplinas']->pluck('disciplina_id')->all())
        ->toBe([$this->disciplinas['Banco de Dados']->id]);
});

it('grava o estado anterior no histórico antes de mudar', function () {
    $this->action->executar($this->turma, $this->paeet, observacoes: 'Virada de 2026');

    $historico = TurmaHistorico::query()->where('turma_id', $this->turma->id)->latest('id')->first();

    expect($historico)->not->toBeNull()
        ->and($historico->evento)->toBe(EventoHistorico::TurmaAvancoAno)
        // O histórico guarda o que existia ANTES: 2º ano, 2DS.
        ->and($historico->periodo)->toBe(2)
        ->and($historico->nome)->toBe('2 A')
        ->and($historico->grade_curricular_id)->toBe($this->grade->id)
        ->and($historico->observacoes)->toBe('Virada de 2026')
        ->and($historico->registrado_por)->toBe($this->paeet->id)
        ->and($historico->metadados['periodo_destino'])->toBe(3);
});

it('mantém a grade congelada da turma ao avançar', function () {
    $resultado = $this->action->executar($this->turma, $this->paeet);

    expect($resultado['turma']->grade_curricular_id)->toBe($this->grade->id);
});

it('registra o avanço na auditoria', function () {
    $this->action->executar($this->turma, $this->paeet);

    $registro = Activity::query()->where('log_name', 'turma')->latest('id')->first();

    expect($registro)->not->toBeNull()
        ->and($registro->description)->toBe('Turma avançada do 2º para o 3º período')
        ->and($registro->causer_id)->toBe($this->paeet->id)
        ->and($registro->getProperty('periodo_anterior'))->toBe(2)
        ->and($registro->getProperty('periodo_novo'))->toBe(3);
});

it('não avança além do último ano do curso', function () {
    $this->turma->update(['periodo' => 3, 'nome' => '3 A']);

    expect(fn () => $this->action->executar($this->turma->refresh(), $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'último do curso');

    expect($this->turma->refresh()->periodo)->toBe(3);
});

it('não avança turma que não está ativa', function () {
    $this->turma->update(['status' => StatusTurma::Concluida]);

    // Regra de negócio, não falta de permissão: a mensagem explica o motivo.
    expect(fn () => $this->action->executar($this->turma->refresh(), $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'Apenas turmas ativas');

    expect($this->turma->refresh()->periodo)->toBe(2);
});

it('nega o avanço a um PAEET de outro eixo', function () {
    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);

    expect(fn () => $this->action->executar($this->turma, paeet($outroEixo)))
        ->toThrow(AuthorizationException::class);

    expect($this->turma->refresh()->periodo)->toBe(2);
});

it('nega o avanço ao professor', function () {
    expect(fn () => $this->action->executar($this->turma, professor($this->eixo)))
        ->toThrow(AuthorizationException::class);
});

it('move os alunos e grava histórico quando há turma de destino', function () {
    $destino = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 3,
        'nome' => '3 A',
        'periodo_letivo' => '2027',
    ]);

    $ativo = Aluno::factory()->naTurma($this->turma)->create(['ra' => '1001']);
    $transferido = Aluno::factory()->naTurma($this->turma)->create([
        'ra' => '1002',
        'status' => StatusAluno::Transferido,
    ]);

    $resultado = $this->action->executar($this->turma, $this->paeet, turmaDestino: $destino);

    expect($resultado['alunos_movidos'])->toBe(1)
        ->and($ativo->refresh()->turma_id)->toBe($destino->id)
        // Aluno transferido não é arrastado junto.
        ->and($transferido->refresh()->turma_id)->toBe($this->turma->id)
        ->and($this->turma->refresh()->status)->toBe(StatusTurma::Concluida);

    $historicoAluno = AlunoHistorico::query()->where('aluno_id', $ativo->id)->latest('id')->first();

    expect($historicoAluno->evento)->toBe(EventoHistorico::AlunoTrocaTurma)
        ->and($historicoAluno->turma_anterior_id)->toBe($this->turma->id)
        ->and($historicoAluno->turma_nova_id)->toBe($destino->id)
        ->and($historicoAluno->motivo)->toBe('Avanço de ano da turma');
});

it('recusa turma de destino de outro curso ou de ano errado', function () {
    $outroCurso = cursoComGrade($this->eixo, [3 => ['Redes']])['curso'];
    $destinoErrado = Turma::factory()
        ->doCurso($outroCurso)
        ->create(['periodo' => 3, 'nome' => '3 R']);

    expect(fn () => $this->action->executar($this->turma, $this->paeet, turmaDestino: $destinoErrado))
        ->toThrow(RegraDeNegocioException::class, 'outro curso');

    $mesmoAno = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 2,
        'nome' => '2 B',
    ]);

    expect(fn () => $this->action->executar($this->turma, $this->paeet, turmaDestino: $mesmoAno))
        ->toThrow(RegraDeNegocioException::class);
});

it('deixa o avanço em nada quando a regra é violada', function () {
    $this->turma->update(['periodo' => 3]);

    $historicosAntes = TurmaHistorico::query()->count();

    try {
        $this->action->executar($this->turma->refresh(), $this->paeet);
    } catch (RegraDeNegocioException) {
        // esperado
    }

    expect(TurmaHistorico::query()->count())->toBe($historicosAntes)
        ->and($this->turma->refresh()->periodo)->toBe(3);
});

it('mantém a identificação quando ela não começa por número', function () {
    $this->turma->update(['nome' => 'Turma Noturna']);

    $resultado = $this->action->executar($this->turma->refresh(), $this->paeet);

    expect($resultado['turma']->nome)->toBe('Turma Noturna')
        ->and($resultado['turma']->periodo)->toBe(3);
});

it('explica a colisão quando a turma do ano seguinte já existe no período', function () {
    // Cenário real: 1DS, 2DS e 3DS convivem no mesmo ano letivo.
    Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 3,
        'nome' => '3 A',
        'periodo_letivo' => '2026',
    ]);

    expect(fn () => $this->action->executar($this->turma, $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'Já existe a turma 3 A em 2026');

    // Nada foi alterado nem registrado.
    expect($this->turma->refresh()->periodo)->toBe(2)
        ->and(TurmaHistorico::query()->where('turma_id', $this->turma->id)->count())->toBe(0);
});

it('aceita a promoção quando o período letivo avança junto', function () {
    Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 3,
        'nome' => '3 A',
        'periodo_letivo' => '2026',
    ]);

    $resultado = $this->action->executar(
        $this->turma,
        $this->paeet,
        novoPeriodoLetivo: '2027',
    );

    expect($resultado['turma']->nome)->toBe('3 A')
        ->and($resultado['turma']->periodo_letivo)->toBe('2027')
        ->and($resultado['turma']->periodo)->toBe(3);
});
