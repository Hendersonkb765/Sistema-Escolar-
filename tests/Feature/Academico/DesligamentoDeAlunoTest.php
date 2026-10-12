<?php

/*
 * Retirar um aluno da turma.
 *
 * Saiu não é sumiu: o vínculo com a turma permanece, porque as notas que
 * ele tirou e o histórico dele só fazem sentido presos à turma em que
 * foram tirados. O que muda é a situação — ele deixa de contar como
 * ativo.
 *
 * É diferente de trocar de turma (quem continua estudando) e de excluir
 * o cadastro (matrícula duplicada, que nunca teve nota).
 */

use App\Actions\Academico\ConcluirTurmaAction;
use App\Actions\Academico\DesligarAlunoAction;
use App\Actions\Academico\MoverAlunoDeTurmaAction;
use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\AnalisarDesempenhoAction;
use App\Enums\EventoHistorico;
use App\Enums\StatusAluno;
use App\Enums\StatusProva;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Turmas\DetalheTurma;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica de Programação']], duracaoAnos: 2);

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026',
    ]);

    $this->marina = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Marina Alves', 'ra' => '2001']);
    $this->caio = Aluno::factory()->naTurma($this->turma)
        ->create(['nome' => 'Caio Prado', 'ra' => '2002']);

    $this->disciplina = $montagem['disciplinas']['Lógica de Programação'];

    $this->action = app(DesligarAlunoAction::class);

    /**
     * Uma prova aplicada nesta turma, pelo fluxo de verdade — não há
     * `ProvaFactory`, e a análise lê o snapshot que a montagem grava.
     */
    $this->provaDaTurma = function (): Prova {
        $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
            ['disciplina' => $this->disciplina, 'professor' => $this->professor, 'questoes' => 2],
        ], titulo: 'Bimestral');

        enviarParte($solicitacao->partes()->sole(), $this->professor);

        foreach ($solicitacao->questoes()->get() as $questao) {
            app(AnalisarQuestaoAction::class)->aprovar($questao, $this->paeet);
        }

        $prova = app(MontarProvaAction::class)->executar(
            autor: $this->paeet,
            turma: $this->turma,
            modelo: ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]),
            titulo: 'Bimestral',
        );

        $prova->update(['status' => StatusProva::Aplicada]);

        return $prova->refresh();
    };
});

/*
|--------------------------------------------------------------------------
| A regra
|--------------------------------------------------------------------------
*/

it('retira o aluno da turma marcando a saída', function () {
    $aluno = $this->action->executar(
        aluno: $this->marina,
        autor: $this->paeet,
        situacao: StatusAluno::Transferido,
        motivo: 'Mudou de cidade',
    );

    expect($aluno->status)->toBe(StatusAluno::Transferido)
        // O vínculo permanece: as notas dele são desta turma.
        ->and($aluno->turma_id)->toBe($this->turma->id);
});

it('grava a saída no histórico, com o motivo', function () {
    $this->action->executar($this->marina, $this->paeet, StatusAluno::Inativo, 'Parou de frequentar em março');

    $registro = AlunoHistorico::query()->sole();

    expect($registro->evento)->toBe(EventoHistorico::AlunoStatusAlterado)
        ->and($registro->aluno_id)->toBe($this->marina->id)
        ->and($registro->status_anterior)->toBe('ativo')
        ->and($registro->status_novo)->toBe('inativo')
        ->and($registro->motivo)->toBe('Parou de frequentar em março')
        ->and($registro->registrado_por)->toBe($this->paeet->id)
        // Mesma turma dos dois lados: mudou a situação, não o lugar.
        ->and($registro->turma_anterior_id)->toBe($this->turma->id)
        ->and($registro->turma_nova_id)->toBe($this->turma->id);
});

it('registra a saída na auditoria', function () {
    $this->action->executar($this->marina, $this->paeet, StatusAluno::Transferido, 'Transferência');

    $registro = Activity::query()->where('log_name', 'aluno')->latest('id')->first();

    expect($registro->description)->toContain('Aluno desligado da turma como Transferido')
        ->and($registro->properties['motivo'])->toBe('Transferência')
        ->and($registro->causer_id)->toBe($this->paeet->id);
});

it('não mexe nos outros alunos da turma', function () {
    $this->action->executar($this->marina, $this->paeet, StatusAluno::Transferido, 'Mudou de cidade');

    expect($this->caio->refresh()->status)->toBe(StatusAluno::Ativo)
        ->and(AlunoHistorico::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| O que a regra recusa
|--------------------------------------------------------------------------
*/

it('recusa retirar quem já saiu', function () {
    $this->action->executar($this->marina, $this->paeet, StatusAluno::Transferido, 'Mudou de cidade');

    expect(fn () => $this->action->executar(
        $this->marina->refresh(), $this->paeet, StatusAluno::Inativo, 'De novo'
    ))->toThrow(RegraDeNegocioException::class, 'já está Transferido');
});

it('recusa retirar quem concluiu o curso', function () {
    $turma = Turma::factory()->doCurso($this->turma->curso, $this->turma->grade)->create([
        'periodo' => 2, 'nome' => '2 A', 'periodo_letivo' => '2026',
    ]);

    $formando = Aluno::factory()->naTurma($turma)->create(['nome' => 'Rita Nunes', 'ra' => '2003']);

    app(ConcluirTurmaAction::class)->executar($turma, $this->paeet);

    expect(fn () => $this->action->executar(
        $formando->refresh(), $this->paeet, StatusAluno::Inativo, 'Tentativa'
    ))->toThrow(RegraDeNegocioException::class, 'já está Concluinte');
});

/*
 * Concluinte não é uma saída: é o fim do percurso, e quem o atribui é a
 * conclusão da turma. Deixá-lo passar aqui criaria formando por engano.
 */
it('recusa usar o desligamento para marcar concluinte', function () {
    expect(fn () => $this->action->executar(
        $this->marina, $this->paeet, StatusAluno::Concluinte, 'Terminou'
    ))->toThrow(RegraDeNegocioException::class, 'Concluinte é o fim do curso');

    expect($this->marina->refresh()->status)->toBe(StatusAluno::Ativo);
});

it('recusa retirar sem dizer por quê', function () {
    expect(fn () => $this->action->executar(
        $this->marina, $this->paeet, StatusAluno::Transferido, '   '
    ))->toThrow(RegraDeNegocioException::class, 'Diga por que o aluno está saindo');

    expect($this->marina->refresh()->status)->toBe(StatusAluno::Ativo)
        ->and(AlunoHistorico::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Escopo e autorização
|--------------------------------------------------------------------------
*/

it('nega o desligamento ao professor', function () {
    expect(fn () => $this->action->executar(
        $this->marina, $this->professor, StatusAluno::Transferido, 'Tentativa'
    ))->toThrow(AuthorizationException::class);
});

it('nega o desligamento a um PAEET de outro eixo', function () {
    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => $this->action->executar(
        $this->marina, $forasteiro, StatusAluno::Transferido, 'Tentativa'
    ))->toThrow(AuthorizationException::class);
});

/*
 * O id vem do navegador. Sem recortar pela turma da tela, um id de outra
 * turma — ou de outro Eixo — seria desligado a partir daqui.
 */
it('não deixa a tela de uma turma desligar aluno de outra', function () {
    $outraTurma = Turma::factory()->doCurso($this->turma->curso, $this->turma->grade)->create([
        'periodo' => 1, 'nome' => '1 B', 'periodo_letivo' => '2026',
    ]);

    $deFora = Aluno::factory()->naTurma($outraTurma)->create(['nome' => 'Rui Sá', 'ra' => '3001']);

    $componente = Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma]);

    // `firstOrFail` no relacionamento da turma: o id não resolve, e em
    // HTTP isto é o 404 do handler.
    expect(fn () => $componente->call('abrirDesligamento', $deFora->id))
        ->toThrow(ModelNotFoundException::class);

    expect($deFora->refresh()->status)->toBe(StatusAluno::Ativo);
});

/*
|--------------------------------------------------------------------------
| O efeito prático
|--------------------------------------------------------------------------
*/

/*
 * Quem saiu no meio do bimestre não é "pendente": ele não deveria ter
 * feito a prova. Sem isto, cada desligamento rebaixaria a turma por um
 * dado que não existe.
 */
it('tira quem saiu da contagem de pendentes da análise', function () {
    $prova = ($this->provaDaTurma)();

    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::whereKey($prova->id)->get(), $this->paeet);

    expect($geral['matriculados'])->toBe(2);

    $this->action->executar($this->marina, $this->paeet, StatusAluno::Transferido, 'Mudou de cidade');

    $geral = app(AnalisarDesempenhoAction::class)
        ->visaoGeral(Prova::whereKey($prova->id)->get(), $this->paeet);

    expect($geral['matriculados'])->toBe(1);
});

it('deixa de contar quem saiu ao avançar ou concluir a turma', function () {
    $this->action->executar($this->marina, $this->paeet, StatusAluno::Transferido, 'Mudou de cidade');

    $turmaFinal = Turma::factory()->doCurso($this->turma->curso, $this->turma->grade)->create([
        'periodo' => 2, 'nome' => '2 B', 'periodo_letivo' => '2026',
    ]);

    app(MoverAlunoDeTurmaAction::class)
        ->executar($this->caio, $turmaFinal, $this->paeet);

    $resultado = app(ConcluirTurmaAction::class)->executar($turmaFinal, $this->paeet);

    expect($resultado['concluintes'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| A tela
|--------------------------------------------------------------------------
*/

it('oferece retirar só para quem está ativo', function () {
    $componente = Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma]);

    // Duas linhas, dois botões.
    expect(substr_count($componente->html(), 'abrirDesligamento'))->toBe(2);

    $this->action->executar($this->marina, $this->paeet, StatusAluno::Transferido, 'Mudou de cidade');

    $componente = Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma]);

    expect(substr_count($componente->html(), 'abrirDesligamento'))->toBe(1);
});

it('mostra quem saiu na lista, com o selo da situação', function () {
    $this->action->executar($this->marina, $this->paeet, StatusAluno::Transferido, 'Mudou de cidade');

    Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma])
        // Continua na lista: quem saiu não some.
        ->assertSee('Marina Alves')
        ->assertSee('Transferido')
        // E a contagem passa a separar matriculados de ativos.
        ->assertSee('2 matriculado(s) · 1 ativo(s)');
});

it('diz o que vai acontecer antes de retirar', function () {
    Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma])
        ->call('abrirDesligamento', $this->marina->id)
        ->assertSet('situacaoDesligamento', StatusAluno::Transferido->value)
        ->assertSee('Retirar Marina Alves da turma')
        ->assertSee('deixa de ser contado(a) como ativo(a)')
        ->assertSee('Saiu para outra escola');
});

it('retira pela tela e avisa sem sair dela', function () {
    Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma])
        ->call('abrirDesligamento', $this->marina->id)
        ->set('situacaoDesligamento', StatusAluno::Inativo->value)
        ->set('motivoDesligamento', 'Parou de frequentar')
        ->call('desligarAluno')
        ->assertDispatched('notificar')
        ->assertSet('alunoDesligando', null)
        ->assertNoRedirect();

    expect($this->marina->refresh()->status)->toBe(StatusAluno::Inativo)
        ->and(AlunoHistorico::query()->sole()->motivo)->toBe('Parou de frequentar');
});

it('explica na tela quando falta o motivo, sem desligar', function () {
    Livewire::actingAs($this->paeet)
        ->test(DetalheTurma::class, ['turma' => $this->turma])
        ->call('abrirDesligamento', $this->marina->id)
        ->call('desligarAluno')
        ->assertDispatched('notificar')
        // O painel continua aberto: dá para corrigir sem reabrir.
        ->assertSet('alunoDesligando', $this->marina->id);

    expect($this->marina->refresh()->status)->toBe(StatusAluno::Ativo);
});
