<?php

/*
 * O quadro de horários diz quem dá qual aula, em que turma, em que dia e
 * hora. É dele que a ausência do professor tira as aulas de um dia.
 *
 * Quem monta é a coordenação do Eixo da turma. As recusas vêm com o
 * motivo escrito, no nível do horário: "Renato já dá aula na 1 B,
 * segunda, 07:00–07:50", e não "conflito de horário".
 */

use App\Enums\DiaDaSemana;
use App\Livewire\Horarios\MinhaSemana;
use App\Livewire\Horarios\QuadroDeHorarios;
use App\Models\Eixo;
use App\Models\ProfessorDisciplina;
use App\Models\QuadroHorario;
use App\Models\Turma;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $this->renato = professor($this->eixo);
    $this->renato->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->marta = professor($this->eixo);
    $this->marta->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes'], 2 => ['Back-end']], autor: $this->paeet);
    $this->disciplinas = $montagem['disciplinas'];

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026']);
    $this->outraTurma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 B', 'periodo_letivo' => '2026']);

    foreach ([[$this->renato, 'Lógica'], [$this->marta, 'Redes']] as [$professor, $disciplina]) {
        ProfessorDisciplina::query()->create([
            'usuario_id' => $professor->id,
            'disciplina_id' => $this->disciplinas[$disciplina]->id,
            'turma_id' => null,
            'ativo' => true,
        ]);
    }

    $this->horario = fn (array $campos = []) => QuadroHorario::query()->create([
        'turma_id' => $this->turma->id,
        'dia_semana' => DiaDaSemana::Segunda,
        'aula_numero' => 1,
        'hora_inicio' => '07:00',
        'hora_fim' => '07:50',
        'disciplina_id' => $this->disciplinas['Lógica']->id,
        'professor_id' => $this->renato->id,
        ...$campos,
    ]);

    $this->formulario = fn (array $campos = []) => Livewire::actingAs($this->paeet)
        ->test(QuadroDeHorarios::class, ['turma' => $this->turma])
        ->set('dia', $campos['dia'] ?? '1')
        ->set('aula_numero', $campos['aula_numero'] ?? '1')
        ->set('hora_inicio', $campos['hora_inicio'] ?? '07:00')
        ->set('hora_fim', $campos['hora_fim'] ?? '07:50')
        ->set('disciplina_id', (string) ($campos['disciplina_id'] ?? $this->disciplinas['Lógica']->id))
        ->set('professor_id', (string) ($campos['professor_id'] ?? $this->renato->id));
});

// --- Cadastro ------------------------------------------------------------

it('deixa a coordenação cadastrar uma aula no quadro da turma', function () {
    ($this->formulario)()
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'sucesso');

    $aula = QuadroHorario::query()->sole();

    expect($aula->turma_id)->toBe($this->turma->id)
        ->and($aula->dia_semana)->toBe(DiaDaSemana::Segunda)
        ->and($aula->aula_numero)->toBe(1)
        ->and($aula->horaInicio())->toBe('07:00')
        ->and($aula->horaFim())->toBe('07:50')
        ->and($aula->professor_id)->toBe($this->renato->id);
});

it('desenha o quadro com as aulas de cada dia', function () {
    ($this->horario)();
    ($this->horario)([
        'dia_semana' => DiaDaSemana::Quarta, 'aula_numero' => 2, 'hora_inicio' => '07:50', 'hora_fim' => '08:40',
        'disciplina_id' => $this->disciplinas['Redes']->id, 'professor_id' => $this->marta->id,
    ]);

    $this->actingAs($this->paeet)
        ->get(route('turmas.horarios', $this->turma))
        ->assertOk()
        ->assertSeeInOrder(['Segunda', 'Quarta'])
        ->assertSee('Lógica')
        ->assertSee('Renato Lima')
        ->assertSee('07:00–07:50')
        ->assertSee('Redes')
        ->assertSee('Marta Reis')
        ->assertSee('07:50–08:40');
});

it('oferece só os professores vinculados à disciplina escolhida', function () {
    Livewire::actingAs($this->paeet)
        ->test(QuadroDeHorarios::class, ['turma' => $this->turma])
        ->set('disciplina_id', (string) $this->disciplinas['Lógica']->id)
        ->assertSee('Renato Lima')
        ->assertDontSee('Marta Reis')
        ->set('disciplina_id', (string) $this->disciplinas['Redes']->id)
        ->assertSee('Marta Reis')
        ->assertDontSee('Renato Lima');
});

it('recusa fim antes do início', function () {
    ($this->formulario)(['hora_inicio' => '08:00', 'hora_fim' => '07:50'])
        ->call('salvar')
        ->assertHasErrors(['hora_fim']);

    expect(QuadroHorario::query()->count())->toBe(0);
});

// --- Regras, cada uma com o motivo ----------------------------------------

it('recusa disciplina que não é do período da turma', function () {
    ProfessorDisciplina::query()->create([
        'usuario_id' => $this->renato->id, 'disciplina_id' => $this->disciplinas['Back-end']->id,
        'turma_id' => null, 'ativo' => true,
    ]);

    ($this->formulario)(['disciplina_id' => $this->disciplinas['Back-end']->id])
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'Back-end não faz parte do 1º período da turma 1 A.');

    expect(QuadroHorario::query()->count())->toBe(0);
});

it('recusa professor que não está vinculado à disciplina', function () {
    ($this->formulario)(['professor_id' => $this->marta->id])
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'Marta Reis não tem vínculo com Lógica. O vínculo é feito no cadastro do usuário.');

    expect(QuadroHorario::query()->count())->toBe(0);
});

it('recusa a mesma aula do dia duas vezes', function () {
    ($this->horario)();

    ($this->formulario)(['disciplina_id' => $this->disciplinas['Redes']->id, 'professor_id' => $this->marta->id,
        'hora_inicio' => '07:00', 'hora_fim' => '07:50'])
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'A 1ª aula de segunda da turma 1 A já é Lógica, com Renato Lima.');

    expect(QuadroHorario::query()->count())->toBe(1);
});

it('recusa aula que se sobrepõe a outra na mesma turma', function () {
    ($this->horario)();

    ($this->formulario)(['aula_numero' => '2', 'hora_inicio' => '07:30', 'hora_fim' => '08:20',
        'disciplina_id' => $this->disciplinas['Redes']->id, 'professor_id' => $this->marta->id])
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'O horário 07:30–08:20 se sobrepõe à 1ª aula de segunda da turma 1 A (07:00–07:50).');

    expect(QuadroHorario::query()->count())->toBe(1);
});

it('recusa o professor em duas turmas ao mesmo tempo', function () {
    ($this->horario)(['turma_id' => $this->outraTurma->id]);

    ($this->formulario)(['hora_inicio' => '07:20', 'hora_fim' => '08:10'])
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'Renato Lima já dá aula na turma 1 B na segunda, 07:00–07:50.');

    expect(QuadroHorario::query()->where('turma_id', $this->turma->id)->count())->toBe(0);
});

it('não vê conflito com turma de outro ano letivo', function () {
    $antiga = Turma::factory()->doCurso($this->turma->curso, $this->turma->grade)
        ->create(['periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2025']);
    ($this->horario)(['turma_id' => $antiga->id]);

    ($this->formulario)()->call('salvar')->assertHasNoErrors();

    expect(QuadroHorario::query()->where('turma_id', $this->turma->id)->count())->toBe(1);
});

// --- Editar e remover ------------------------------------------------------

it('edita uma aula sem duplicá-la e sem acusar conflito com ela mesma', function () {
    $aula = ($this->horario)();

    Livewire::actingAs($this->paeet)
        ->test(QuadroDeHorarios::class, ['turma' => $this->turma])
        ->call('editar', $aula->id)
        ->assertSet('hora_inicio', '07:00')
        ->assertSet('professor_id', (string) $this->renato->id)
        ->set('hora_fim', '08:00')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(QuadroHorario::query()->count())->toBe(1)
        ->and($aula->fresh()->horaFim())->toBe('08:00');
});

it('marca no formulário a disciplina e o professor da aula em edição', function () {
    $aula = ($this->horario)();

    $html = Livewire::actingAs($this->paeet)
        ->test(QuadroDeHorarios::class, ['turma' => $this->turma])
        ->call('editar', $aula->id)
        ->html();

    expect($html)->toMatch('/<option value="'.$this->disciplinas['Lógica']->id.'"\s+selected/')
        ->and($html)->toMatch('/<option value="'.$this->renato->id.'"\s+selected/')
        ->and($html)->toMatch('/<option value="1"\s+selected>Segunda/')
        ->and($html)->toContain('value="07:00"');
});

it('remove uma aula do quadro', function () {
    $aula = ($this->horario)();
    ($this->horario)(['dia_semana' => DiaDaSemana::Terca]);

    Livewire::actingAs($this->paeet)
        ->test(QuadroDeHorarios::class, ['turma' => $this->turma])
        ->call('remover', $aula->id)
        ->assertDispatched('notificar');

    expect(QuadroHorario::query()->count())->toBe(1)
        ->and(QuadroHorario::query()->sole()->dia_semana)->toBe(DiaDaSemana::Terca);
});

it('não edita nem remove aula de outra turma pelo id', function () {
    $alheia = ($this->horario)(['turma_id' => $this->outraTurma->id]);

    $componente = Livewire::actingAs($this->paeet)
        ->test(QuadroDeHorarios::class, ['turma' => $this->turma]);

    expect(fn () => $componente->call('remover', $alheia->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $componente->call('editar', $alheia->id))->toThrow(ModelNotFoundException::class);

    expect($alheia->fresh())->not->toBeNull();
});

// --- Escopo ----------------------------------------------------------------

it('responde 403 ao professor no quadro da turma', function () {
    $this->actingAs($this->renato)->get(route('turmas.horarios', $this->turma))->assertForbidden();
});

it('responde 403 ao PAEET de outro Eixo pelo id da turma', function () {
    $deFora = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    $this->actingAs($deFora)->get(route('turmas.horarios', $this->turma))->assertForbidden();
});

it('leva do detalhe da turma ao quadro de horários', function () {
    $this->actingAs($this->paeet)
        ->get(route('turmas.show', $this->turma))
        ->assertSee(route('turmas.horarios', $this->turma), false);
});

// --- Minha semana ----------------------------------------------------------

it('mostra ao professor as aulas dele na semana, e só as dele', function () {
    ($this->horario)();
    ($this->horario)(['turma_id' => $this->outraTurma->id, 'dia_semana' => DiaDaSemana::Quinta,
        'aula_numero' => 3, 'hora_inicio' => '08:40', 'hora_fim' => '09:30']);
    ($this->horario)(['dia_semana' => DiaDaSemana::Sexta, 'disciplina_id' => $this->disciplinas['Redes']->id,
        'professor_id' => $this->marta->id]);

    $this->actingAs($this->renato)
        ->get(route('horarios.meus'))
        ->assertOk()
        ->assertSeeInOrder(['Segunda', '07:00–07:50', '1 A', 'Quinta', '08:40–09:30', '1 B'])
        ->assertDontSee('Sexta')
        ->assertDontSee('Redes');
});

it('diz ao professor sem aulas no quadro de onde elas vêm', function () {
    $this->actingAs($this->marta)
        ->get(route('horarios.meus'))
        ->assertOk()
        ->assertSee('Nenhuma aula sua no quadro de horários');
});

it('põe Minha semana no menu de quem leciona', function () {
    $this->actingAs($this->renato)->get(route('painel'))->assertSee(route('horarios.meus'), false);
    $this->actingAs(paeetAdmin($this->eixo))->get(route('painel'))->assertDontSee(route('horarios.meus'), false);
});

it('mostra a semana do professor pelo componente, com duas aulas', function () {
    ($this->horario)();
    ($this->horario)(['dia_semana' => DiaDaSemana::Terca]);

    Livewire::actingAs($this->renato)
        ->test(MinhaSemana::class)
        ->assertSeeInOrder(['Segunda', 'Terça']);
});
