<?php

/*
 * Quem dá aula chega à semana e às ausências pelo painel, sem passar pelo
 * menu. Cada atalho já diz o essencial: quantas aulas há hoje e qual é a
 * próxima ausência marcada.
 *
 * O critério é o mesmo do menu: ter vínculo com alguma disciplina. O
 * PAEET que não dá aula não vê os atalhos.
 */

use App\Enums\DiaDaSemana;
use App\Enums\StatusAusencia;
use App\Enums\StatusTurma;
use App\Models\Ausencia;
use App\Models\Eixo;
use App\Models\ProfessorDisciplina;
use App\Models\QuadroHorario;
use App\Models\Turma;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Terça.
    Carbon::setTestNow('2026-05-12 08:00');

    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->renato = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    $this->logica = $montagem['disciplinas']['Lógica'];
    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026']);

    $this->vincular = fn ($usuario) => ProfessorDisciplina::query()->create([
        'usuario_id' => $usuario->id, 'disciplina_id' => $this->logica->id, 'turma_id' => null, 'ativo' => true,
    ]);
    ($this->vincular)($this->renato);

    $this->aula = fn (DiaDaSemana $dia, int $numero, ?Turma $turma = null) => QuadroHorario::query()->create([
        'turma_id' => ($turma ?? $this->turma)->id, 'dia_semana' => $dia, 'aula_numero' => $numero,
        'hora_inicio' => sprintf('%02d:00', 6 + $numero), 'hora_fim' => sprintf('%02d:50', 6 + $numero),
        'disciplina_id' => $this->logica->id, 'professor_id' => $this->renato->id,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('mostra os atalhos com as aulas de hoje e a próxima ausência', function () {
    ($this->aula)(DiaDaSemana::Terca, 1);
    ($this->aula)(DiaDaSemana::Terca, 2);
    ($this->aula)(DiaDaSemana::Quarta, 1);

    $proxima = Ausencia::query()->create(['professor_id' => $this->renato->id, 'data' => '2026-05-19']);
    Ausencia::query()->create(['professor_id' => $this->renato->id, 'data' => '2026-06-02']);

    $this->actingAs($this->renato)
        ->get(route('painel'))
        ->assertOk()
        ->assertSee(route('horarios.meus'), false)
        ->assertSee(route('ausencias.index'), false)
        ->assertSee(route('ausencias.criar'), false)
        ->assertSee('2 aula(s) sua(s) hoje.')
        ->assertSee('Terça, 19/05/2026')
        ->assertSee(route('ausencias.show', $proxima), false)
        ->assertSee('Aguardando ciência')
        ->assertDontSee('02/06/2026');
});

it('não conta aula de turma que não está ativa', function () {
    ($this->aula)(DiaDaSemana::Terca, 1);
    $encerrada = Turma::factory()->create(['curso_id' => $this->turma->curso_id, 'grade_curricular_id' => $this->turma->grade_curricular_id,
        'periodo' => 1, 'nome' => '1 Z', 'periodo_letivo' => '2025', 'status' => StatusTurma::Concluida]);
    ($this->aula)(DiaDaSemana::Terca, 2, $encerrada);

    $this->actingAs($this->renato)
        ->get(route('painel'))
        ->assertSee('1 aula(s) sua(s) hoje.');
});

it('diz quando não há aula hoje nem ausência marcada, ignorando as passadas e as canceladas', function () {
    ($this->aula)(DiaDaSemana::Quarta, 1);
    Ausencia::query()->create(['professor_id' => $this->renato->id, 'data' => '2026-05-05']);
    Ausencia::query()->create([
        'professor_id' => $this->renato->id, 'data' => '2026-05-20', 'status' => StatusAusencia::Cancelada,
    ]);

    $this->actingAs($this->renato)
        ->get(route('painel'))
        ->assertSee('Nenhuma aula sua hoje.')
        ->assertSee('Nenhuma ausência marcada.')
        ->assertDontSee('20/05/2026');
});

it('não mostra a ausência de outro professor como próxima', function () {
    $marta = professor($this->eixo);
    ($this->vincular)($marta);
    Ausencia::query()->create(['professor_id' => $marta->id, 'data' => '2026-05-19']);

    $this->actingAs($this->renato)
        ->get(route('painel'))
        ->assertSee('Nenhuma ausência marcada.');
});

it('mostra os atalhos ao PAEET que dá aula, e não ao que não dá', function () {
    $this->actingAs($this->paeet)
        ->get(route('painel'))
        ->assertDontSee(route('ausencias.criar'), false)
        ->assertDontSee('Minhas ausências');

    ($this->vincular)($this->paeet);

    $this->actingAs($this->paeet->fresh())
        ->get(route('painel'))
        ->assertSee(route('ausencias.criar'), false)
        ->assertSee(route('horarios.meus'), false);
});
