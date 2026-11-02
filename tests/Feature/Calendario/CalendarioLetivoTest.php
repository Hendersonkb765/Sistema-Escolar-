<?php

/*
 * O calendário letivo diz em que bimestre e semana cai uma data.
 *
 * O escopo-sequência é escrito por bimestre e semana, sem datas. É o
 * calendário que liga "terça, 12/05" a "2º bimestre, semana 3" — e é
 * por ele que a ausência do professor acha a aula do escopo daquele dia.
 *
 * A semana 1 é a semana (segunda a domingo) em que o bimestre começa,
 * ainda que ele comece numa quarta.
 */

use App\Enums\Bimestre;
use App\Livewire\Calendario\CalendarioLetivo;
use App\Models\BimestreLetivo;
use App\Models\Eixo;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);
});

function calendarioDe2026(): void
{
    foreach ([
        1 => ['2026-02-04', '2026-04-17'],  // começa numa quarta
        2 => ['2026-04-27', '2026-07-03'],
        3 => ['2026-07-27', '2026-10-02'],
        4 => ['2026-10-13', '2026-12-18'],
    ] as $bimestre => [$inicio, $fim]) {
        BimestreLetivo::query()->create([
            'periodo_letivo' => '2026',
            'bimestre' => $bimestre,
            'inicio' => $inicio,
            'fim' => $fim,
        ]);
    }
}

// --- Data → bimestre e semana --------------------------------------------

it('localiza bimestre e semana de uma data', function (string $data, Bimestre $bimestre, int $semana) {
    calendarioDe2026();

    $local = BimestreLetivo::localizar('2026', Carbon::parse($data));

    expect($local->encontrada())->toBeTrue()
        ->and($local->bimestre)->toBe($bimestre)
        ->and($local->semana)->toBe($semana)
        ->and($local->motivo)->toBeNull();
})->with([
    'o primeiro dia' => ['2026-02-04', Bimestre::Primeiro, 1],
    'a sexta da semana em que começou' => ['2026-02-06', Bimestre::Primeiro, 1],
    'o domingo ainda é a semana 1' => ['2026-02-08', Bimestre::Primeiro, 1],
    'a segunda seguinte abre a semana 2' => ['2026-02-09', Bimestre::Primeiro, 2],
    'semana 3 do 2º bimestre' => ['2026-05-12', Bimestre::Segundo, 3],
    'o último dia' => ['2026-12-18', Bimestre::Quarto, 10],
]);

it('diz que a data cai fora dos bimestres, e não chuta um', function () {
    calendarioDe2026();

    $local = BimestreLetivo::localizar('2026', Carbon::parse('2026-07-14'));

    expect($local->encontrada())->toBeFalse()
        ->and($local->bimestre)->toBeNull()
        ->and($local->motivo)->toBe('14/07/2026 fica fora dos bimestres cadastrados para 2026 (férias ou recesso).');
});

it('diz que o ano letivo não tem calendário', function () {
    calendarioDe2026();

    $local = BimestreLetivo::localizar('2027', Carbon::parse('2027-03-10'));

    expect($local->encontrada())->toBeFalse()
        ->and($local->motivo)->toBe('O calendário letivo de 2027 ainda não foi cadastrado.');
});

// --- Tela ---------------------------------------------------------------

it('abre o formulário do PAEET Admin com as datas gravadas já nos campos', function () {
    calendarioDe2026();

    // Sem `value` o HTML sai com os campos vazios, e só o JavaScript
    // os preencheria depois.
    $this->actingAs($this->admin)
        ->get(route('calendario.index', ['ano' => '2026']))
        ->assertOk()
        ->assertSee('value="2026-02-04"', false)
        ->assertSee('value="2026-04-17"', false)
        ->assertSee('value="2026-10-13"', false)
        ->assertSee('value="2026-12-18"', false);
});

it('deixa o PAEET Admin cadastrar os quatro bimestres de uma vez', function () {
    Livewire::actingAs($this->admin)
        ->test(CalendarioLetivo::class)
        ->set('ano', '2027')
        ->set('datas', [
            1 => ['inicio' => '2027-02-03', 'fim' => '2027-04-16'],
            2 => ['inicio' => '2027-04-26', 'fim' => '2027-07-02'],
            3 => ['inicio' => '2027-07-26', 'fim' => '2027-10-01'],
            4 => ['inicio' => '2027-10-11', 'fim' => '2027-12-17'],
        ])
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertDispatched('notificar');

    expect(BimestreLetivo::query()->where('periodo_letivo', '2027')->count())->toBe(4)
        ->and(BimestreLetivo::query()->where('periodo_letivo', '2027')->where('bimestre', 3)->sole()->inicio->toDateString())
        ->toBe('2027-07-26');
});

it('aceita cadastrar só parte do ano, e apagar um bimestre deixando-o em branco', function () {
    calendarioDe2026();

    Livewire::actingAs($this->admin)
        ->test(CalendarioLetivo::class, ['ano' => '2026'])
        ->set('datas.4', ['inicio' => '', 'fim' => ''])
        ->call('salvar')
        ->assertHasNoErrors();

    expect(BimestreLetivo::query()->where('periodo_letivo', '2026')->pluck('bimestre')->map->value->sort()->values()->all())
        ->toBe([1, 2, 3]);
});

it('carrega as datas gravadas ao trocar de ano', function () {
    calendarioDe2026();

    Livewire::actingAs($this->admin)
        ->test(CalendarioLetivo::class, ['ano' => '2027'])
        ->assertSet('datas.1.inicio', '')
        ->set('ano', '2026')
        ->assertSet('datas.1.inicio', '2026-02-04')
        ->assertSet('datas.4.fim', '2026-12-18');
});

it('recusa fim antes do início, e bimestre pela metade', function () {
    Livewire::actingAs($this->admin)
        ->test(CalendarioLetivo::class, ['ano' => '2027'])
        ->set('datas.1', ['inicio' => '2027-04-16', 'fim' => '2027-02-03'])
        ->set('datas.2', ['inicio' => '2027-04-26', 'fim' => ''])
        ->call('salvar')
        ->assertHasErrors(['datas.1.fim', 'datas.2.fim']);

    expect(BimestreLetivo::query()->count())->toBe(0);
});

it('recusa bimestres fora de ordem ou sobrepostos, dizendo quais', function () {
    Livewire::actingAs($this->admin)
        ->test(CalendarioLetivo::class, ['ano' => '2027'])
        ->set('datas.1', ['inicio' => '2027-02-03', 'fim' => '2027-04-30'])
        ->set('datas.2', ['inicio' => '2027-04-26', 'fim' => '2027-07-02'])
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && str_contains($dados['mensagem'], 'O 2º bimestre começa em 26/04/2027, antes do fim do 1º bimestre (30/04/2027).'));

    expect(BimestreLetivo::query()->count())->toBe(0);
});

it('mostra o calendário ao PAEET e ao professor sem deixar editar', function (string $perfil) {
    calendarioDe2026();
    $usuario = $perfil === 'paeet' ? paeet($this->eixo) : professor($this->eixo);

    $html = $this->actingAs($usuario)
        ->get(route('calendario.index', ['ano' => '2026']))
        ->assertOk()
        ->assertSee('04/02/2026')
        ->getContent();

    expect($html)->not->toContain('type="date"')
        ->and($html)->not->toContain('wire:submit="salvar"');

    Livewire::actingAs($usuario)
        ->test(CalendarioLetivo::class, ['ano' => '2026'])
        ->set('datas.1.inicio', '2026-02-02')
        ->call('salvar')
        ->assertForbidden();

    expect(BimestreLetivo::query()->where('bimestre', 1)->sole()->inicio->toDateString())->toBe('2026-02-04');
})->with(['paeet', 'professor']);

it('marca no seletor o ano que está aberto', function () {
    calendarioDe2026();
    BimestreLetivo::query()->create([
        'periodo_letivo' => '2025', 'bimestre' => 1, 'inicio' => '2025-02-03', 'fim' => '2025-04-17',
    ]);

    $html = $this->actingAs($this->admin)
        ->get(route('calendario.index', ['ano' => '2025']))
        ->getContent();

    expect($html)->toMatch('/<option value="2025"\s+selected/')
        ->and($html)->not->toMatch('/<option value="2026"\s+selected/');
});

/*
 * O `x-input` recebe o tipo pela prop `tipo`. Com `type="date"` solto, o
 * HTML saía com dois `type` — o primeiro, `text`, ganhava — e o campo
 * virava caixa de texto.
 */
it('desenha os campos de data como data, e não como texto', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('calendario.index', ['ano' => '2026']))
        ->getContent();

    preg_match_all('/<input[^>]*id="(inicio|fim)-\d"[^>]*>/', $html, $campos);

    expect($campos[0])->toHaveCount(8);

    foreach ($campos[0] as $campo) {
        expect(substr_count($campo, 'type='))->toBe(1)
            ->and($campo)->toContain('type="date"');
    }
});

/*
 * Data com barra é lida no formato americano: "03/02/2026" passava como
 * 2 de março, sem erro nenhum. O campo de data manda AAAA-MM-DD; o resto
 * é recusado.
 */
it('recusa data em outro formato em vez de lê-la como mês/dia', function () {
    Livewire::actingAs($this->admin)
        ->test(CalendarioLetivo::class, ['ano' => '2027'])
        ->set('datas.1', ['inicio' => '03/02/2027', 'fim' => '2027-04-16'])
        ->call('salvar')
        ->assertHasErrors(['datas.1.inicio']);

    expect(BimestreLetivo::query()->count())->toBe(0);
});
