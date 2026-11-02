<?php

/*
 * A barra superior e o painel dizem em que bimestre estamos e quantos
 * dias faltam para ele acabar. Tudo vem do calendário letivo.
 *
 * Entre bimestres, o que importa é quando o próximo começa. Sem
 * calendário, a tela diz que ele falta — nunca "0 dias".
 */

use App\Livewire\Painel;
use App\Models\BimestreLetivo;
use App\Models\Eixo;
use App\Support\SituacaoDoBimestre;
use Illuminate\Support\Carbon;
use Illuminate\Support\Once;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);

    $this->calendario = function () {
        foreach ([
            2 => ['2026-04-27', '2026-07-03'],
            3 => ['2026-07-27', '2026-10-02'],
        ] as $bimestre => [$inicio, $fim]) {
            BimestreLetivo::query()->create([
                'periodo_letivo' => '2026', 'bimestre' => $bimestre, 'inicio' => $inicio, 'fim' => $fim,
            ]);
        }
    };
});

afterEach(fn () => Carbon::setTestNow());

// --- A conta -------------------------------------------------------------

it('diz o bimestre de hoje e quantos dias faltam para acabar', function (string $hoje, int $dias, string $prazo) {
    ($this->calendario)();

    $situacao = SituacaoDoBimestre::em(Carbon::parse($hoje));

    expect($situacao->emAndamento())->toBeTrue()
        ->and($situacao->rotulo())->toBe('2º bimestre')
        ->and($situacao->dias())->toBe($dias)
        ->and($situacao->prazo())->toBe($prazo)
        ->and($situacao->periodo())->toBe('De 27/04/2026 a 03/07/2026');
})->with([
    'o primeiro dia' => ['2026-04-27', 67, 'faltam 67 dias'],
    'no meio' => ['2026-06-21', 12, 'faltam 12 dias'],
    'a véspera' => ['2026-07-02', 1, 'falta 1 dia'],
    'o último dia' => ['2026-07-03', 0, 'termina hoje'],
]);

it('marca a última semana do bimestre', function (string $hoje, bool $acabando) {
    ($this->calendario)();

    expect(SituacaoDoBimestre::em(Carbon::parse($hoje))->acabando())->toBe($acabando);
})->with([
    'oito dias antes' => ['2026-06-25', false],
    'sete dias antes' => ['2026-06-26', true],
]);

it('nas férias, diz quando o próximo bimestre começa', function () {
    ($this->calendario)();

    $situacao = SituacaoDoBimestre::em(Carbon::parse('2026-07-14'));

    expect($situacao->emAndamento())->toBeFalse()
        ->and($situacao->rotulo())->toBe('Férias')
        ->and($situacao->dias())->toBe(13)
        ->and($situacao->prazo())->toBe('faltam 13 dias para o 3º bimestre')
        ->and($situacao->periodo())->toBe('O 3º bimestre começa em 27/07/2026')
        ->and($situacao->acabando())->toBeFalse();
});

it('diz que o calendário falta, sem inventar um número', function (string $hoje) {
    ($this->calendario)();

    $situacao = SituacaoDoBimestre::em(Carbon::parse($hoje));

    expect($situacao->rotulo())->toBe('Calendário não cadastrado')
        ->and($situacao->dias())->toBeNull()
        ->and($situacao->prazo())->toBeNull();
})->with([
    'depois do último bimestre cadastrado' => ['2026-12-01'],
]);

// --- Barra superior ------------------------------------------------------

it('mostra o bimestre na barra superior de qualquer tela, para todos os perfis', function (string $perfil) {
    ($this->calendario)();
    Carbon::setTestNow('2026-06-21 10:00');

    $usuario = match ($perfil) {
        'professor' => professor($this->eixo),
        'paeet' => paeet($this->eixo),
    };

    foreach (['painel', 'calendario.index'] as $rota) {
        $html = $this->actingAs($usuario)->get(route($rota))->assertOk()->getContent();

        expect($html)->toMatch('/<a [^>]*data-bimestre-atual[^>]*title="De 27\/04\/2026 a 03\/07\/2026"/')
            ->and($html)->toContain('2º bimestre · faltam 12 dias');
    }
})->with(['professor', 'paeet']);

it('mostra as férias e a falta de calendário na barra superior', function () {
    $usuario = paeet($this->eixo);

    Carbon::setTestNow('2026-07-14 10:00');
    $this->actingAs($usuario)->get(route('painel'))
        ->assertSee('Calendário não cadastrado');

    ($this->calendario)();
    // A situação é calculada uma vez por requisição; aqui são duas no mesmo processo.
    Once::flush();
    Carbon::setTestNow('2026-07-15 10:00');
    $this->actingAs($usuario)->get(route('painel'))
        ->assertSee('Férias · faltam 12 dias para o 3º bimestre');
});

// --- Painel --------------------------------------------------------------

it('abre o painel com o card do bimestre', function () {
    ($this->calendario)();
    Carbon::setTestNow('2026-06-21 10:00');

    $card = Livewire::actingAs(professor($this->eixo))->test(Painel::class)->viewData('indicadores')[0];

    expect($card['rotulo'])->toBe('2º bimestre')
        ->and($card['valor'])->toBe('12 dias')
        ->and($card['detalhe'])->toBe('para acabar · termina em 03/07/2026')
        ->and($card['cor'])->toBe('azul')
        ->and($card['href'])->toBe(route('calendario.index'));
});

it('pinta o card de amarelo na última semana', function () {
    ($this->calendario)();
    Carbon::setTestNow('2026-07-02 10:00');

    $card = Livewire::actingAs(paeet($this->eixo))->test(Painel::class)->viewData('indicadores')[0];

    expect($card['valor'])->toBe('1 dia')
        ->and($card['cor'])->toBe('amarelo');
});

it('mostra no card as férias e a falta de calendário', function () {
    Carbon::setTestNow('2026-07-14 10:00');
    $usuario = paeet($this->eixo);

    $semCalendario = Livewire::actingAs($usuario)->test(Painel::class)->viewData('indicadores')[0];

    expect($semCalendario['valor'])->toBe('—')
        ->and($semCalendario['detalhe'])->toBe('Calendário letivo não cadastrado')
        ->and($semCalendario['cor'])->toBe('cinza');

    ($this->calendario)();
    Once::flush();

    $ferias = Livewire::actingAs($usuario)->test(Painel::class)->viewData('indicadores')[0];

    expect($ferias['valor'])->toBe('13 dias')
        ->and($ferias['detalhe'])->toBe('Férias · o 3º bimestre começa em 27/07/2026');
});
