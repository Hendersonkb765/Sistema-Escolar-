<?php

/*
 * Eixo, aluno, usuário, grade, solicitação, prova, importação e modelos
 * também vão para a Lixeira, com o mesmo cuidado de curso e turma: a
 * confirmação diz o que sai junto, e o que não pode sair agora diz por
 * quê, no lugar do botão.
 */

use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Documento\CompartilharModeloAction;
use App\Actions\Exclusao\ExcluirRegistroAction;
use App\Actions\Exclusao\RestaurarExclusaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Resultado\AnalisarDesempenhoAction;
use App\Enums\StatusImportacao;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Alunos\ListaAlunos;
use App\Livewire\Eixos\ListaEixos;
use App\Livewire\Grades\DetalheGrade;
use App\Livewire\Importacoes\ListaImportacoes;
use App\Livewire\Provas\DetalheProva;
use App\Livewire\Usuarios\ListaUsuarios;
use App\Models\Aluno;
use App\Models\CompartilhamentoDeModelo;
use App\Models\Curso;
use App\Models\Eixo;
use App\Models\Exclusao;
use App\Models\GradeCurricular;
use App\Models\Importacao;
use App\Models\ModeloDocumento;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Questao;
use App\Models\ResultadoAluno;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia']);
    $this->admin = paeetAdmin($this->eixo);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);
    $this->professor->update(['nome' => 'Renato Lima']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->paeet);
    $this->curso = $montagem['curso'];
    $this->grade = $montagem['grade'];
    $this->logica = $montagem['disciplinas']['Lógica'];

    $this->turma = Turma::factory()->doCurso($this->curso, $this->grade)
        ->create(['periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026']);
    $this->marina = Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Marina Alves', 'ra' => '5001']);
    $this->caio = Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Caio Prado', 'ra' => '5002']);

    $this->solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->professor, 'questoes' => 2],
    ], titulo: 'Avaliação bimestral');
    enviarParte($this->solicitacao->partes()->sole(), $this->professor);
    foreach ($this->solicitacao->questoes()->get() as $questao) {
        app(AnalisarQuestaoAction::class)->aprovar($questao, $this->paeet);
    }

    $this->modelo = ModeloProva::factory()->create(['eixo_id' => $this->eixo->id, 'nome' => 'Padrão da escola']);
    $this->prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo, titulo: 'Avaliação bimestral',
    );
    $this->importacao = Importacao::query()->create([
        'prova_id' => $this->prova->id, 'usuario_id' => $this->paeet->id, 'arquivo' => 'x.xlsx',
        'hash' => hash('sha256', 'x'), 'status' => StatusImportacao::Confirmada, 'confirmada_em' => now(),
    ]);
    foreach ([$this->marina, $this->caio] as $aluno) {
        ResultadoAluno::query()->create(['prova_id' => $this->prova->id, 'aluno_id' => $aluno->id, 'importacao_id' => $this->importacao->id]);
    }

    $this->excluir = fn ($registro, $quem = null) => app(ExcluirRegistroAction::class)->executar($registro, $quem ?? $this->paeet);
    $this->restaurar = fn (Exclusao $lote) => app(RestaurarExclusaoAction::class)->executar($lote, $this->admin);
});

// --- Aluno ------------------------------------------------------------------

it('exclui o aluno com os resultados dele, que deixam de contar nas notas', function () {
    $plano = app(ExcluirRegistroAction::class)->planejar($this->marina);

    expect($plano->contagens)->toBe(['resultado(s) de aluno' => 1])
        ->and($plano->confirmacoes)->toBe(1);

    Livewire::actingAs($this->paeet)->test(ListaAlunos::class)
        ->call('pedirExclusao', 'aluno', $this->marina->id)
        ->assertSee('Excluir Aluno Marina Alves (RA 5001)?')
        ->call('confirmarExclusao');

    expect(Aluno::query()->whereKey($this->marina->id)->exists())->toBeFalse()
        ->and($this->prova->resultados()->pluck('aluno_id')->all())->toBe([$this->caio->id]);

    ($this->restaurar)(Exclusao::query()->sole());

    expect($this->prova->resultados()->count())->toBe(2);
});

/*
 * A análise lê as respostas pela questão, sem passar pelo resultado. Sem
 * filtrar, a resposta de um aluno excluído chegava com o resultado nulo, e
 * a tela da questão quebrava.
 */
it('tira da análise da questão as respostas do aluno excluído', function () {
    $questao = $this->prova->questoes()->orderBy('numero')->first();

    foreach (ResultadoAluno::query()->get() as $resultado) {
        $resultado->respostas()->create([
            'prova_questao_id' => $questao->id, 'acertou' => true,
            'alternativa_marcada' => $questao->letra_correta, 'peso' => $questao->peso, 'pontuacao' => $questao->peso,
        ]);
    }

    ($this->excluir)($this->marina);

    expect(app(AnalisarDesempenhoAction::class)->respostasDaQuestao($questao, $this->paeet)->pluck('aluno')->all())
        ->toBe(['Caio Prado']);
});

// --- Prova e importação -----------------------------------------------------

it('exclui a prova já aplicada, com a leitura da folha e os resultados, saindo da tela dela', function () {
    $tela = Livewire::actingAs($this->paeet)->test(DetalheProva::class, ['prova' => $this->prova])
        ->call('pedirExclusao', 'prova', $this->prova->id);

    expect($tela->get('exclusao')['contagens'])->toBe(['importação(ões) de resultados' => 1, 'resultado(s) de aluno' => 2]);

    $tela->call('confirmarExclusao')->assertRedirect(route('provas.index'));

    expect(Prova::query()->whereKey($this->prova->id)->exists())->toBeFalse()
        ->and(ResultadoAluno::query()->count())->toBe(0)
        // As questões continuam no banco.
        ->and(Questao::query()->where('solicitacao_id', $this->solicitacao->id)->count())->toBe(2);
});

it('exclui a leitura errada da folha, e a prova volta a ficar sem resultado', function () {
    Livewire::actingAs($this->paeet)->test(ListaImportacoes::class)
        ->call('pedirExclusao', 'importacao', $this->importacao->id)
        ->assertSee('2 resultado(s) de aluno')
        ->call('confirmarExclusao')
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'sucesso');

    expect($this->prova->fresh()->resultados()->count())->toBe(0)
        ->and(Importacao::query()->whereKey($this->importacao->id)->exists())->toBeFalse();

    $this->actingAs($this->paeet)->get(route('importacoes.index'))->assertDontSeeHtml("pedirExclusao('importacao', {$this->importacao->id})");
});

// --- Solicitação ------------------------------------------------------------

it('exclui a solicitação com as questões, avisando que a prova já montada continua', function () {
    $plano = app(ExcluirRegistroAction::class)->planejar($this->solicitacao);

    expect($plano->contagens)->toBe(['questão(ões)' => 2])
        ->and($plano->avisos)->toBe(['1 prova(s) já montada(s) com estas questões continuam como estão.']);

    ($this->excluir)($this->solicitacao);

    // A prova montada segue de pé, com as questões dela.
    $this->actingAs($this->paeet)->get(route('provas.show', $this->prova))->assertOk();
    expect(SolicitacaoProva::query()->whereKey($this->solicitacao->id)->exists())->toBeFalse();
});

// --- Modelos ----------------------------------------------------------------

it('exclui o modelo de prova, e a prova montada com ele continua abrindo', function () {
    expect(app(ExcluirRegistroAction::class)->planejar($this->modelo)->avisos)
        ->toBe(['1 prova(s) já montada(s) com ele continuam com este layout.']);

    ($this->excluir)($this->modelo);

    $this->actingAs($this->paeet)->get(route('provas.show', $this->prova))->assertOk();
    $this->actingAs($this->paeet)->get(route('provas.pdf', $this->prova))->assertOk();
    $this->actingAs($this->paeet)->get(route('modelos-prova.index'))->assertDontSee('Padrão da escola');
});

it('exclui o modelo de documento com as ofertas de compartilhamento dele', function () {
    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);
    $destino = paeet($outroEixo);
    $documento = ModeloDocumento::factory()->noEixo($this->eixo)->create(['nome' => 'Declaração', 'criado_por' => $this->paeet->id]);
    app(CompartilharModeloAction::class)->executar($documento, $this->paeet, $destino);

    expect(app(ExcluirRegistroAction::class)->planejar($documento)->contagens)->toBe(['compartilhamento(s) de modelo' => 1]);

    ($this->excluir)($documento);

    expect(CompartilhamentoDeModelo::query()->count())->toBe(0);
    $this->actingAs($destino)->get(route('documentos.compartilhados'))->assertOk()->assertDontSee('Declaração');
});

// --- Grade ------------------------------------------------------------------

it('não exclui a grade usada por turmas, e diz quais e o que fazer, no lugar do botão', function () {
    $tela = Livewire::actingAs($this->paeet)->test(DetalheGrade::class, ['grade' => $this->grade])
        ->call('pedirExclusao', 'grade', $this->grade->id)
        ->assertSee('Não dá para excluir Grade versão 1 agora')
        ->assertSee('A grade versão 1 é usada pela(s) turma(s) 1 A. Troque a grade delas, na tela de cada turma, antes de excluir esta versão.')
        // No diálogo, só "Fechar": não há o que confirmar.
        ->assertDontSeeHtml('wire:click="confirmarExclusao"');

    // Confirmar não faz nada: não há o que confirmar.
    $tela->call('confirmarExclusao');

    expect(GradeCurricular::query()->whereKey($this->grade->id)->exists())->toBeTrue();

    expect(fn () => ($this->excluir)($this->grade))
        ->toThrow(RegraDeNegocioException::class, 'é usada pela(s) turma(s) 1 A');
});

it('exclui a versão de grade que nenhuma turma usa', function () {
    $v2 = app(PublicarVersaoDeGradeAction::class)->executar($this->curso, $this->paeet);

    Livewire::actingAs($this->paeet)->test(DetalheGrade::class, ['grade' => $v2])
        ->call('pedirExclusao', 'grade', $v2->id)
        ->call('confirmarExclusao')
        ->assertRedirect(route('grades.index'));

    expect(GradeCurricular::query()->whereKey($v2->id)->exists())->toBeFalse();
});

// --- Usuário ----------------------------------------------------------------

it('exclui a conta criada por engano, que deixa de entrar no sistema', function () {
    $engano = professor($this->eixo);
    $engano->update(['nome' => 'Conta Duplicada', 'email' => 'duplicada@exemplo.test']);

    Livewire::actingAs($this->paeet)->test(ListaUsuarios::class)
        ->call('pedirExclusao', 'usuario', $engano->id)
        ->assertSee('Excluir Usuário Conta Duplicada (duplicada@exemplo.test)?')
        ->call('confirmarExclusao');

    expect(User::query()->whereKey($engano->id)->exists())->toBeFalse();

    // O login procura a conta pelo e-mail, e na Lixeira ela não é achada.
    expect(Auth::getProvider()->retrieveByCredentials(['email' => 'duplicada@exemplo.test']))->toBeNull();
});

it('não exclui a conta com histórico, e sugere desativar', function () {
    Livewire::actingAs($this->admin)->test(ListaUsuarios::class)
        ->call('pedirExclusao', 'usuario', $this->professor->id)
        ->assertSee('Renato Lima tem 2 questão(ões) e 1 parte(s) de solicitação.')
        ->assertSee('desative-a em vez de excluir');

    expect(fn () => ($this->excluir)($this->professor, $this->admin))->toThrow(RegraDeNegocioException::class);
});

it('não deixa excluir a própria conta nem a de um PAEET Admin', function () {
    expect($this->paeet->can('delete', $this->paeet))->toBeFalse()
        ->and($this->paeet->can('delete', $this->admin))->toBeFalse()
        ->and($this->admin->can('delete', $this->paeet))->toBeTrue();
});

// --- Eixo -------------------------------------------------------------------

it('exclui o Eixo inteiro em duas confirmações, e o PAEET Admin o restaura', function () {
    $tela = Livewire::actingAs($this->admin)->test(ListaEixos::class)
        ->call('pedirExclusao', 'eixo', $this->eixo->id);

    expect($tela->get('exclusao')['etapas'])->toBe(2)
        ->and($tela->get('exclusao')['contagens'])->toMatchArray(['curso(s)' => 1, 'turma(s)' => 1, 'aluno(s)' => 2, 'modelo(s) de prova' => 1])
        ->and($tela->get('exclusao')['avisos'])->toBe(['3 usuário(s) vinculado(s) deixam de ver o que é deste Eixo enquanto ele estiver na Lixeira.']);

    $tela->call('confirmarExclusao')->assertSee('Confirme de novo')->call('confirmarExclusao');

    expect(Eixo::query()->whereKey($this->eixo->id)->exists())->toBeFalse()
        ->and(Curso::query()->whereKey($this->curso->id)->exists())->toBeFalse();

    ($this->restaurar)(Exclusao::query()->sole());

    expect(Turma::query()->whereKey($this->turma->id)->exists())->toBeTrue();
});

// --- Acesso -----------------------------------------------------------------

it('não deixa o professor nem o PAEET de outro Eixo excluir', function () {
    $deFora = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    foreach ([$this->marina, $this->prova, $this->importacao, $this->solicitacao, $this->modelo] as $registro) {
        expect($this->professor->can('delete', $registro))->toBeFalse()
            ->and($deFora->can('delete', $registro))->toBeFalse();
    }

    Livewire::actingAs($deFora)->test(ListaAlunos::class)->call('pedirExclusao', 'aluno', $this->marina->id)->assertForbidden();
    expect($this->paeet->can('delete', $this->eixo))->toBeFalse();
});

it('põe o botão de excluir nas listas para quem pode', function () {
    foreach ([
        ['alunos.index', "pedirExclusao('aluno', {$this->marina->id})"],
        ['provas.index', "pedirExclusao('prova', {$this->prova->id})"],
        ['importacoes.index', "pedirExclusao('importacao', {$this->importacao->id})"],
        ['solicitacoes.index', "pedirExclusao('solicitacao', {$this->solicitacao->id})"],
        ['modelos-prova.index', "pedirExclusao('modelo-prova', {$this->modelo->id})"],
        ['grades.index', "pedirExclusao('grade', {$this->grade->id})"],
    ] as [$rota, $chamada]) {
        $this->actingAs($this->paeet)->get(route($rota))->assertOk()->assertSeeHtml($chamada);
    }

    $this->actingAs($this->admin)->get(route('eixos.index'))->assertSeeHtml("pedirExclusao('eixo', {$this->eixo->id})");
});
