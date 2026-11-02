<?php

/*
 * Os padrões da escola — nome nos documentos, aulas do dia, intervalos —
 * ficam numa tela só, e só o PAEET Admin altera: uma mudança ali atinge
 * todas as turmas de uma vez.
 *
 * O que ele grava fica no banco e vale por cima de config/instituicao.php,
 * que passa a ser só o valor inicial.
 */

use App\Livewire\Configuracao\ConfiguracaoDaEscola;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Turma;
use App\Providers\AppServiceProvider;
use App\Support\IntervaloDoDia;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);
});

it('abre com os valores em uso, vindos do arquivo enquanto ninguém salvou', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('configuracao.escola'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('value="E. E. Prof. Francisco Pereira de Souza Filho"')
        ->and($html)->toContain('value="7"')
        ->and($html)->toContain('value="17:00"')
        ->and($html)->toContain('value="17:20"')
        ->and($html)->toContain('value="19:50"')
        ->and(substr_count($html, 'data-linha-de-intervalo'))->toBe(2);
});

it('grava a configuração, que passa a valer no sistema inteiro', function () {
    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->set('nome', 'E. E. Escola Nova')
        ->set('aulas_por_dia', '6')
        ->set('intervalos.1.nome', 'Jantar')
        ->set('intervalos.1.inicio', '18:40')
        ->set('intervalos.1.fim', '19:20')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'sucesso');

    expect(config('instituicao.nome'))->toBe('E. E. Escola Nova')
        ->and(config('instituicao.aulas_por_dia'))->toBe(6)
        ->and(IntervaloDoDia::padrao()->map->intervalo()->all())->toBe(['17:00–17:20', '18:40–19:20']);

    // O nome da escola na folha de prova, quando nem prova nem modelo dizem outro.
    $prova = new Prova;
    $prova->setRelation('modelo', new ModeloProva);

    expect($prova->instituicaoDaFolha())->toBe('E. E. Escola Nova');
});

it('lê do banco na inicialização, por cima do arquivo', function () {
    DB::table('configuracoes')->insert([
        ['chave' => 'aulas_por_dia', 'valor' => json_encode(5), 'created_at' => now(), 'updated_at' => now()],
        ['chave' => 'intervalos', 'valor' => json_encode([['nome' => 'Recreio', 'inicio' => '09:30', 'minutos' => 15]]), 'created_at' => now(), 'updated_at' => now()],
    ]);

    config(['instituicao.aulas_por_dia' => 7]);
    // A inicialização do sistema, como numa requisição nova.
    app()->getProvider(AppServiceProvider::class)->boot();

    expect(config('instituicao.aulas_por_dia'))->toBe(5)
        ->and(IntervaloDoDia::padrao()->map->intervalo()->all())->toBe(['09:30–09:45'])
        // O que não foi gravado continua vindo do arquivo.
        ->and(config('instituicao.nome'))->toBe('E. E. Prof. Francisco Pereira de Souza Filho');
});

it('registra na auditoria quem mudou e o que era antes', function () {
    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->set('aulas_por_dia', '6')
        ->call('salvar');

    $registro = Activity::query()->where('log_name', 'configuracao')->sole();

    expect($registro->causer_id)->toBe($this->admin->id)
        ->and($registro->properties['old']['aulas_por_dia'])->toBe(7)
        ->and($registro->properties['attributes']['aulas_por_dia'])->toBe(6);
});

it('recusa intervalos sobrepostos ou nenhum, e campos fora do formato', function () {
    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->set('intervalos.1.nome', 'Jantar')
        ->set('intervalos.1.inicio', '17:10')
        ->set('intervalos.1.fim', '17:40')
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'Jantar (17:10–17:40) se sobrepõe a Intervalo (17:00–17:20).');

    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->call('removerIntervalo', 1)
        ->call('removerIntervalo', 0)
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['mensagem'] === 'Informe ao menos um intervalo.');

    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->set('nome', '')
        ->set('aulas_por_dia', '0')
        ->set('intervalos.0.fim', '16:00')
        ->call('salvar')
        ->assertHasErrors(['nome', 'aulas_por_dia', 'intervalos.0.fim']);

    expect(DB::table('configuracoes')->count())->toBe(0);
});

it('acrescenta uma linha de intervalo', function () {
    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->call('adicionarIntervalo')
        ->assertSet('intervalos.2.alcance', 'todas')
        ->set('intervalos.2.nome', 'Recreio')
        ->set('intervalos.2.inicio', '21:00')
        ->set('intervalos.2.fim', '21:10')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(IntervaloDoDia::padrao())->toHaveCount(3);
});

it('não deixa o PAEET nem o professor abrir ou salvar', function () {
    foreach ([paeet($this->eixo), professor($this->eixo)] as $usuario) {
        $this->actingAs($usuario)->get(route('configuracao.escola'))->assertForbidden();
        $this->actingAs($usuario)->get(route('painel'))->assertDontSee(route('configuracao.escola'), false);
    }

    expect(DB::table('configuracoes')->count())->toBe(0);
});

it('põe "Configuração da escola" no menu do PAEET Admin', function () {
    $this->actingAs($this->admin)->get(route('painel'))
        ->assertSee(route('configuracao.escola'), false);
});

// --- A quem cada intervalo se aplica -------------------------------------

/** Duas turmas ativas de outro Eixo, para lembrar que o intervalo é da escola inteira. */
function turmasDaEscola(): array
{
    $outro = Eixo::factory()->create(['codigo' => 'ADM']);
    $montagem = cursoComGrade($outro, [1 => ['Contabilidade']]);

    return [
        Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create(['periodo' => 1, 'nome' => '1 A']),
        Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create(['periodo' => 1, 'nome' => '3 B']),
    ];
}

it('aplica os dois intervalos a todas as turmas por padrão', function () {
    [$a, $b] = turmasDaEscola();

    expect(IntervaloDoDia::padrao()->map->alcance->all())->toBe(['todas', 'todas'])
        ->and(IntervaloDoDia::daTurma($a)->map->intervalo()->all())->toBe(['17:00–17:20', '19:00–19:50'])
        ->and(IntervaloDoDia::daTurma($b)->map->intervalo()->all())->toBe(['17:00–17:20', '19:00–19:50']);
});

it('dá a uma turma um intervalo diferente, tirando-a do padrão', function () {
    [$a, $b] = turmasDaEscola();

    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->set('intervalos.1.alcance', 'exceto')
        ->set('intervalos.1.turmas', [(string) $b->id])
        ->call('adicionarIntervalo')
        ->set('intervalos.2.nome', 'Intervalo da 3 B')
        ->set('intervalos.2.inicio', '19:30')
        ->set('intervalos.2.fim', '20:20')
        ->set('intervalos.2.alcance', 'somente')
        ->set('intervalos.2.turmas', [(string) $b->id])
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'sucesso');

    expect(IntervaloDoDia::daTurma($a)->map->intervalo()->all())->toBe(['17:00–17:20', '19:00–19:50'])
        ->and(IntervaloDoDia::daTurma($b)->map->intervalo()->all())->toBe(['17:00–17:20', '19:30–20:20']);
});

it('mostra as turmas só quando o intervalo não é para todas, com as marcadas já marcadas', function () {
    [, $b] = turmasDaEscola();

    $tela = Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class);

    expect($tela->html())->not->toContain('data-turmas-do-intervalo');

    $html = $tela->set('intervalos.1.alcance', 'exceto')
        ->set('intervalos.1.turmas', [(string) $b->id])
        ->html();

    expect(substr_count($html, 'data-turmas-do-intervalo'))->toBe(1)
        ->and($html)->toContain('Turmas que ficam de fora')
        ->and($html)->toMatch('/<option value="exceto"\s+selected/')
        ->and($html)->toMatch('/value="'.$b->id.'"[^>]*wire:model="intervalos\.1\.turmas"\s+checked/')
        ->and(substr_count($html, ' checked'))->toBe(1);
});

it('deixa conviver intervalos no mesmo horário quando não têm turma em comum', function () {
    [, $b] = turmasDaEscola();

    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->set('intervalos.1.alcance', 'exceto')
        ->set('intervalos.1.turmas', [(string) $b->id])
        ->call('adicionarIntervalo')
        ->set('intervalos.2.nome', 'Intervalo da 3 B')
        ->set('intervalos.2.inicio', '19:10')
        ->set('intervalos.2.fim', '19:40')
        ->set('intervalos.2.alcance', 'somente')
        ->set('intervalos.2.turmas', [(string) $b->id])
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertNotDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro');

    expect(IntervaloDoDia::padrao())->toHaveCount(3);
});

it('recusa sobreposição numa turma em comum, dizendo qual', function () {
    [, $b] = turmasDaEscola();

    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->call('adicionarIntervalo')
        ->set('intervalos.2.nome', 'Intervalo da 3 B')
        ->set('intervalos.2.inicio', '19:10')
        ->set('intervalos.2.fim', '19:40')
        ->set('intervalos.2.alcance', 'somente')
        ->set('intervalos.2.turmas', [(string) $b->id])
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'Intervalo da 3 B (19:10–19:40) se sobrepõe a Intervalo (19:00–19:50) na turma 3 B.');

    expect(DB::table('configuracoes')->count())->toBe(0);
});

it('recusa "só as marcadas" sem turma marcada, e trata "exceto nenhuma" como todas', function () {
    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->set('intervalos.1.alcance', 'somente')
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['tipo'] === 'erro'
            && $dados['mensagem'] === 'Intervalo (19:00–19:50): marque ao menos uma turma, ou aplique a todas.');

    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->set('intervalos.1.alcance', 'exceto')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(IntervaloDoDia::padrao()->map->alcance->all())->toBe(['todas', 'todas']);
});

it('recusa turma que não existe', function () {
    Livewire::actingAs($this->admin)->test(ConfiguracaoDaEscola::class)
        ->set('intervalos.1.alcance', 'somente')
        ->set('intervalos.1.turmas', ['999'])
        ->call('salvar')
        ->assertDispatched('notificar', fn (string $evento, array $dados) => $dados['mensagem'] === 'Uma das turmas marcadas não existe mais. Recarregue a página.');
});
