<?php

/*
 * A coordenação acompanha as ausências com aula nas turmas do seu Eixo
 * e toma ciência delas. Não aprova nem recusa: a ciência só diz ao
 * professor que o aviso chegou, e quem o recebeu.
 *
 * O professor pode dar aula em dois Eixos no mesmo dia. Cada coordenação
 * vê a ausência inteira como aviso, mas só as aulas das próprias turmas.
 *
 * O cenário: segunda, 04/05/2026. Renato falta na terça 12/05, com uma
 * aula na 1 A (Tecnologia) e outra na 2 C (Administração).
 */

use App\Enums\StatusAusencia;
use App\Livewire\Ausencias\AusenciasDaCoordenacao;
use App\Livewire\Ausencias\DetalheAusencia;
use App\Models\Ausencia;
use App\Models\Eixo;
use App\Models\Turma;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-05-04 09:00');

    $tec = Eixo::factory()->create(['codigo' => 'TEC']);
    $adm = Eixo::factory()->create(['codigo' => 'ADM']);

    $this->paeetTec = paeet($tec);
    $this->paeetTec->update(['nome' => 'Paula Tecnologia']);
    $this->paeetAdm = paeet($adm);

    $this->renato = professor($tec, $adm);
    $this->renato->update(['nome' => 'Renato Lima']);
    $this->marta = professor($tec);
    $this->marta->update(['nome' => 'Marta Reis']);
    $this->carlos = professor($adm);
    $this->carlos->update(['nome' => 'Carlos Dantas']);

    $deTec = cursoComGrade($tec, [1 => ['Lógica', 'Redes']], autor: $this->paeetTec);
    $deAdm = cursoComGrade($adm, [1 => ['Contabilidade']], autor: $this->paeetAdm);
    $this->logica = $deTec['disciplinas']['Lógica'];
    $this->redes = $deTec['disciplinas']['Redes'];
    $this->contabilidade = $deAdm['disciplinas']['Contabilidade'];

    $this->turmaTec = Turma::factory()->doCurso($deTec['curso'], $deTec['grade'])->create(['periodo' => 1, 'nome' => '1 A']);
    $this->turmaAdm = Turma::factory()->doCurso($deAdm['curso'], $deAdm['grade'])->create(['periodo' => 1, 'nome' => '2 C']);

    /** Uma ausência com as aulas dadas: [turma, disciplina, número, conteúdo]. */
    $this->ausencia = function ($professor, string $data, array $aulas, array $campos = []) {
        $ausencia = Ausencia::query()->create(['professor_id' => $professor->id, 'data' => $data, ...$campos]);

        foreach ($aulas as [$turma, $disciplina, $numero, $conteudo]) {
            $ausencia->aulas()->create([
                'turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'aula_numero' => $numero,
                'hora_inicio' => sprintf('%02d:00', 6 + $numero), 'hora_fim' => sprintf('%02d:50', 6 + $numero),
                'escopo_titulo' => null, 'conteudo_planejado' => $conteudo,
            ]);
        }

        return $ausencia;
    };

    $this->doRenato = ($this->ausencia)($this->renato, '2026-05-12', [
        [$this->turmaTec, $this->logica, 1, 'Lista de laços'],
        [$this->turmaAdm, $this->contabilidade, 3, 'Balanço patrimonial'],
    ], ['motivo' => 'Consulta médica']);
    $this->daMarta = ($this->ausencia)($this->marta, '2026-05-13', [[$this->turmaTec, $this->redes, 2, 'Topologias']]);
    $this->doCarlos = ($this->ausencia)($this->carlos, '2026-05-14', [[$this->turmaAdm, $this->contabilidade, 1, 'Fluxo de caixa']]);

    $this->lista = fn ($quem = null, array $filtro = []) => Livewire::withQueryParams($filtro)
        ->actingAs($quem ?? $this->paeetTec)
        ->test(AusenciasDaCoordenacao::class);
});

afterEach(fn () => Carbon::setTestNow());

// --- A lista -------------------------------------------------------------

it('lista as ausências com aula no Eixo, cada uma só com as aulas de lá', function () {
    $this->actingAs($this->paeetTec)
        ->get(route('ausencias.coordenacao'))
        ->assertOk()
        ->assertSeeInOrder(['Terça, 12/05/2026', 'Renato Lima', 'Lógica (1 A)', 'Quarta, 13/05/2026', 'Marta Reis', 'Redes (1 A)'])
        ->assertSee('Consulta médica')
        // A aula do Renato na Administração e a ausência do Carlos são de outro Eixo.
        ->assertDontSee('Contabilidade')
        ->assertDontSee('2 C')
        ->assertDontSee('Carlos Dantas');
});

it('mostra à outra coordenação a mesma ausência, com as aulas de lá', function () {
    $this->actingAs($this->paeetAdm)
        ->get(route('ausencias.coordenacao'))
        ->assertSeeInOrder(['Renato Lima', 'Contabilidade (2 C)', 'Carlos Dantas', 'Contabilidade (2 C)'])
        ->assertDontSee('Lógica')
        ->assertDontSee('Marta Reis');
});

it('abre nas que esperam ciência e ainda vão acontecer, e filtra as demais', function () {
    $this->daMarta->update(['status' => StatusAusencia::Ciente, 'ciente_por' => $this->paeetTec->id, 'ciente_em' => now()]);
    $passada = ($this->ausencia)($this->marta, '2026-04-28', [[$this->turmaTec, $this->redes, 2, 'Revisão']]);
    $cancelada = ($this->ausencia)($this->marta, '2026-05-20', [[$this->turmaTec, $this->redes, 2, 'Prova']], [
        'status' => StatusAusencia::Cancelada, 'cancelada_em' => now(),
    ]);

    ($this->lista)()
        ->assertSee('12/05/2026')
        ->assertDontSee('13/05/2026')
        ->assertDontSee('28/04/2026')
        ->assertDontSee('20/05/2026')
        ->set('situacao', 'ciente')
        ->assertSee('13/05/2026')
        ->assertSee('Ciência de Paula Tecnologia em 04/05/2026')
        ->assertDontSee('12/05/2026')
        ->set('situacao', 'cancelada')
        ->assertSee('20/05/2026')
        ->set('situacao', 'todas')
        ->set('quando', 'passadas')
        ->assertSee('28/04/2026')
        ->assertDontSee('12/05/2026');
});

it('marca no seletor o filtro que veio no endereço', function () {
    $html = ($this->lista)(filtro: ['situacao' => 'ciente', 'quando' => 'passadas'])->html();

    expect($html)->toMatch('/<option value="ciente"\s+selected/')
        ->and($html)->toMatch('/<option value="passadas"\s+selected/')
        ->and($html)->not->toMatch('/<option value="pendente"\s+selected/');
});

it('volta ao padrão quando o filtro do endereço não existe', function () {
    ($this->lista)(filtro: ['situacao' => 'qualquer', 'quando' => 'ontem'])
        ->assertSet('situacao', 'pendente')
        ->assertSet('quando', 'proximas')
        ->assertSee('12/05/2026');
});

// --- Ciência -------------------------------------------------------------

it('deixa a coordenação tomar ciência pela lista', function () {
    ($this->lista)()
        ->call('tomarCiencia', $this->doRenato->id)
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'sucesso'
            && $dados['mensagem'] === 'Ciência registrada da ausência de Renato Lima em 12/05/2026.')
        // Sai da lista de pendentes.
        ->assertDontSee('12/05/2026');

    $ausencia = $this->doRenato->fresh();

    expect($ausencia->status)->toBe(StatusAusencia::Ciente)
        ->and($ausencia->ciente_por)->toBe($this->paeetTec->id)
        ->and($ausencia->ciente_em->toDateTimeString())->toBe('2026-05-04 09:00:00');
});

it('deixa a coordenação tomar ciência pelo detalhe, e o professor vê quem a tomou', function () {
    Livewire::actingAs($this->paeetTec)
        ->test(DetalheAusencia::class, ['ausencia' => $this->doRenato])
        ->assertSee('Tomar ciência')
        ->call('tomarCiencia')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'sucesso')
        ->assertDontSee('Tomar ciência')
        ->assertSee('Coordenação ciente');

    $this->actingAs($this->renato)
        ->get(route('ausencias.show', $this->doRenato))
        ->assertSee('Ciência de Paula Tecnologia em 04/05/2026 às 09:00.');

    $this->actingAs($this->renato)
        ->get(route('ausencias.index'))
        ->assertSee('Coordenação ciente');
});

it('não toma ciência de ausência cancelada nem duas vezes, dizendo por quê', function () {
    $this->daMarta->update(['status' => StatusAusencia::Cancelada, 'cancelada_em' => now()]);
    $this->doRenato->update(['status' => StatusAusencia::Ciente, 'ciente_por' => $this->paeetAdm->id, 'ciente_em' => now()]);
    $this->paeetAdm->update(['nome' => 'Ana Administração']);

    ($this->lista)()
        ->call('tomarCiencia', $this->daMarta->id)
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'Marta Reis cancelou a ausência de 13/05/2026: não há do que tomar ciência.')
        ->call('tomarCiencia', $this->doRenato->id)
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'Ana Administração já tomou ciência da ausência de 12/05/2026 em 04/05/2026 às 09:00.');

    expect($this->daMarta->fresh()->status)->toBe(StatusAusencia::Cancelada);
});

it('mostra a ausência cancelada à coordenação, com a data do cancelamento', function () {
    $this->doRenato->update(['status' => StatusAusencia::Cancelada, 'cancelada_em' => '2026-05-04 08:30']);

    Livewire::actingAs($this->paeetTec)
        ->test(DetalheAusencia::class, ['ausencia' => $this->doRenato])
        ->assertSee('Cancelada pelo professor em 04/05/2026 às 08:30.')
        ->assertDontSee('Tomar ciência');
});

// --- Detalhe -------------------------------------------------------------

it('mostra à coordenação só as aulas do seu Eixo, e diz quantas ficaram de fora', function () {
    $this->actingAs($this->paeetTec)
        ->get(route('ausencias.show', $this->doRenato))
        ->assertOk()
        ->assertSee('Lista de laços')
        ->assertSee('Turma 1 A')
        ->assertDontSee('Balanço patrimonial')
        ->assertDontSee('Turma 2 C')
        ->assertSee('Mais 1 aula(s) deste dia em turmas de outro Eixo');
});

it('mostra ao professor todas as aulas dele, sem o aviso de outro Eixo', function () {
    $this->actingAs($this->renato)
        ->get(route('ausencias.show', $this->doRenato))
        ->assertSee('Lista de laços')
        ->assertSee('Balanço patrimonial')
        ->assertDontSee('turmas de outro Eixo')
        ->assertDontSee('Tomar ciência');
});

// --- Acesso --------------------------------------------------------------

it('responde 403 ao PAEET de outro Eixo pelo id da ausência', function () {
    $this->actingAs($this->paeetTec)->get(route('ausencias.show', $this->doCarlos))->assertForbidden();

    ($this->lista)()->call('tomarCiencia', $this->doCarlos->id)->assertForbidden();

    expect($this->doCarlos->fresh()->status)->toBe(StatusAusencia::Pendente);
});

it('não deixa o professor abrir a lista da coordenação nem tomar ciência', function () {
    $this->actingAs($this->marta)->get(route('ausencias.coordenacao'))->assertForbidden();

    Livewire::actingAs($this->renato)
        ->test(DetalheAusencia::class, ['ausencia' => $this->doRenato])
        ->call('tomarCiencia')
        ->assertForbidden();

    expect($this->doRenato->fresh()->status)->toBe(StatusAusencia::Pendente);
});

// --- Contadores ----------------------------------------------------------

it('conta no menu e no painel as ausências que esperam ciência', function () {
    // Nem a pendente que já passou nem a de outro Eixo entram na conta.
    ($this->ausencia)($this->marta, '2026-04-28', [[$this->turmaTec, $this->redes, 2, 'Revisão']]);

    $this->actingAs($this->paeetTec)
        ->get(route('painel'))
        ->assertSee('2 ausências aguardando ciência')
        ->assertSee('Ausências aguardando ciência')
        ->assertSee(route('ausencias.coordenacao'), false);

    $this->doRenato->update(['status' => StatusAusencia::Ciente, 'ciente_por' => $this->paeetTec->id, 'ciente_em' => now()]);

    $this->actingAs($this->paeetTec)
        ->get(route('painel'))
        ->assertSee('1 ausência aguardando ciência');
});

it('não mostra o indicador quando nada espera ciência', function () {
    Ausencia::query()->update(['status' => StatusAusencia::Ciente->value]);

    $this->actingAs($this->paeetTec)
        ->get(route('painel'))
        ->assertDontSee('Ausências aguardando ciência')
        ->assertDontSee('aguardando ciência')
        // O item do menu continua lá, sem selo.
        ->assertSee('Ausências de professores');
});
