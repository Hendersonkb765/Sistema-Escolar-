<?php

/*
 * A coordenação fica sabendo do que os professores do seu Eixo fazem:
 * ausência informada ou cancelada, questões enviadas, questão corrigida.
 *
 * O aviso vai para o sino de cada PAEET e PAEET Admin ativo dos Eixos
 * envolvidos. Não há WebSocket: o sino pergunta ao servidor a cada 30
 * segundos e mostra em balão o que chegou desde a última pergunta.
 */

use App\Actions\Ausencias\CancelarAusenciaAction;
use App\Actions\Ausencias\RegistrarAusenciaAction;
use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Avaliacao\ReenviarQuestaoAction;
use App\Enums\DiaDaSemana;
use App\Livewire\Notificacoes\Sino;
use App\Models\Eixo;
use App\Models\ProfessorDisciplina;
use App\Models\QuadroHorario;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\AvisoACoordenacao;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-05-04 09:00:00');

    $tec = Eixo::factory()->create(['codigo' => 'TEC']);
    $adm = Eixo::factory()->create(['codigo' => 'ADM']);

    $this->paula = paeet($tec);
    $this->admin = paeetAdmin($tec);
    $this->deAdm = paeet($adm);
    $this->inativo = paeet($tec);
    $this->inativo->update(['ativo' => false]);

    $this->renato = professor($tec, $adm);
    $this->renato->update(['nome' => 'Renato Lima']);

    $deTec = cursoComGrade($tec, [1 => ['Lógica']], autor: $this->paula);
    $deAdm = cursoComGrade($adm, [1 => ['Contabilidade']], autor: $this->deAdm);
    $this->logica = $deTec['disciplinas']['Lógica'];

    $this->turmaTec = Turma::factory()->doCurso($deTec['curso'], $deTec['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026']);
    $this->turmaAdm = Turma::factory()->doCurso($deAdm['curso'], $deAdm['grade'])
        ->create(['periodo' => 1, 'nome' => '2 C', 'periodo_letivo' => '2026']);

    foreach ([$this->logica, $deAdm['disciplinas']['Contabilidade']] as $disciplina) {
        ProfessorDisciplina::query()->create([
            'usuario_id' => $this->renato->id, 'disciplina_id' => $disciplina->id, 'turma_id' => null, 'ativo' => true,
        ]);
    }

    $this->naTerca = fn (Turma $turma, $disciplina, int $numero) => QuadroHorario::query()->create([
        'turma_id' => $turma->id, 'dia_semana' => DiaDaSemana::Terca, 'aula_numero' => $numero,
        'hora_inicio' => sprintf('%02d:00', 6 + $numero), 'hora_fim' => sprintf('%02d:50', 6 + $numero),
        'disciplina_id' => $disciplina->id, 'professor_id' => $this->renato->id,
    ]);

    $this->informar = function () {
        $planos = QuadroHorario::query()->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => ['escopo_aula_id' => null, 'conteudo' => 'Exercícios']])
            ->all();

        return app(RegistrarAusenciaAction::class)->executar($this->renato, Carbon::parse('2026-05-12'), null, $planos);
    };

    /** Os avisos de alguém, do mais antigo ao mais novo. */
    $this->avisosDe = fn (User $usuario) => $usuario->notifications()->reorder('created_at')->get();
});

afterEach(fn () => Carbon::setTestNow());

// --- Quem recebe ---------------------------------------------------------

it('avisa a coordenação do Eixo quando o professor informa uma ausência', function () {
    ($this->naTerca)($this->turmaTec, $this->logica, 1);

    $ausencia = ($this->informar)();

    foreach ([$this->paula, $this->admin] as $coordenacao) {
        $aviso = ($this->avisosDe)($coordenacao)->sole();

        expect($aviso->type)->toBe(AvisoACoordenacao::class)
            ->and($aviso->data)->toBe([
                'assunto' => 'ausencia',
                'titulo' => 'Ausência informada',
                'mensagem' => 'Renato Lima vai faltar em Terça, 12/05/2026.',
                'url' => route('ausencias.show', $ausencia),
            ]);
    }

    // Sem aula na Administração nesse dia: a coordenação de lá não tem o que saber.
    expect(($this->avisosDe)($this->deAdm))->toBeEmpty()
        ->and(($this->avisosDe)($this->inativo))->toBeEmpty()
        ->and(($this->avisosDe)($this->renato))->toBeEmpty();
});

it('avisa as duas coordenações quando o dia tem aula nos dois Eixos', function () {
    ($this->naTerca)($this->turmaTec, $this->logica, 1);
    ($this->naTerca)($this->turmaAdm, Turma::find($this->turmaAdm->id)->disciplinasDoPeriodo()->first()->disciplina, 2);

    ($this->informar)();

    expect(($this->avisosDe)($this->paula))->toHaveCount(1)
        ->and(($this->avisosDe)($this->deAdm))->toHaveCount(1);
});

it('não avisa o PAEET da ausência que ele mesmo informou', function () {
    ProfessorDisciplina::query()->create([
        'usuario_id' => $this->paula->id, 'disciplina_id' => $this->logica->id, 'turma_id' => null, 'ativo' => true,
    ]);
    QuadroHorario::query()->create([
        'turma_id' => $this->turmaTec->id, 'dia_semana' => DiaDaSemana::Terca, 'aula_numero' => 1,
        'hora_inicio' => '07:00', 'hora_fim' => '07:50', 'disciplina_id' => $this->logica->id, 'professor_id' => $this->paula->id,
    ]);

    app(RegistrarAusenciaAction::class)->executar($this->paula, Carbon::parse('2026-05-12'), null, [
        QuadroHorario::query()->value('id') => ['escopo_aula_id' => null, 'conteudo' => 'Exercícios'],
    ]);

    expect(($this->avisosDe)($this->paula))->toBeEmpty()
        ->and(($this->avisosDe)($this->admin))->toHaveCount(1);
});

it('avisa quando o professor cancela a ausência', function () {
    ($this->naTerca)($this->turmaTec, $this->logica, 1);
    $ausencia = ($this->informar)();

    app(CancelarAusenciaAction::class)->executar($ausencia);

    expect(($this->avisosDe)($this->paula)->pluck('data.titulo')->all())->toBe(['Ausência informada', 'Ausência cancelada'])
        ->and(($this->avisosDe)($this->paula)->last()->data['mensagem'])->toBe('Renato Lima cancelou a ausência de Terça, 12/05/2026.');
});

it('avisa quando o professor envia as questões, e só a coordenação daquele Eixo', function () {
    $solicitacao = solicitacaoCom($this->paula, $this->turmaTec, [
        ['disciplina' => $this->logica, 'professor' => $this->renato, 'questoes' => 2],
    ], titulo: 'Avaliação bimestral');

    enviarParte($solicitacao->partes()->sole(), $this->renato);

    $aviso = ($this->avisosDe)($this->paula)->sole();

    expect($aviso->data['titulo'])->toBe('Questões enviadas')
        ->and($aviso->data['mensagem'])->toBe('Renato Lima enviou as questões de Lógica para Avaliação bimestral.')
        ->and($aviso->data['url'])->toBe(route('solicitacoes.show', $solicitacao))
        ->and(($this->avisosDe)($this->deAdm))->toBeEmpty();
});

it('diz quando as questões chegaram em atraso', function () {
    $solicitacao = solicitacaoCom($this->paula, $this->turmaTec, [
        ['disciplina' => $this->logica, 'professor' => $this->renato, 'questoes' => 1],
    ], prazo: now()->addDay());

    $this->travel(2)->days();
    enviarParte($solicitacao->partes()->sole(), $this->renato);

    expect(($this->avisosDe)($this->paula)->sole()->data['titulo'])->toBe('Questões enviadas em atraso');
});

it('avisa quando o professor reenvia uma questão corrigida', function () {
    $solicitacao = solicitacaoCom($this->paula, $this->turmaTec, [
        ['disciplina' => $this->logica, 'professor' => $this->renato, 'questoes' => 1],
    ], titulo: 'Avaliação bimestral');
    enviarParte($solicitacao->partes()->sole(), $this->renato);

    $questao = $solicitacao->questoes()->sole();
    app(AnalisarQuestaoAction::class)->rejeitar($questao, $this->paula, 'Alternativa B também está certa.');
    app(ReenviarQuestaoAction::class)->executar($questao->refresh(), $this->renato);

    $aviso = ($this->avisosDe)($this->paula)->last();

    expect($aviso->data['titulo'])->toBe('Questão corrigida')
        ->and($aviso->data['mensagem'])->toBe('Renato Lima reenviou uma questão de Lógica em Avaliação bimestral.');
});

// --- O sino --------------------------------------------------------------

/** Um aviso no sino da Paula, gravado agora. */
function avisoNoSino(User $para, string $mensagem, string $url = '/painel'): void
{
    $para->notify(new AvisoACoordenacao('ausencia', 'Ausência informada', $mensagem, $url));
}

it('mostra no sino o número de não lidos e os avisos recentes', function () {
    avisoNoSino($this->paula, 'Renato Lima vai faltar em Terça, 12/05/2026.');
    avisoNoSino($this->paula, 'Marta Reis vai faltar em Quarta, 13/05/2026.');
    $this->paula->notifications()->latest()->first()->markAsRead();
    avisoNoSino($this->paula, 'Carlos Dantas vai faltar em Quinta, 14/05/2026.');

    $html = $this->actingAs($this->paula)->get(route('painel'))->assertOk()->getContent();

    expect($html)->toContain('wire:poll.30s="verificar"')
        ->and($html)->toMatch('/data-contador-de-avisos[^>]*>2</')
        ->and($html)->toContain('Renato Lima vai faltar em Terça, 12/05/2026.')
        ->and($html)->toContain('Marta Reis vai faltar em Quarta, 13/05/2026.')
        ->and($html)->toContain('Carlos Dantas vai faltar em Quinta, 14/05/2026.');
});

it('não mostra o contador quando não há aviso novo', function () {
    $html = $this->actingAs($this->paula)->get(route('painel'))->getContent();

    expect($html)->not->toContain('data-contador-de-avisos')
        ->and($html)->toContain('Nenhum aviso ainda.');
});

it('não põe o sino na tela do professor', function () {
    $this->actingAs($this->renato)->get(route('painel'))
        ->assertOk()
        ->assertDontSee('wire:poll.30s', false);
});

/**
 * As mensagens dos balões da última chamada. `assertDispatched` com
 * closure só examina o primeiro evento de mesmo nome — com três balões,
 * o segundo e o terceiro passariam sem ser vistos.
 */
function baloes($sino): array
{
    return collect($sino->effects['dispatches'] ?? [])
        ->where('name', 'notificar')
        ->pluck('params.mensagem')
        ->all();
}

it('mostra em balão só o que chegou desde a última consulta, uma vez cada', function () {
    avisoNoSino($this->paula, 'Aviso de antes de abrir a tela.');

    $sino = Livewire::actingAs($this->paula)->test(Sino::class);

    $this->travel(10)->seconds();
    avisoNoSino($this->paula, 'Renato Lima vai faltar em Terça, 12/05/2026.');
    $this->travel(20)->seconds();

    $sino->call('verificar');

    expect(baloes($sino))->toBe(['Renato Lima vai faltar em Terça, 12/05/2026.']);

    $this->travel(30)->seconds();

    $sino->call('verificar')->assertNotDispatched('notificar');
});

it('deixa para a consulta seguinte o aviso gravado no mesmo segundo dela', function () {
    $sino = Livewire::actingAs($this->paula)->test(Sino::class);

    $this->travel(30)->seconds();
    avisoNoSino($this->paula, 'Gravado no segundo da consulta.');

    $sino->call('verificar')->assertNotDispatched('notificar');

    $this->travel(30)->seconds();

    $sino->call('verificar')->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['mensagem'] === 'Gravado no segundo da consulta.');
});

it('resume em um balão o que passar de três avisos novos', function () {
    $sino = Livewire::actingAs($this->paula)->test(Sino::class);

    $this->travel(5)->seconds();
    foreach (range(1, 5) as $i) {
        avisoNoSino($this->paula, "Aviso {$i}");
    }
    $this->travel(25)->seconds();

    $sino->call('verificar');

    expect(baloes($sino))->toBe(['Aviso 1', 'Aviso 2', 'Aviso 3', 'E mais 2 aviso(s) no sino.']);
});

it('marca como lido e leva à tela do assunto ao abrir o aviso', function () {
    avisoNoSino($this->paula, 'Renato Lima vai faltar.', route('ausencias.coordenacao'));
    avisoNoSino($this->paula, 'Outro aviso.');
    $aviso = $this->paula->notifications()->where('data->mensagem', 'Renato Lima vai faltar.')->sole();

    Livewire::actingAs($this->paula)->test(Sino::class)
        ->call('abrir', $aviso->id)
        ->assertRedirect(route('ausencias.coordenacao'));

    expect($aviso->fresh()->read_at)->not->toBeNull()
        ->and($this->paula->unreadNotifications()->count())->toBe(1);
});

it('marca todos como lidos', function () {
    avisoNoSino($this->paula, 'Um.');
    avisoNoSino($this->paula, 'Dois.');
    avisoNoSino($this->admin, 'Do colega.');

    Livewire::actingAs($this->paula)->test(Sino::class)->call('marcarTodasComoLidas');

    expect($this->paula->unreadNotifications()->count())->toBe(0)
        // O "lido" é de cada um.
        ->and($this->admin->unreadNotifications()->count())->toBe(1);
});

it('não abre o aviso de outra pessoa pelo id', function () {
    avisoNoSino($this->admin, 'Do colega.');
    $doColega = $this->admin->notifications()->sole();

    expect(fn () => Livewire::actingAs($this->paula)->test(Sino::class)->call('abrir', $doColega->id))
        ->toThrow(ModelNotFoundException::class);

    expect($doColega->fresh()->read_at)->toBeNull();
});
