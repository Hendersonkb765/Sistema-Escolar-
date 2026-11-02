<?php

/*
 * O calendário letivo e os intervalos são padrões da escola: valem para
 * todas as turmas sem cadastro nenhum. A turma que funciona de outro
 * jeito ganha os próprios, que substituem o padrão só para ela.
 */

use App\Actions\Ausencias\PreverAulasDoDiaAction;
use App\Enums\DiaDaSemana;
use App\Livewire\Calendario\CalendarioLetivo;
use App\Livewire\Turmas\CalendarioEIntervalos;
use App\Models\BimestreLetivo;
use App\Models\Eixo;
use App\Models\ProfessorDisciplina;
use App\Models\QuadroHorario;
use App\Models\Turma;
use App\Models\TurmaBimestre;
use App\Support\IntervaloDoDia;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    $this->logica = $montagem['disciplinas']['Lógica'];
    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026']);
    $this->outra = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 B', 'periodo_letivo' => '2026']);

    foreach ([1 => ['2026-02-04', '2026-04-17'], 2 => ['2026-04-27', '2026-07-03'], 3 => ['2026-07-27', '2026-10-02'], 4 => ['2026-10-13', '2026-12-18']] as $b => [$i, $f]) {
        BimestreLetivo::query()->create(['periodo_letivo' => '2026', 'bimestre' => $b, 'inicio' => $i, 'fim' => $f]);
    }

    $this->tela = fn ($quem = null) => Livewire::actingAs($quem ?? $this->paeet)
        ->test(CalendarioEIntervalos::class, ['turma' => $this->turma]);
});

// --- O padrão ------------------------------------------------------------

it('tem como padrão os intervalos das 17h, de 20 minutos, e das 19h, de 50, para todas as turmas', function () {
    expect(IntervaloDoDia::padrao()->map->intervalo()->all())->toBe(['17:00–17:20', '19:00–19:50'])
        ->and(IntervaloDoDia::padrao()->map->minutos()->all())->toBe([20, 50])
        ->and(IntervaloDoDia::daTurma($this->turma)->map->intervalo()->all())->toBe(['17:00–17:20', '19:00–19:50']);
});

it('abre dizendo que a turma segue o padrão da escola, com as datas e os intervalos', function () {
    $this->actingAs($this->paeet)
        ->get(route('turmas.calendario', $this->turma))
        ->assertOk()
        ->assertSee('Segue o calendário letivo da escola de 2026.')
        ->assertSeeInOrder(['1º bimestre', '04/02/2026 a 17/04/2026', '4º bimestre', '13/10/2026 a 18/12/2026'])
        ->assertSeeInOrder(['17:00–17:20 · 20 min', '19:00–19:50 · 50 min'])
        ->assertSee('Definir calendário próprio');
});

it('leva da turma à tela de calendário e intervalos', function () {
    $this->actingAs($this->paeet)
        ->get(route('turmas.show', $this->turma))
        ->assertSee(route('turmas.calendario', $this->turma), false);
});

// --- Calendário próprio --------------------------------------------------

it('abre o calendário próprio já preenchido com as datas da escola', function () {
    $html = ($this->tela)()
        ->call('definirCalendario')
        ->assertSet('datas.2.inicio', '2026-04-27')
        ->html();

    expect($html)->toContain('value="2026-04-27"')
        ->and($html)->toContain('value="2026-12-18"');
});

it('grava o calendário próprio, que passa a valer só para a turma', function () {
    ($this->tela)()
        ->call('definirCalendario')
        ->set('datas.2.inicio', '2026-05-04')
        ->call('salvarCalendario')
        ->assertHasNoErrors()
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'sucesso')
        ->assertSee('Próprio da turma')
        ->assertSee('04/05/2026 a 03/07/2026');

    expect(TurmaBimestre::query()->where('turma_id', $this->turma->id)->count())->toBe(4);

    // 28/04 é do 2º bimestre da escola, e não da 1 A, que só começa em 04/05.
    $dia = Carbon::parse('2026-04-28');

    expect(BimestreLetivo::localizarNaTurma($this->turma->fresh(), $dia)->motivo)
        ->toBe('28/04/2026 fica fora dos bimestres do calendário próprio da turma 1 A (férias ou recesso).')
        ->and(BimestreLetivo::localizarNaTurma($this->outra, $dia)->bimestre->value)->toBe(2);
});

it('recusa calendário próprio fora de ordem, ou sem bimestre nenhum', function () {
    ($this->tela)()
        ->call('definirCalendario')
        ->set('datas.2.inicio', '2026-04-10')
        ->call('salvarCalendario')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'O 2º bimestre começa em 10/04/2026, antes do fim do 1º bimestre (17/04/2026).');

    $tela = ($this->tela)()->call('definirCalendario');

    foreach ([1, 2, 3, 4] as $b) {
        $tela->set("datas.{$b}", ['inicio' => '', 'fim' => '']);
    }

    $tela->call('salvarCalendario')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['mensagem'] === 'Informe as datas de ao menos um bimestre, ou volte ao calendário da escola.');

    expect(TurmaBimestre::query()->count())->toBe(0);
});

it('volta ao calendário da escola apagando o próprio', function () {
    TurmaBimestre::query()->create(['turma_id' => $this->turma->id, 'bimestre' => 1, 'inicio' => '2026-03-02', 'fim' => '2026-04-30']);
    TurmaBimestre::query()->create(['turma_id' => $this->turma->id, 'bimestre' => 2, 'inicio' => '2026-05-04', 'fim' => '2026-07-10']);

    ($this->tela)()
        ->assertSee('Próprio da turma')
        ->call('voltarAoCalendarioDaEscola')
        ->assertSee('Segue o calendário letivo da escola de 2026.');

    expect(TurmaBimestre::query()->count())->toBe(0);
});

it('acha a aula do escopo da ausência pelo calendário próprio da turma', function () {
    Carbon::setTestNow('2026-04-20 08:00');
    $professor = professor($this->eixo);
    ProfessorDisciplina::query()->create(['usuario_id' => $professor->id, 'disciplina_id' => $this->logica->id, 'turma_id' => null, 'ativo' => true]);
    QuadroHorario::query()->create([
        'turma_id' => $this->turma->id, 'dia_semana' => DiaDaSemana::Terca, 'aula_numero' => 1,
        'hora_inicio' => '07:00', 'hora_fim' => '07:50', 'disciplina_id' => $this->logica->id, 'professor_id' => $professor->id,
    ]);
    // A 1 A começa o 2º bimestre uma semana depois da escola.
    TurmaBimestre::query()->create(['turma_id' => $this->turma->id, 'bimestre' => 2, 'inicio' => '2026-05-04', 'fim' => '2026-07-10']);

    $aula = app(PreverAulasDoDiaAction::class)->executar($professor, Carbon::parse('2026-05-12'))->aulas->sole();

    // Pela escola seria a semana 3; pela turma, a 2.
    expect($aula->local->semana)->toBe(2);

    Carbon::setTestNow();
});

it('lista no calendário da escola as turmas com calendário próprio', function () {
    TurmaBimestre::query()->create(['turma_id' => $this->turma->id, 'bimestre' => 2, 'inicio' => '2026-05-04', 'fim' => '2026-07-10']);
    TurmaBimestre::query()->create(['turma_id' => $this->outra->id, 'bimestre' => 2, 'inicio' => '2026-05-11', 'fim' => '2026-07-10']);

    Livewire::actingAs(paeetAdmin($this->eixo))->test(CalendarioLetivo::class, ['ano' => '2026'])
        ->assertSee('Turmas com calendário próprio')
        ->assertSeeInOrder(['1 A', '1 B'])
        ->assertSeeHtml(route('turmas.calendario', $this->turma));
});

// --- Intervalos ----------------------------------------------------------

it('mostra os intervalos que valem para a turma, sem deixar editar ali', function () {
    config(['instituicao.intervalos' => [
        ['nome' => 'Intervalo', 'inicio' => '17:00', 'minutos' => 20, 'alcance' => 'todas', 'turmas' => []],
        ['nome' => 'Intervalo', 'inicio' => '19:00', 'minutos' => 50, 'alcance' => 'exceto', 'turmas' => [$this->turma->id]],
        ['nome' => 'Intervalo da 1 A', 'inicio' => '19:30', 'minutos' => 50, 'alcance' => 'somente', 'turmas' => [$this->turma->id]],
    ]]);

    $html = ($this->tela)()
        ->assertSee('Definidos pelo PAEET Admin em Configuração da escola')
        ->assertDontSee('19:00–19:50')
        ->assertDontSee('Definir intervalos próprios')
        // O PAEET não configura a escola: o atalho é só do Admin.
        ->assertDontSeeHtml(route('configuracao.escola'))
        ->html();

    expect($html)->toMatch('/Intervalo.*Padrão da escola.*17:00–17:20 · 20 min.*Intervalo da 1 A.*Só de turmas escolhidas.*19:30–20:20 · 50 min/s');

    $daOutra = Livewire::actingAs($this->paeet)->test(CalendarioEIntervalos::class, ['turma' => $this->outra])->html();

    expect($daOutra)->toContain('19:00–19:50')
        ->and($daOutra)->not->toContain('19:30–20:20');
});

it('leva o PAEET Admin da turma à configuração da escola', function () {
    Livewire::actingAs(paeetAdmin($this->eixo))->test(CalendarioEIntervalos::class, ['turma' => $this->turma])
        ->assertSeeHtml(route('configuracao.escola'));
});

// --- Acesso --------------------------------------------------------------

it('não deixa o professor nem o PAEET de outro Eixo mexer na turma', function () {
    $this->actingAs(professor($this->eixo))->get(route('turmas.calendario', $this->turma))->assertForbidden();
    $this->actingAs(paeet(Eixo::factory()->create(['codigo' => 'ADM'])))->get(route('turmas.calendario', $this->turma))->assertForbidden();
});
