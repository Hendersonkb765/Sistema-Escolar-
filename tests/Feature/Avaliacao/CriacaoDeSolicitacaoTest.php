<?php

/*
 * Abertura de solicitações: uma prova reúne várias disciplinas, cada uma
 * com seu professor e sua cota de questões.
 */

use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->logicaProf = professor($this->eixo);
    $this->logicaProf->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->redesProf = professor($this->eixo);
    $this->redesProf->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes de Computadores'],
        2 => ['Back-end'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->curso = $montagem['curso'];
    $this->logica = $montagem['disciplinas']['Lógica de Programação'];
    $this->redes = $montagem['disciplinas']['Redes de Computadores'];
    $this->backend = $montagem['disciplinas']['Back-end'];

    $this->turma = Turma::factory()->doCurso($this->curso, $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->criar = app(CriarSolicitacaoAction::class);
});

it('abre uma prova com duas disciplinas e professores diferentes', function () {
    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf, 'questoes' => 5],
        ['disciplina' => $this->redes, 'professor' => $this->redesProf, 'questoes' => 3],
    ], titulo: 'Avaliação do 2º bimestre');

    expect($solicitacao->status)->toBe(StatusSolicitacao::Aberta)
        ->and($solicitacao->titulo)->toBe('Avaliação do 2º bimestre')
        ->and($solicitacao->partes()->count())->toBe(2)
        ->and($solicitacao->totalDeQuestoes())->toBe(8)
        ->and($solicitacao->questoes()->count())->toBe(8);

    $partes = $solicitacao->partes()->with(['disciplina', 'professor'])->get();

    expect($partes->pluck('disciplina.nome')->all())
        ->toBe(['Lógica de Programação', 'Redes de Computadores'])
        ->and($partes->pluck('professor.nome')->all())
        ->toBe(['Renato Lima', 'Marta Reis'])
        ->and($partes->pluck('quantidade_questoes')->all())->toBe([5, 3])
        ->and($partes->pluck('ordem')->all())->toBe([1, 2]);
});

it('cada questão nasce ligada à sua parte, com a disciplina e o professor certos', function () {
    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf, 'questoes' => 2],
        ['disciplina' => $this->redes, 'professor' => $this->redesProf, 'questoes' => 3],
    ]);

    $deLogica = $solicitacao->questoes()->where('disciplina_id', $this->logica->id)->get();
    $deRedes = $solicitacao->questoes()->where('disciplina_id', $this->redes->id)->get();

    expect($deLogica)->toHaveCount(2)
        ->and($deLogica->pluck('professor_id')->unique()->all())->toBe([$this->logicaProf->id])
        ->and($deLogica->pluck('ordem')->all())->toBe([1, 2])
        ->and($deRedes)->toHaveCount(3)
        ->and($deRedes->pluck('professor_id')->unique()->all())->toBe([$this->redesProf->id])
        ->and($deRedes->pluck('ordem')->all())->toBe([1, 2, 3])
        ->and($solicitacao->questoes()->where('status', StatusQuestao::Rascunho)->count())->toBe(5);
});

it('cria o vínculo docente de cada professor com a sua disciplina', function () {
    expect($this->logicaProf->lecionaDisciplina($this->logica->id))->toBeFalse()
        ->and($this->redesProf->lecionaDisciplina($this->redes->id))->toBeFalse();

    solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf],
        ['disciplina' => $this->redes, 'professor' => $this->redesProf],
    ]);

    expect($this->logicaProf->refresh()->esquecerEscopo()->lecionaDisciplina($this->logica->id))->toBeTrue()
        ->and($this->redesProf->refresh()->esquecerEscopo()->lecionaDisciplina($this->redes->id))->toBeTrue()
        // E não cria vínculo cruzado.
        ->and($this->logicaProf->lecionaDisciplina($this->redes->id))->toBeFalse();
});

it('o mesmo professor pode responder duas disciplinas da mesma prova', function () {
    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf, 'questoes' => 2],
        ['disciplina' => $this->redes, 'professor' => $this->logicaProf, 'questoes' => 2],
    ]);

    expect($solicitacao->partes()->count())->toBe(2)
        ->and($solicitacao->partes()->pluck('professor_id')->unique()->all())->toBe([$this->logicaProf->id])
        ->and($solicitacao->questoes()->count())->toBe(4);
});

it('recusa a mesma disciplina duas vezes na prova', function () {
    expect(fn () => solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf],
        ['disciplina' => $this->logica, 'professor' => $this->redesProf],
    ]))->toThrow(RegraDeNegocioException::class, 'aparece mais de uma vez');

    expect(SolicitacaoProva::query()->count())->toBe(0);
});

it('recusa disciplina que a turma não cursa no período dela', function () {
    expect(fn () => solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf],
        ['disciplina' => $this->backend, 'professor' => $this->redesProf],
    ]))->toThrow(RegraDeNegocioException::class, 'não cursa Back-end no 1º período');

    expect(SolicitacaoProva::query()->count())->toBe(0);
});

it('recusa disciplina de outro curso', function () {
    $alheia = Disciplina::factory()
        ->doCurso(Curso::factory()->noEixo($this->eixo)->create())
        ->noPeriodo(1)
        ->create(['nome' => 'Armazenagem']);

    expect(fn () => solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $alheia, 'professor' => $this->logicaProf],
    ]))->toThrow(RegraDeNegocioException::class, 'não pertence ao curso desta turma');
});

it('recusa prova sem nenhuma disciplina', function () {
    expect(fn () => solicitacaoCom($this->paeet, $this->turma, []))
        ->toThrow(RegraDeNegocioException::class, 'ao menos uma disciplina');
});

it('recusa cota de questões menor que um', function () {
    expect(fn () => solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf, 'questoes' => 0],
    ]))->toThrow(RegraDeNegocioException::class, 'ao menos uma questão de Lógica');
});

it('recusa quantidade de alternativas fora da faixa', function (int $quantidade) {
    expect(fn () => solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf],
    ], alternativas: $quantidade))->toThrow(RegraDeNegocioException::class, 'de 2 a 6 alternativas');
})->with([1, 7]);

it('recusa designar professor inativo', function () {
    $this->redesProf->update(['ativo' => false]);

    expect(fn () => solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf],
        ['disciplina' => $this->redes, 'professor' => $this->redesProf->refresh()],
    ]))->toThrow(RegraDeNegocioException::class, 'está inativo');
});

it('não deixa a prova pela metade quando uma parte é inválida', function () {
    try {
        solicitacaoCom($this->paeet, $this->turma, [
            ['disciplina' => $this->logica, 'professor' => $this->logicaProf],
            ['disciplina' => $this->backend, 'professor' => $this->redesProf],
        ]);
    } catch (RegraDeNegocioException) {
        // esperado
    }

    expect(SolicitacaoProva::query()->count())->toBe(0)
        ->and(Questao::query()->count())->toBe(0);
});

it('nega a abertura ao professor', function () {
    expect(fn () => solicitacaoCom($this->logicaProf, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf],
    ]))->toThrow(AuthorizationException::class);
});

it('nega a abertura a um PAEET de outro eixo', function () {
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => solicitacaoCom($intruso, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf],
    ]))->toThrow(AuthorizationException::class);

    expect(SolicitacaoProva::query()->count())->toBe(0);
});

it('o professor define o peso de cada questão', function () {
    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf, 'questoes' => 5],
    ]);

    // Os pesos do exemplo do enunciado: 1; 1; 0,5; 0,5; 0,75 — soma 3,75.
    $pesos = [1 => 1, 2 => 1, 3 => 0.5, 4 => 0.5, 5 => 0.75];

    foreach ($solicitacao->questoes()->get() as $questao) {
        completarQuestao($questao, $this->logicaProf, peso: $pesos[$questao->ordem]);
    }

    expect((float) $solicitacao->refresh()->somaDosPesos())->toBe(3.75);
});

it('a soma dos pesos cobre a prova inteira, somando as disciplinas', function () {
    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf, 'questoes' => 2],
        ['disciplina' => $this->redes, 'professor' => $this->redesProf, 'questoes' => 2],
    ]);

    $partes = $solicitacao->partes()->get();

    foreach ($partes[0]->questoes()->get() as $questao) {
        completarQuestao($questao, $this->logicaProf, peso: 2);
    }

    foreach ($partes[1]->questoes()->get() as $questao) {
        completarQuestao($questao, $this->redesProf, peso: 0.5);
    }

    expect((float) $partes[0]->somaDosPesos())->toBe(4.0)
        ->and((float) $partes[1]->somaDosPesos())->toBe(1.0)
        ->and((float) $solicitacao->refresh()->somaDosPesos())->toBe(5.0);
});

it('registra a abertura na auditoria com as disciplinas', function () {
    solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->logicaProf, 'questoes' => 2],
        ['disciplina' => $this->redes, 'professor' => $this->redesProf, 'questoes' => 3],
    ]);

    $registro = Activity::query()
        ->where('log_name', 'solicitacao')->latest('id')->first();

    expect($registro->description)->toBe('Solicitação aberta com 2 disciplina(s) e 5 questão(ões)')
        ->and($registro->causer_id)->toBe($this->paeet->id)
        ->and($registro->getProperty('partes'))->toBe(2)
        ->and($registro->getProperty('questoes'))->toBe(5)
        ->and($registro->getProperty('disciplinas'))
        ->toContain('Lógica de Programação', 'Redes de Computadores');
});
