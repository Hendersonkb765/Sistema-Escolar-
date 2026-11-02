<?php

/*
 * Os eventos e feriados da escola ficam no Calendário letivo, para que
 * todos saibam. O PAEET Admin cadastra; professores e PAEETs consultam.
 *
 * Feriado e recesso são dias sem aula: neles a ausência não é informada,
 * e a tela diz por quê.
 *
 * Hoje é segunda, 05/10/2026.
 */

use App\Actions\Ausencias\PreverAulasDoDiaAction;
use App\Enums\DiaDaSemana;
use App\Enums\TipoDeEvento;
use App\Livewire\Calendario\EventosDaEscola;
use App\Models\Eixo;
use App\Models\EventoEscolar;
use App\Models\ProfessorDisciplina;
use App\Models\QuadroHorario;
use App\Models\Turma;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 10:00');

    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);

    $this->evento = fn (array $campos = []) => EventoEscolar::query()->create([
        'titulo' => 'Nossa Senhora Aparecida', 'tipo' => TipoDeEvento::Feriado,
        'inicio' => '2026-10-12', 'fim' => '2026-10-12', ...$campos,
    ]);

    $this->formulario = fn (array $campos = [], $quem = null) => Livewire::actingAs($quem ?? $this->admin)
        ->test(EventosDaEscola::class, ['ano' => '2026'])
        ->set('titulo', $campos['titulo'] ?? 'Conselho de classe')
        ->set('tipo', $campos['tipo'] ?? 'reuniao')
        ->set('inicio', $campos['inicio'] ?? '2026-10-20')
        ->set('fim', $campos['fim'] ?? '')
        ->set('descricao', $campos['descricao'] ?? '');
});

afterEach(fn () => Carbon::setTestNow());

// --- Cadastro ------------------------------------------------------------

it('deixa o PAEET Admin cadastrar um evento de um dia', function () {
    ($this->formulario)(['descricao' => 'Após a 7ª aula, na sala dos professores.'])
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'sucesso'
            && $dados['mensagem'] === 'Reunião "Conselho de classe" em 20/10/2026 salvo no calendário.');

    $evento = EventoEscolar::query()->sole();

    expect($evento->tipo)->toBe(TipoDeEvento::Reuniao)
        ->and($evento->inicio->toDateString())->toBe('2026-10-20')
        // Sem "até", é um dia só.
        ->and($evento->fim->toDateString())->toBe('2026-10-20')
        ->and($evento->descricao)->toBe('Após a 7ª aula, na sala dos professores.');
});

it('cadastra um período, como o recesso', function () {
    ($this->formulario)(['titulo' => 'Recesso de fim de ano', 'tipo' => 'recesso', 'inicio' => '2026-12-21', 'fim' => '2026-12-31'])
        ->call('salvar')
        ->assertHasNoErrors();

    expect(EventoEscolar::query()->sole()->periodo())->toBe('21/12/2026 a 31/12/2026');
});

it('recusa o fim antes do início, e o que falta', function () {
    ($this->formulario)(['titulo' => '', 'inicio' => '2026-10-20', 'fim' => '2026-10-19'])
        ->call('salvar')
        ->assertHasErrors(['titulo', 'fim']);

    // "20/10/2026" no formato brasileiro seria lido como mês/dia.
    ($this->formulario)(['inicio' => '20/10/2026'])->call('salvar')->assertHasErrors(['inicio']);

    expect(EventoEscolar::query()->count())->toBe(0);
});

it('edita um evento, com os campos já preenchidos', function () {
    $evento = ($this->evento)();
    ($this->evento)(['titulo' => 'Finados', 'inicio' => '2026-11-02', 'fim' => '2026-11-02']);

    $tela = Livewire::actingAs($this->admin)->test(EventosDaEscola::class, ['ano' => '2026'])
        ->call('editar', $evento->id)
        ->assertSet('titulo', 'Nossa Senhora Aparecida')
        ->assertSet('fim', '');

    expect($tela->html())->toMatch('/<option value="feriado"\s+selected/')
        ->and($tela->html())->toContain('value="2026-10-12"');

    $tela->set('fim', '2026-10-13')->call('salvar')->assertHasNoErrors();

    expect($evento->fresh()->periodo())->toBe('12/10/2026 a 13/10/2026')
        ->and(EventoEscolar::query()->count())->toBe(2);
});

it('remove um evento', function () {
    $evento = ($this->evento)();
    ($this->evento)(['titulo' => 'Finados', 'inicio' => '2026-11-02', 'fim' => '2026-11-02']);

    Livewire::actingAs($this->admin)->test(EventosDaEscola::class, ['ano' => '2026'])
        ->call('remover', $evento->id)
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'sucesso');

    expect(EventoEscolar::query()->pluck('titulo')->all())->toBe(['Finados']);
});

// --- Consulta ------------------------------------------------------------

it('mostra os eventos do ano no calendário letivo, a todos, em ordem', function (string $perfil) {
    ($this->evento)(['titulo' => 'Finados', 'inicio' => '2026-11-02', 'fim' => '2026-11-02']);
    ($this->evento)();
    ($this->evento)(['titulo' => 'Carnaval', 'inicio' => '2026-02-16', 'fim' => '2026-02-17', 'tipo' => TipoDeEvento::Recesso]);
    ($this->evento)(['titulo' => 'Feriado de 2027', 'inicio' => '2027-01-01', 'fim' => '2027-01-01']);

    $usuario = $perfil === 'professor' ? professor($this->eixo) : paeet($this->eixo);

    $html = $this->actingAs($usuario)
        ->get(route('calendario.index', ['ano' => '2026']))
        ->assertOk()
        ->assertSeeInOrder(['Carnaval', 'Já passou', 'Nossa Senhora Aparecida', 'Finados'])
        ->assertSee('16/02/2026 a 17/02/2026')
        ->assertDontSee('Feriado de 2027')
        // Só o PAEET Admin cadastra.
        ->assertDontSee('Incluir evento ou feriado')
        ->assertDontSee('wire:click="remover', false)
        ->getContent();

    expect(substr_count($html, 'data-evento'))->toBe(3);
})->with(['professor', 'paeet']);

it('não deixa o PAEET nem o professor cadastrar, editar ou remover', function () {
    $evento = ($this->evento)();

    foreach ([paeet($this->eixo), professor($this->eixo)] as $usuario) {
        ($this->formulario)([], $usuario)->call('salvar')->assertForbidden();
        Livewire::actingAs($usuario)->test(EventosDaEscola::class, ['ano' => '2026'])->call('editar', $evento->id)->assertForbidden();
        Livewire::actingAs($usuario)->test(EventosDaEscola::class, ['ano' => '2026'])->call('remover', $evento->id)->assertForbidden();
    }

    expect(EventoEscolar::query()->count())->toBe(1);
});

it('mostra no painel os eventos dos próximos 30 dias', function () {
    ($this->evento)();
    ($this->evento)(['titulo' => 'Conselho de classe', 'tipo' => TipoDeEvento::Reuniao, 'inicio' => '2026-10-20', 'fim' => '2026-10-20']);
    ($this->evento)(['titulo' => 'Já foi', 'inicio' => '2026-09-07', 'fim' => '2026-09-07']);
    ($this->evento)(['titulo' => 'Recesso de fim de ano', 'tipo' => TipoDeEvento::Recesso, 'inicio' => '2026-12-21', 'fim' => '2026-12-31']);

    $html = $this->actingAs(professor($this->eixo))
        ->get(route('painel'))
        ->assertSee('Próximos eventos')
        ->assertSeeInOrder(['12/10/2026', 'Nossa Senhora Aparecida', 'Feriado · sem aula', '20/10/2026', 'Conselho de classe', 'Reunião'])
        ->assertDontSee('Já foi')
        ->assertDontSee('Recesso de fim de ano')
        ->getContent();

    expect(substr_count($html, 'data-proximo-evento'))->toBe(2);
});

it('não mostra o card no painel quando não há evento por vir', function () {
    $this->actingAs(professor($this->eixo))->get(route('painel'))->assertDontSee('Próximos eventos');
});

// --- Dia sem aula --------------------------------------------------------

it('não deixa informar ausência em feriado ou recesso, dizendo qual', function () {
    $professor = professor($this->eixo);
    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']]);
    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create(['periodo' => 1, 'nome' => '1 A']);
    ProfessorDisciplina::query()->create(['usuario_id' => $professor->id, 'disciplina_id' => $montagem['disciplinas']['Lógica']->id, 'turma_id' => null, 'ativo' => true]);
    QuadroHorario::query()->create([
        'turma_id' => $turma->id, 'dia_semana' => DiaDaSemana::Segunda, 'aula_numero' => 1,
        'hora_inicio' => '07:00', 'hora_fim' => '07:50', 'disciplina_id' => $montagem['disciplinas']['Lógica']->id, 'professor_id' => $professor->id,
    ]);

    ($this->evento)();
    ($this->evento)(['titulo' => 'Recesso de fim de ano', 'tipo' => TipoDeEvento::Recesso, 'inicio' => '2026-12-21', 'fim' => '2026-12-31']);
    // Reunião não tira a aula.
    ($this->evento)(['titulo' => 'Conselho de classe', 'tipo' => TipoDeEvento::Reuniao, 'inicio' => '2026-10-19', 'fim' => '2026-10-19']);

    $prever = fn (string $data) => app(PreverAulasDoDiaAction::class)->executar($professor, Carbon::parse($data));

    expect($prever('2026-10-12')->motivo)
        ->toBe('12/10/2026 é dia sem aula no calendário da escola: Nossa Senhora Aparecida (Feriado, 12/10/2026).')
        ->and($prever('2026-12-28')->motivo)
        ->toBe('28/12/2026 é dia sem aula no calendário da escola: Recesso de fim de ano (Recesso, 21/12/2026 a 31/12/2026).')
        ->and($prever('2026-10-19')->vazio())->toBeFalse();
});
