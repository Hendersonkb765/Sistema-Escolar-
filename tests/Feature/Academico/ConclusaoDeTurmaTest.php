<?php

/*
 * O fim do percurso de uma turma.
 *
 * `AvancarTurmaAction` leva de um período ao seguinte; no último não há
 * seguinte, e até aqui o botão simplesmente sumia da tela. Agora a turma
 * encerra: passa a Concluída, os alunos ativos passam a concluintes, e
 * ela sai sozinha de circulação — o formulário de solicitação só oferece
 * turmas ativas.
 */

use App\Actions\Academico\AvancarTurmaAction;
use App\Actions\Academico\ConcluirTurmaAction;
use App\Enums\EventoHistorico;
use App\Enums\StatusAluno;
use App\Enums\StatusTurma;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Solicitacoes\FormularioSolicitacao;
use App\Livewire\Turmas\DetalheTurma;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\Eixo;
use App\Models\Turma;
use App\Models\TurmaHistorico;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação'],
        2 => ['Banco de Dados'],
    ], duracaoAnos: 2);

    $this->curso = $montagem['curso'];
    $this->grade = $montagem['grade'];

    // No 2º de um curso de dois períodos: não há para onde avançar.
    $this->turma = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 2,
        'nome' => '2 A',
        'periodo_letivo' => '2026',
    ]);

    // Dois ativos, um transferido: quem já saiu não conclui.
    $this->marina = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Marina Alves', 'ra' => '2001']);
    $this->caio = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Caio Prado', 'ra' => '2002']);
    $this->saiu = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Rita Nunes', 'ra' => '2003', 'status' => StatusAluno::Transferido]);

    $this->action = app(ConcluirTurmaAction::class);
});

/*
|--------------------------------------------------------------------------
| A regra
|--------------------------------------------------------------------------
*/

it('conclui a turma que chegou ao último período', function () {
    $resultado = $this->action->executar($this->turma, $this->paeet);

    expect($resultado['turma']->status)->toBe(StatusTurma::Concluida)
        ->and($resultado['concluintes'])->toBe(2);
});

it('passa a concluinte quem estava ativo, e só', function () {
    $this->action->executar($this->turma, $this->paeet);

    expect($this->marina->refresh()->status)->toBe(StatusAluno::Concluinte)
        ->and($this->caio->refresh()->status)->toBe(StatusAluno::Concluinte)
        // Quem já tinha saído não concluiu o curso.
        ->and($this->saiu->refresh()->status)->toBe(StatusAluno::Transferido);
});

it('grava um histórico por aluno, com o status anterior', function () {
    $this->action->executar($this->turma, $this->paeet);

    $registros = AlunoHistorico::query()->get();

    expect($registros)->toHaveCount(2)
        ->and($registros->pluck('evento')->unique()->all())
        ->toBe([EventoHistorico::AlunoStatusAlterado])
        ->and($registros->pluck('status_anterior')->unique()->all())->toBe(['ativo'])
        ->and($registros->pluck('status_novo')->unique()->all())->toBe(['concluinte'])
        ->and($registros->pluck('motivo')->unique()->all())->toBe(['Conclusão da turma'])
        ->and($registros->pluck('aluno_id')->sort()->values()->all())
        ->toBe(collect([$this->marina->id, $this->caio->id])->sort()->values()->all());
});

/*
 * O histórico guarda o estado que existia ANTES da mudança — é o que
 * permite dizer depois "em que estado ela estava quando encerrou".
 */
it('grava o estado anterior da turma antes de mudá-lo', function () {
    $this->action->executar($this->turma, $this->paeet, observacoes: 'Formatura de 2026');

    $historico = TurmaHistorico::query()->sole();

    expect($historico->evento)->toBe(EventoHistorico::TurmaEncerrada)
        ->and($historico->status)->toBe(StatusTurma::Ativa->value)
        ->and($historico->periodo)->toBe(2)
        ->and($historico->nome)->toBe('2 A')
        ->and($historico->grade_curricular_id)->toBe($this->grade->id)
        ->and($historico->observacoes)->toBe('Formatura de 2026')
        ->and($historico->registrado_por)->toBe($this->paeet->id);
});

it('registra a conclusão na auditoria', function () {
    $this->action->executar($this->turma, $this->paeet);

    $registro = Activity::query()->where('log_name', 'turma')->latest('id')->first();

    expect($registro->description)->toContain('Turma concluída')
        ->and($registro->properties['concluintes'])->toBe(2)
        ->and($registro->causer_id)->toBe($this->paeet->id);
});

/*
|--------------------------------------------------------------------------
| O que a regra recusa
|--------------------------------------------------------------------------
*/

it('recusa concluir turma que ainda tem período pela frente', function () {
    $primeiroAno = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 1, 'nome' => '1 B', 'periodo_letivo' => '2026',
    ]);

    expect(fn () => $this->action->executar($primeiroAno, $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'Avance o ano até o último período');

    expect($primeiroAno->refresh()->status)->toBe(StatusTurma::Ativa);
});

it('recusa concluir duas vezes', function () {
    $this->action->executar($this->turma, $this->paeet);

    expect(fn () => $this->action->executar($this->turma->refresh(), $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'já está Concluída');
});

/*
 * Regra de negócio é recusa explicada, não 403 mudo: a Policy responde
 * só "esta turma é sua?".
 */
it('deixa a turma intacta quando a regra é violada', function () {
    $primeiroAno = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 1, 'nome' => '1 B', 'periodo_letivo' => '2026',
    ]);

    try {
        $this->action->executar($primeiroAno, $this->paeet);
    } catch (RegraDeNegocioException) {
        // esperado
    }

    expect(TurmaHistorico::query()->count())->toBe(0)
        ->and(AlunoHistorico::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Escopo e autorização
|--------------------------------------------------------------------------
*/

it('nega a conclusão ao professor', function () {
    expect(fn () => $this->action->executar($this->turma, $this->professor))
        ->toThrow(AuthorizationException::class);
});

it('nega a conclusão a um PAEET de outro eixo', function () {
    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => $this->action->executar($this->turma, $forasteiro))
        ->toThrow(AuthorizationException::class);
});

/*
|--------------------------------------------------------------------------
| O efeito prático
|--------------------------------------------------------------------------
*/

it('tira a turma concluída da lista de quem pode receber prova', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioSolicitacao::class)
        ->assertSee('2 A');

    $this->action->executar($this->turma, $this->paeet);

    Livewire::actingAs($this->paeet)
        ->test(FormularioSolicitacao::class)
        ->assertDontSee('2 A');
});

/*
|--------------------------------------------------------------------------
| A tela
|--------------------------------------------------------------------------
*/

it('oferece concluir no último período, e avançar antes dele', function () {
    $primeiroAno = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 1, 'nome' => '1 B', 'periodo_letivo' => '2026',
    ]);

    Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $primeiroAno])
        ->assertSee('Avançar ano')
        ->assertDontSee('Concluir turma');

    Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma])
        ->assertSee('Concluir turma')
        ->assertDontSee('Avançar ano');
});

it('diz o que vai acontecer antes de concluir', function () {
    Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma])
        ->call('abrirPainelConclusao')
        ->assertSee('2 aluno(s) ativo(s) passam a')
        ->assertSee('Concluinte')
        ->assertSee('deixa de aparecer ao abrir');
});

it('conclui pela tela e avisa sem sair dela', function () {
    Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma])
        ->call('abrirPainelConclusao')
        ->set('observacoesConclusao', 'Turma de 2026')
        ->call('concluirTurma')
        ->assertDispatched('notificar')
        ->assertSet('painelConclusaoAberto', false)
        ->assertNoRedirect();

    expect($this->turma->refresh()->status)->toBe(StatusTurma::Concluida)
        ->and(TurmaHistorico::query()->sole()->observacoes)->toBe('Turma de 2026');
});

/*
 * O botão sumir é metade da resposta. A outra é dizer por que sumiu — e
 * uma turma encerrada precisa parecer encerrada, não apenas ter menos
 * botões que as outras.
 */
it('explica na tela por que a turma concluída não tem mais ações', function () {
    $this->action->executar($this->turma, $this->paeet);

    Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma->refresh()])
        ->assertSee('Turma Concluída')
        ->assertSee('não avança mais de período')
        ->assertDontSee('Concluir turma')
        ->assertDontSee('Avançar ano');
});

/*
|--------------------------------------------------------------------------
| O percurso inteiro
|--------------------------------------------------------------------------
*/

it('vai do primeiro período ao encerramento, passo a passo', function () {
    $turma = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 1, 'nome' => '1 C', 'periodo_letivo' => '2026',
    ]);

    Aluno::factory()->naTurma($turma)->create(['nome' => 'Ana Dias', 'ra' => '3001']);
    Aluno::factory()->naTurma($turma)->create(['nome' => 'Luís Reis', 'ra' => '3002']);

    $avancada = app(AvancarTurmaAction::class)->executar($turma, $this->paeet)['turma'];

    expect($avancada->periodo)->toBe(2)
        ->and($avancada->nome)->toBe('2 C')
        ->and($avancada->podeAvancar())->toBeFalse()
        ->and($avancada->podeConcluir())->toBeTrue();

    $resultado = $this->action->executar($avancada, $this->paeet);

    expect($resultado['turma']->status)->toBe(StatusTurma::Concluida)
        ->and($resultado['concluintes'])->toBe(2)
        // Avanço e encerramento, nesta ordem, no histórico da turma.
        ->and(TurmaHistorico::query()->where('turma_id', $turma->id)
            ->orderBy('id')->pluck('evento')->all())
        ->toBe([EventoHistorico::TurmaAvancoAno, EventoHistorico::TurmaEncerrada]);
});
