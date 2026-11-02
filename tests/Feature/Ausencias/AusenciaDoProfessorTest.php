<?php

/*
 * O professor avisa com antecedência o dia em que não estará. As aulas
 * daquele dia vêm do quadro de horários; a aula do escopo de cada uma,
 * do calendário letivo e do escopo-sequência. Ele confere, troca a
 * sugestão se precisar e escreve o que planejou.
 *
 * Quando o sistema não acha a aula do escopo, diz por quê, na aula, e
 * o conteúdo escrito passa a ser obrigatório.
 *
 * O cenário: segunda, 04/05/2026. O 2º bimestre começou na segunda
 * 27/04, então a terça 12/05 cai na semana 3.
 */

use App\Actions\Ausencias\PreverAulasDoDiaAction;
use App\Actions\Ausencias\RegistrarAusenciaAction;
use App\Enums\DiaDaSemana;
use App\Enums\StatusAusencia;
use App\Enums\StatusTurma;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Ausencias\InformarAusencia;
use App\Livewire\Ausencias\MinhasAusencias;
use App\Models\Ausencia;
use App\Models\BimestreLetivo;
use App\Models\Eixo;
use App\Models\EscopoAula;
use App\Models\ProfessorDisciplina;
use App\Models\QuadroHorario;
use App\Models\Turma;
use App\Support\AulaPrevista;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-05-04 09:00');

    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $this->renato = professor($this->eixo);
    $this->renato->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->marta = professor($this->eixo);
    $this->marta->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], autor: $this->paeet);
    $this->curso = $montagem['curso'];
    $this->logica = $montagem['disciplinas']['Lógica'];
    $this->redes = $montagem['disciplinas']['Redes'];

    $this->turmaA = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026']);
    $this->turmaB = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 B', 'periodo_letivo' => '2026']);

    foreach ([[$this->renato, $this->logica], [$this->marta, $this->redes]] as [$professor, $disciplina]) {
        ProfessorDisciplina::query()->create([
            'usuario_id' => $professor->id, 'disciplina_id' => $disciplina->id, 'turma_id' => null, 'ativo' => true,
        ]);
    }

    BimestreLetivo::query()->create([
        'periodo_letivo' => '2026', 'bimestre' => 2, 'inicio' => '2026-04-27', 'fim' => '2026-07-03',
    ]);

    $horario = fn (Turma $turma, DiaDaSemana $dia, int $numero, string $inicio, string $fim, $professor = null, $disciplina = null) => QuadroHorario::query()->create([
        'turma_id' => $turma->id, 'dia_semana' => $dia, 'aula_numero' => $numero,
        'hora_inicio' => $inicio, 'hora_fim' => $fim,
        'disciplina_id' => ($disciplina ?? $this->logica)->id, 'professor_id' => ($professor ?? $this->renato)->id,
    ]);

    // Terça do Renato: duas aulas de Lógica na 1 A e uma na 1 B.
    $this->a1 = $horario($this->turmaA, DiaDaSemana::Terca, 1, '07:00', '07:50');
    $this->a2 = $horario($this->turmaA, DiaDaSemana::Terca, 2, '07:50', '08:40');
    $this->b3 = $horario($this->turmaB, DiaDaSemana::Terca, 3, '08:40', '09:30');
    // A quinta da 1 A é a 3ª aula de Lógica da semana dela.
    $this->a1Quinta = $horario($this->turmaA, DiaDaSemana::Quinta, 1, '07:00', '07:50');
    // Marta também dá aula na terça, e nada dela entra na ausência do Renato.
    $horario($this->turmaA, DiaDaSemana::Terca, 4, '09:30', '10:20', $this->marta, $this->redes);

    $this->escopo = function (string $titulo, int $semana = 3, ?int $disciplinaId = null) {
        static $ordem = 0;

        return EscopoAula::query()->create([
            'curso_id' => $this->curso->id, 'periodo' => 1,
            'disciplina_id' => $disciplinaId ?? $this->logica->id,
            'bimestre' => 2, 'semana' => $semana, 'ordem' => ++$ordem,
            'componente_nome' => 'Lógica', 'titulo_aula' => $titulo,
        ]);
    };

    $this->lacos = ($this->escopo)('Laços de repetição');
    $this->aninhados = ($this->escopo)('Laços aninhados');
    $this->exercicios = ($this->escopo)('Exercícios de laços');

    $this->prever = fn (string $data, $professor = null) => app(PreverAulasDoDiaAction::class)
        ->executar($professor ?? $this->renato, Carbon::parse($data));

    $this->tela = fn (string $data) => Livewire::actingAs($this->renato)
        ->test(InformarAusencia::class)
        ->set('data', $data);
});

afterEach(fn () => Carbon::setTestNow());

// --- As aulas do dia -----------------------------------------------------

it('tira do quadro as aulas do professor no dia, com a aula do escopo de cada uma', function () {
    $previstas = ($this->prever)('2026-05-12');

    expect($previstas->vazio())->toBeFalse()
        ->and($previstas->aulas->map(fn (AulaPrevista $a) => $a->quadro->id)->all())
        ->toBe([$this->a1->id, $this->a2->id, $this->b3->id]);

    [$primeira, $segunda, $naOutraTurma] = $previstas->aulas->all();

    expect($primeira->local->bimestre->value)->toBe(2)
        ->and($primeira->local->semana)->toBe(3)
        // A 1ª aula de Lógica da semana da 1 A leva a 1ª do escopo, a 2ª leva a 2ª.
        ->and($primeira->sugerida->id)->toBe($this->lacos->id)
        ->and($segunda->sugerida->id)->toBe($this->aninhados->id)
        // Na 1 B, a terça é a 1ª aula de Lógica da semana.
        ->and($naOutraTurma->sugerida->id)->toBe($this->lacos->id)
        ->and($primeira->opcoes)->toHaveCount(3)
        ->and($primeira->motivo)->toBeNull();
});

it('não oferece aula do escopo de outra disciplina nem de outra semana', function () {
    ($this->escopo)('Vetores', semana: 4);
    ($this->escopo)('Topologias', disciplinaId: $this->redes->id);

    $titulos = ($this->prever)('2026-05-12')->aulas->first()->opcoes->pluck('titulo_aula')->all();

    expect($titulos)->toBe(['Laços de repetição', 'Laços aninhados', 'Exercícios de laços']);
});

it('diz por que não há sugestão quando o dia cai fora dos bimestres', function () {
    // 14/07/2026 é terça, depois do fim do 2º bimestre.
    $aula = ($this->prever)('2026-07-14')->aulas->first();

    expect($aula->sugerida)->toBeNull()
        ->and($aula->opcoes)->toBeEmpty()
        ->and($aula->motivo)->toBe('14/07/2026 fica fora dos bimestres cadastrados para 2026 (férias ou recesso).');
});

it('diz por que não há sugestão quando o escopo não tem aula na semana', function () {
    $aula = ($this->prever)('2026-05-19')->aulas->first();

    expect($aula->opcoes)->toBeEmpty()
        ->and($aula->motivo)->toBe('O escopo-sequência não tem aula de Lógica para o 1º período na semana 4 do 2º bimestre.');
});

it('não chuta quando a turma tem mais aulas na semana que o escopo', function () {
    EscopoAula::query()->whereKey([$this->aninhados->id, $this->exercicios->id])->delete();

    [$primeira, $segunda] = ($this->prever)('2026-05-12')->aulas->all();

    expect($primeira->sugerida->id)->toBe($this->lacos->id)
        ->and($segunda->sugerida)->toBeNull()
        ->and($segunda->opcoes)->toHaveCount(1)
        ->and($segunda->motivo)->toBe(
            'O escopo traz 1 aula(s) de Lógica na semana 3, e esta é a 2ª da turma 1 A na semana. Escolha uma ou descreva o conteúdo.'
        );
});

it('explica o dia sem aula', function (string $data, string $motivo) {
    $previstas = ($this->prever)($data);

    expect($previstas->vazio())->toBeTrue()
        ->and($previstas->motivo)->toBe($motivo);
})->with([
    'quarta, sem aula no quadro' => ['2026-05-13', 'Você não tem aula no quadro de horários em 13/05/2026 (Quarta). Se faltar alguma aula no quadro, avise a coordenação.'],
    'domingo' => ['2026-05-17', '17/05/2026 é domingo, e não há aula aos domingos.'],
]);

it('deixa de fora as turmas que não estão ativas', function () {
    $this->turmaB->update(['status' => StatusTurma::Concluida]);

    expect(($this->prever)('2026-05-12')->aulas->map(fn (AulaPrevista $a) => $a->quadro->id)->all())
        ->toBe([$this->a1->id, $this->a2->id]);
});

// --- A tela --------------------------------------------------------------

it('mostra as aulas do dia com a sugestão já marcada', function () {
    $html = ($this->tela)('2026-05-12')
        ->assertSee('1ª aula, 07:00–07:50 · Lógica · turma 1 A')
        ->assertSee('2ª aula, 07:50–08:40 · Lógica · turma 1 A')
        ->assertSee('3ª aula, 08:40–09:30 · Lógica · turma 1 B')
        ->assertSee('2º bimestre, semana 3 do escopo')
        ->assertDontSee('Redes')
        ->assertSet("planos.{$this->a1->id}.escopo_aula_id", (string) $this->lacos->id)
        ->assertSet("planos.{$this->a2->id}.escopo_aula_id", (string) $this->aninhados->id)
        ->html();

    // Sem `@selected` o HTML chega com a primeira opção marcada, e quem
    // envia sem mexer manda outra coisa do que vê.
    expect($html)->toMatch('/<option value="'.$this->lacos->id.'"\s+selected/')
        ->and($html)->toMatch('/<option value="'.$this->aninhados->id.'"\s+selected/')
        ->and($html)->toContain('Laços de repetição (sugerida)')
        ->and(substr_count($html, 'Pronta para enviar'))->toBe(3);
});

it('registra a ausência com as sugestões e o conteúdo escrito', function () {
    ($this->tela)('2026-05-12')
        ->set('motivo', 'Consulta médica')
        ->set("planos.{$this->a2->id}.conteudo", 'Terminar a lista 3 em dupla.')
        ->call('informar')
        ->assertHasNoErrors()
        ->assertRedirect(route('ausencias.show', Ausencia::query()->sole()));

    $ausencia = Ausencia::query()->with('aulas')->sole();

    expect($ausencia->professor_id)->toBe($this->renato->id)
        ->and($ausencia->data->toDateString())->toBe('2026-05-12')
        ->and($ausencia->motivo)->toBe('Consulta médica')
        ->and($ausencia->status)->toBe(StatusAusencia::Pendente)
        ->and($ausencia->aulas->pluck('escopo_titulo')->all())
        ->toBe(['Laços de repetição', 'Laços aninhados', 'Laços de repetição'])
        ->and($ausencia->aulas->pluck('conteudo_planejado')->all())
        ->toBe([null, 'Terminar a lista 3 em dupla.', null])
        ->and($ausencia->aulas->pluck('turma_id')->all())
        ->toBe([$this->turmaA->id, $this->turmaA->id, $this->turmaB->id]);
});

it('grava a aula do escopo que o professor escolheu no lugar da sugestão', function () {
    ($this->tela)('2026-05-12')
        ->set("planos.{$this->a1->id}.escopo_aula_id", (string) $this->exercicios->id)
        ->call('informar')
        ->assertHasNoErrors();

    expect(Ausencia::query()->sole()->aulas()->where('quadro_horario_id', $this->a1->id)->sole()->escopo_aula_id)
        ->toBe($this->exercicios->id);
});

it('exige o conteúdo escrito quando não há aula do escopo, dizendo em qual aula', function () {
    ($this->tela)('2026-07-14')
        ->assertSee('fica fora dos bimestres cadastrados')
        ->set("planos.{$this->a1->id}.conteudo", 'Revisão para a recuperação.')
        ->call('informar')
        ->assertHasErrors(["planos.{$this->a2->id}", "planos.{$this->b3->id}"])
        ->assertHasNoErrors(["planos.{$this->a1->id}"])
        ->assertSee('2ª aula, 07:50–08:40 · Lógica · turma 1 A: descreva o conteúdo planejado.');

    expect(Ausencia::query()->count())->toBe(0);
});

it('aceita a aula sem escopo quando o conteúdo está escrito', function () {
    $tela = ($this->tela)('2026-07-14');

    foreach ([$this->a1, $this->a2, $this->b3] as $horario) {
        $tela->set("planos.{$horario->id}.conteudo", 'Revisão para a recuperação.');
    }

    $tela->call('informar')->assertHasNoErrors();

    expect(Ausencia::query()->sole()->aulas()->whereNull('escopo_aula_id')->count())->toBe(3);
});

it('trava o envio e diz qual aula falta quando o professor tira a sugestão sem escrever nada', function () {
    $html = ($this->tela)('2026-05-12')
        ->set("planos.{$this->a2->id}.escopo_aula_id", '')
        ->assertSee('Falta definir o conteúdo')
        ->html();

    expect($html)->toMatch('/<button[^>]*type="submit"[^>]*\sdisabled[\s>]/')
        ->and($html)->toContain('title="2ª aula, 07:50–08:40 · Lógica · turma 1 A: escolha a aula do escopo ou descreva o conteúdo planejado."')
        ->and(substr_count($html, 'Pronta para enviar'))->toBe(2);
});

it('explica na tela o dia sem aula e não deixa enviar', function () {
    $html = ($this->tela)('2026-05-13')
        ->assertSee('Nenhuma aula nesse dia')
        ->assertSee('Você não tem aula no quadro de horários em 13/05/2026 (Quarta).')
        ->html();

    expect($html)->toMatch('/<button[^>]*type="submit"[^>]*\sdisabled[\s>]/');

    expect(fn () => app(RegistrarAusenciaAction::class)->executar($this->renato, Carbon::parse('2026-05-13'), null, []))
        ->toThrow(RegraDeNegocioException::class, 'Você não tem aula no quadro de horários em 13/05/2026');
});

it('recusa dia que já passou', function () {
    ($this->tela)('2026-05-01')
        ->assertSee('Esse dia já passou')
        ->call('informar')
        ->assertHasErrors(['data' => 'after_or_equal']);

    expect(fn () => app(RegistrarAusenciaAction::class)->executar($this->renato, Carbon::parse('2026-05-01'), null, []))
        ->toThrow(RegraDeNegocioException::class, '01/05/2026 já passou. A ausência é informada com antecedência.');
});

it('aceita a ausência para hoje', function () {
    // Hoje é segunda, 04/05: a 1ª aula de segunda entra no quadro.
    QuadroHorario::query()->create([
        'turma_id' => $this->turmaA->id, 'dia_semana' => DiaDaSemana::Segunda, 'aula_numero' => 1,
        'hora_inicio' => '07:00', 'hora_fim' => '07:50',
        'disciplina_id' => $this->logica->id, 'professor_id' => $this->renato->id,
    ]);

    ($this->tela)('2026-05-04')
        ->set('planos.'.QuadroHorario::query()->where('dia_semana', 1)->value('id').'.conteudo', 'Lista 2.')
        ->call('informar')
        ->assertHasNoErrors();

    expect(Ausencia::query()->sole()->data->toDateString())->toBe('2026-05-04');
});

it('recusa a segunda ausência no mesmo dia, e aceita depois de cancelada a primeira', function () {
    $registrar = fn () => app(RegistrarAusenciaAction::class)->executar($this->renato, Carbon::parse('2026-05-12'), null, [
        $this->a1->id => ['escopo_aula_id' => $this->lacos->id, 'conteudo' => null],
        $this->a2->id => ['escopo_aula_id' => $this->aninhados->id, 'conteudo' => null],
        $this->b3->id => ['escopo_aula_id' => $this->lacos->id, 'conteudo' => null],
    ]);

    $primeira = $registrar();

    ($this->tela)('2026-05-12')
        ->call('informar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'Você já informou ausência em 12/05/2026. Cancele a anterior para informar de novo.');

    $primeira->update(['status' => StatusAusencia::Cancelada]);

    expect($registrar()->id)->not->toBe($primeira->id);
});

it('recusa aula do escopo que não é da semana daquela aula', function () {
    $deOutraSemana = ($this->escopo)('Vetores', semana: 4);

    expect(fn () => app(RegistrarAusenciaAction::class)->executar($this->renato, Carbon::parse('2026-05-12'), null, [
        $this->a1->id => ['escopo_aula_id' => $deOutraSemana->id, 'conteudo' => null],
    ]))->toThrow(RegraDeNegocioException::class, '1ª aula, 07:00–07:50 · Lógica · turma 1 A: a aula escolhida não é do escopo desta semana.');

    expect(Ausencia::query()->count())->toBe(0);
});

it('guarda a aula como estava, mesmo que o quadro mude depois', function () {
    ($this->tela)('2026-05-12')->call('informar')->assertHasNoErrors();

    $this->a1->update(['hora_inicio' => '13:00', 'hora_fim' => '13:50']);
    $this->lacos->update(['titulo_aula' => 'Outro título']);

    $aula = Ausencia::query()->sole()->aulas()->where('quadro_horario_id', $this->a1->id)->sole();

    expect($aula->intervalo())->toBe('07:00–07:50')
        ->and($aula->escopo_titulo)->toBe('Laços de repetição');
});

// --- Minhas ausências e cancelamento -------------------------------------

it('lista as ausências do professor, próximas e passadas, sem as dos outros', function () {
    Ausencia::query()->create(['professor_id' => $this->renato->id, 'data' => '2026-05-12', 'motivo' => 'Consulta médica']);
    Ausencia::query()->create(['professor_id' => $this->renato->id, 'data' => '2026-05-26', 'motivo' => 'Curso de formação']);
    Ausencia::query()->create([
        'professor_id' => $this->renato->id, 'data' => '2026-04-28', 'motivo' => 'Viagem', 'status' => StatusAusencia::Ciente,
    ]);
    Ausencia::query()->create(['professor_id' => $this->marta->id, 'data' => '2026-05-12', 'motivo' => 'Motivo da Marta']);

    $this->actingAs($this->renato)
        ->get(route('ausencias.index'))
        ->assertOk()
        ->assertSeeInOrder(['Próximas', '12/05/2026', '26/05/2026', 'Passadas', '28/04/2026'])
        ->assertSee('Consulta médica')
        ->assertSee('Coordenação ciente')
        ->assertSee('Aguardando ciência')
        ->assertDontSee('Motivo da Marta');
});

it('deixa o professor cancelar a ausência que ainda não aconteceu', function () {
    $ausencia = Ausencia::query()->create(['professor_id' => $this->renato->id, 'data' => '2026-05-12']);
    Ausencia::query()->create(['professor_id' => $this->renato->id, 'data' => '2026-05-26']);

    Livewire::actingAs($this->renato)
        ->test(MinhasAusencias::class)
        ->call('cancelar', $ausencia->id)
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'sucesso');

    expect($ausencia->fresh()->status)->toBe(StatusAusencia::Cancelada)
        ->and($ausencia->fresh()->cancelada_em)->not->toBeNull();
});

it('não cancela a ausência que já passou, e esconde o botão dela', function () {
    $passada = Ausencia::query()->create(['professor_id' => $this->renato->id, 'data' => '2026-04-28']);
    Ausencia::query()->create(['professor_id' => $this->renato->id, 'data' => '2026-05-12']);

    Livewire::actingAs($this->renato)
        ->test(MinhasAusencias::class)
        ->assertDontSee("cancelar({$passada->id})")
        ->call('cancelar', $passada->id)
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === '28/04/2026 já passou. Ausência que já aconteceu não se cancela.');

    expect($passada->fresh()->status)->toBe(StatusAusencia::Pendente);
});

it('não cancela duas vezes', function () {
    $ausencia = Ausencia::query()->create([
        'professor_id' => $this->renato->id, 'data' => '2026-05-12', 'status' => StatusAusencia::Cancelada,
    ]);

    Livewire::actingAs($this->renato)
        ->test(MinhasAusencias::class)
        ->call('cancelar', $ausencia->id)
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'A ausência de 12/05/2026 já estava cancelada.');
});

it('não deixa cancelar a ausência de outro professor', function () {
    $daMarta = Ausencia::query()->create(['professor_id' => $this->marta->id, 'data' => '2026-05-12']);

    Livewire::actingAs($this->renato)
        ->test(MinhasAusencias::class)
        ->call('cancelar', $daMarta->id)
        ->assertForbidden();

    expect($daMarta->fresh()->status)->toBe(StatusAusencia::Pendente);
});

// --- Detalhe e acesso ----------------------------------------------------

it('mostra cada aula da ausência com o escopo e o conteúdo planejado', function () {
    ($this->tela)('2026-05-12')
        ->set('motivo', 'Consulta médica')
        ->set("planos.{$this->b3->id}.escopo_aula_id", '')
        ->set("planos.{$this->b3->id}.conteudo", 'Exercícios do caderno, página 40.')
        ->call('informar');

    $this->actingAs($this->renato)
        ->get(route('ausencias.show', Ausencia::query()->sole()))
        ->assertOk()
        ->assertSee('Terça, 12/05/2026')
        ->assertSee('Consulta médica')
        ->assertSeeInOrder(['07:00–07:50', 'Laços de repetição', '07:50–08:40', 'Laços aninhados', '08:40–09:30'])
        ->assertSee('Turma 1 B')
        ->assertSee('nenhuma aula do escopo escolhida')
        ->assertSee('Exercícios do caderno, página 40.');
});

it('não mostra a ausência a quem não a informou', function () {
    $daMarta = Ausencia::query()->create(['professor_id' => $this->marta->id, 'data' => '2026-05-12']);

    $this->actingAs($this->renato)->get(route('ausencias.show', $daMarta))->assertForbidden();
    $this->actingAs(paeet(Eixo::factory()->create(['codigo' => 'ADM'])))->get(route('ausencias.show', $daMarta))->assertForbidden();
});

it('põe "Minhas ausências" no menu de quem dá aula, e só de quem dá aula', function () {
    // Com a aspa: `/ausencias` é prefixo de `/ausencias/coordenacao`, que o PAEET vê.
    $this->actingAs($this->renato)->get(route('painel'))
        ->assertSee('href="'.route('ausencias.index').'"', false);

    $this->actingAs($this->paeet)->get(route('painel'))
        ->assertDontSee('href="'.route('ausencias.index').'"', false);
});
