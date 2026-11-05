<?php

/*
 * Curso, turma e disciplina podem ser excluídos por quem os administra —
 * cadastro errado precisa ter conserto sem abrir o banco. Mas nada sai
 * sozinho nem sem aviso:
 *
 * - a exclusão leva o que depende do registro, e a confirmação diz o quê;
 * - turma e curso pedem duas confirmações, a segunda reforçando o risco;
 * - tudo vai para a Lixeira, de onde o PAEET Admin restaura o lote inteiro;
 * - histórico e fotos (grade publicada, histórico de aluno) ficam, com o
 *   nome do excluído.
 */

use App\Actions\Academico\MoverAlunoDeTurmaAction;
use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Exclusao\ExcluirRegistroAction;
use App\Actions\Exclusao\RestaurarExclusaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Enums\DiaDaSemana;
use App\Enums\StatusImportacao;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Cursos\DetalheCurso;
use App\Livewire\Disciplinas\FormularioDisciplina;
use App\Livewire\Disciplinas\ListaDisciplinas;
use App\Livewire\Lixeira\Lixeira;
use App\Livewire\Turmas\DetalheTurma;
use App\Livewire\Turmas\FormularioTurma;
use App\Livewire\Turmas\ListaTurmas;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Exclusao;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\ProfessorDisciplina;
use App\Models\Prova;
use App\Models\QuadroHorario;
use App\Models\Questao;
use App\Models\ResultadoAluno;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], autor: $this->paeet);
    $this->curso = $montagem['curso'];
    $this->curso->update(['nome' => 'Desenvolvimento de Sistemas']);
    $this->logica = $montagem['disciplinas']['Lógica'];
    $this->redes = $montagem['disciplinas']['Redes'];

    $this->turma = Turma::factory()->doCurso($this->curso, $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026']);
    $this->outra = Turma::factory()->doCurso($this->curso, $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 B', 'periodo_letivo' => '2026']);

    $this->marina = Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Marina Alves', 'ra' => '5001']);
    $this->caio = Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Caio Prado', 'ra' => '5002']);

    ProfessorDisciplina::query()->create([
        'usuario_id' => $this->professor->id, 'disciplina_id' => $this->logica->id, 'turma_id' => null, 'ativo' => true,
    ]);

    // Uma solicitação com as questões enviadas e aprovadas, e a prova montada com elas.
    $this->solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->professor, 'questoes' => 2],
    ], titulo: 'Avaliação bimestral');
    enviarParte($this->solicitacao->partes()->sole(), $this->professor);
    foreach ($this->solicitacao->questoes()->get() as $questao) {
        app(AnalisarQuestaoAction::class)->aprovar($questao, $this->paeet);
    }
    $this->prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma,
        modelo: ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]),
        titulo: 'Avaliação bimestral',
    );
    $importacao = Importacao::query()->create([
        'prova_id' => $this->prova->id, 'usuario_id' => $this->paeet->id, 'arquivo' => 'x.xlsx',
        'hash' => hash('sha256', 'x'), 'status' => StatusImportacao::Confirmada, 'confirmada_em' => now(),
    ]);
    foreach ([$this->marina, $this->caio] as $aluno) {
        ResultadoAluno::query()->create(['prova_id' => $this->prova->id, 'aluno_id' => $aluno->id, 'importacao_id' => $importacao->id]);
    }

    $this->aula = QuadroHorario::query()->create([
        'turma_id' => $this->turma->id, 'dia_semana' => DiaDaSemana::Segunda, 'aula_numero' => 1,
        'hora_inicio' => '07:00', 'hora_fim' => '07:50',
        'disciplina_id' => $this->logica->id, 'professor_id' => $this->professor->id,
    ]);

    $this->listaDeTurmas = fn ($quem = null) => Livewire::actingAs($quem ?? $this->paeet)->test(ListaTurmas::class);
});

// --- O impacto ------------------------------------------------------------

it('diz tudo o que sai junto com a turma, e o que só deixa de aparecer', function () {
    $plano = app(ExcluirRegistroAction::class)->planejar($this->turma);

    expect($plano->rotulo)->toBe('Turma 1 A (2026)')
        ->and($plano->contagens)->toBe([
            'aluno(s)' => 2,
            'prova(s)' => 1,
            'solicitação(ões) de questões' => 1,
            'questão(ões)' => 2,
        ])
        ->and($plano->avisos)->toBe([
            '2 resultado(s) de prova lançado(s) deixam de aparecer nas notas e na análise.',
            '1 aula(s) do quadro de horários deixam de aparecer na escala.',
            'O histórico da turma é mantido.',
        ])
        ->and($plano->confirmacoes)->toBe(2);
});

// --- Duas confirmações ----------------------------------------------------

it('pede duas confirmações para excluir a turma, a segunda reforçando o risco', function () {
    $tela = ($this->listaDeTurmas)()
        ->call('pedirExclusao', 'turma', $this->turma->id)
        ->assertSee('Excluir Turma 1 A (2026)?')
        ->assertSee('2 aluno(s)')
        ->assertSee('2 resultado(s) de prova lançado(s) deixam de aparecer')
        ->assertSeeHtml('data-etapa="1"');

    $tela->call('confirmarExclusao')
        ->assertSee('Confirme de novo: excluir Turma 1 A (2026)')
        ->assertSee('Esta exclusão afeta outros registros e pode comprometer dados relacionados.')
        ->assertSeeHtml('data-etapa="2"');

    // Duas confirmações e nada saiu ainda.
    expect(Turma::query()->whereKey($this->turma->id)->exists())->toBeTrue();

    $tela->call('confirmarExclusao')
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'sucesso'
            && $dados['mensagem'] === 'Turma 1 A (2026) foi para a Lixeira. Se foi engano, o PAEET Admin pode restaurar.')
        ->assertSet('exclusao', null);

    expect(Turma::query()->whereKey($this->turma->id)->exists())->toBeFalse()
        ->and(Turma::withTrashed()->find($this->turma->id)->trashed())->toBeTrue()
        ->and(Aluno::query()->where('turma_id', $this->turma->id)->count())->toBe(0)
        ->and(Prova::query()->whereKey($this->prova->id)->exists())->toBeFalse()
        ->and(Exclusao::query()->sole()->itens()->count())->toBe(7);
});

it('não exclui nada quando se cancela, mesmo na segunda confirmação', function () {
    ($this->listaDeTurmas)()
        ->call('pedirExclusao', 'turma', $this->turma->id)
        ->call('confirmarExclusao')
        ->call('cancelarExclusao')
        ->assertSet('exclusao', null);

    expect(Turma::query()->whereKey($this->turma->id)->exists())->toBeTrue()
        ->and(Exclusao::query()->count())->toBe(0);
});

it('não deixa pular a segunda confirmação mexendo na requisição', function () {
    expect(fn () => ($this->listaDeTurmas)()
        ->call('pedirExclusao', 'turma', $this->turma->id)
        ->set('exclusao.etapa', 2))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('pede uma confirmação só para a disciplina, e diz que a grade publicada continua com ela', function () {
    $tela = Livewire::actingAs($this->paeet)->test(ListaDisciplinas::class)
        ->call('pedirExclusao', 'disciplina', $this->redes->id)
        ->assertSee('Excluir Disciplina Redes?')
        ->assertSee('Continua nas 1 grade(s) já publicada(s)');

    expect($tela->get('exclusao')['etapas'])->toBe(1);

    $tela->call('confirmarExclusao');

    expect(Disciplina::query()->whereKey($this->redes->id)->exists())->toBeFalse();
});

it('volta para a lista ao excluir a turma pela tela dela', function () {
    Livewire::actingAs($this->paeet)->test(DetalheTurma::class, ['turma' => $this->turma])
        ->call('pedirExclusao', 'turma', $this->turma->id)
        ->call('confirmarExclusao')
        ->call('confirmarExclusao')
        ->assertRedirect(route('turmas.index'));
});

// --- O que some e o que fica ----------------------------------------------

it('tira a turma e o que é dela de todas as telas', function () {
    app(ExcluirRegistroAction::class)->executar($this->turma, $this->paeet);

    $this->actingAs($this->paeet)->get(route('turmas.index'))->assertDontSee('1 A')->assertSee('1 B');
    $this->actingAs($this->paeet)->get(route('alunos.index'))->assertDontSee('Marina Alves');
    // Pelo link: o aviso antigo do sino ainda cita o título, e é registro do que aconteceu.
    $this->actingAs($this->paeet)->get(route('provas.index'))->assertDontSee(route('provas.show', $this->prova), false);
    $this->actingAs($this->paeet)->get(route('turmas.show', $this->turma))->assertNotFound();
    $this->actingAs($this->professor)->get(route('horarios.meus'))->assertDontSee('Lógica');

    expect(Questao::query()->where('solicitacao_id', $this->solicitacao->id)->count())->toBe(0);
});

it('mantém o histórico de quem passou pela turma, com o nome dela', function () {
    app(MoverAlunoDeTurmaAction::class)->executar($this->marina, $this->outra, $this->paeet, 'Troca de turno');

    app(ExcluirRegistroAction::class)->executar($this->turma, $this->paeet);

    $historico = AlunoHistorico::query()->with('turmaAnterior', 'turmaNova')->where('aluno_id', $this->marina->id)->sole();

    expect($historico->turmaAnterior->nome)->toBe('1 A')
        ->and($historico->turmaNova->nome)->toBe('1 B')
        // A Marina já estava na 1 B: não saiu com a 1 A.
        ->and(Aluno::query()->whereKey($this->marina->id)->exists())->toBeTrue();
});

it('retira as aulas da disciplina do quadro, e as devolve na restauração', function () {
    $vinculos = ProfessorDisciplina::query()->where('disciplina_id', $this->logica->id)->count();

    $lote = app(ExcluirRegistroAction::class)->executar($this->logica, $this->paeet);

    expect(QuadroHorario::query()->whereKey($this->aula->id)->exists())->toBeFalse()
        // Os vínculos de professor ficam: são histórico de quem lecionou o quê.
        ->and(ProfessorDisciplina::query()->where('disciplina_id', $this->logica->id)->count())->toBe($vinculos)
        ->and($this->professor->fresh()->disciplinaIds())->toBe([]);

    app(RestaurarExclusaoAction::class)->executar($lote, $this->admin);

    expect(QuadroHorario::query()->whereKey($this->aula->id)->sole()->horaInicio())->toBe('07:00')
        ->and($this->professor->fresh()->disciplinaIds())->toBe([$this->logica->id]);
});

// --- A Lixeira ------------------------------------------------------------

it('lista na Lixeira o que foi excluído e restaura o lote inteiro', function () {
    app(ExcluirRegistroAction::class)->executar($this->turma, $this->paeet);

    $lixeira = Livewire::actingAs($this->admin)->test(Lixeira::class)
        ->assertSee('Turma 1 A (2026)')
        ->assertSee('Saiu junto: 2 aluno(s), 1 prova(s), 1 solicitação(ões) de questões e 2 questão(ões).');

    $lixeira->call('restaurar', Exclusao::query()->sole()->id)
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'sucesso')
        ->assertDontSee('Turma 1 A (2026)');

    expect(Turma::query()->whereKey($this->turma->id)->exists())->toBeTrue()
        ->and(Aluno::query()->where('turma_id', $this->turma->id)->count())->toBe(2)
        ->and($this->prova->fresh()->resultados()->count())->toBe(2)
        ->and(Exclusao::query()->sole()->restaurado_em)->not->toBeNull();

    $this->actingAs($this->paeet)->get(route('alunos.index'))->assertSee('Marina Alves');
});

it('restaura só o que saiu no lote, e não o que já estava na Lixeira', function () {
    $lia = Aluno::factory()->naTurma($this->turma)->create(['nome' => 'Lia Moraes', 'ra' => '5003']);
    app(ExcluirRegistroAction::class)->executar($lia, $this->paeet);
    $daTurma = app(ExcluirRegistroAction::class)->executar($this->turma->fresh(), $this->paeet);

    app(RestaurarExclusaoAction::class)->executar($daTurma, $this->admin);

    expect(Aluno::query()->whereKey($this->marina->id)->exists())->toBeTrue()
        ->and(Aluno::query()->whereKey($lia->id)->exists())->toBeFalse();
});

it('não restaura a turma enquanto o curso dela estiver na Lixeira', function () {
    $daTurma = app(ExcluirRegistroAction::class)->executar($this->turma, $this->paeet);
    app(ExcluirRegistroAction::class)->executar($this->curso->fresh(), $this->paeet);

    expect(fn () => app(RestaurarExclusaoAction::class)->executar($daTurma, $this->admin))
        ->toThrow(RegraDeNegocioException::class, 'Turma 1 A (2026) pertence a Curso Desenvolvimento de Sistemas, que também está na Lixeira. Restaure-o antes.');
});

/*
 * O que sai por exclusão lógica continua ocupando o nome no banco, e o
 * formulário avisa que ele está na Lixeira: não há como ocupar o lugar.
 * A aula do quadro, que sai de fato, pode ter o horário ocupado depois.
 */
it('diz o que impede restaurar quando o horário da aula foi ocupado depois', function () {
    $lote = app(ExcluirRegistroAction::class)->executar($this->logica, $this->paeet);
    QuadroHorario::query()->create([
        'turma_id' => $this->turma->id, 'dia_semana' => DiaDaSemana::Segunda, 'aula_numero' => 1,
        'hora_inicio' => '07:00', 'hora_fim' => '07:50',
        'disciplina_id' => $this->redes->id, 'professor_id' => $this->professor->id,
    ]);

    Livewire::actingAs($this->admin)->test(Lixeira::class)
        ->call('restaurar', $lote->id)
        ->assertDispatched('notificar', fn (string $e, array $dados) => $dados['tipo'] === 'erro'
            && str_starts_with($dados['mensagem'], 'Não deu para restaurar Disciplina Lógica: depois da exclusão, foi cadastrado outro registro'));

    // Nada voltou pela metade.
    expect(Disciplina::withTrashed()->find($this->logica->id)->trashed())->toBeTrue()
        ->and($lote->fresh()->restaurado_em)->toBeNull();
});

it('avisa que o nome está na Lixeira ao cadastrar uma turma igual à excluída', function () {
    app(ExcluirRegistroAction::class)->executar($this->turma, $this->paeet);

    Livewire::actingAs($this->paeet)->test(FormularioTurma::class)
        ->set('curso_id', (string) $this->curso->id)
        ->set('grade_curricular_id', (string) $this->turma->grade_curricular_id)
        ->set('periodo', '1')
        ->set('nome', '1 A')
        ->set('periodo_letivo', '2026')
        ->call('salvar')
        ->assertHasErrors(['nome'])
        ->assertSee('está na Lixeira. O PAEET Admin pode restaurá-la por lá.');
});

it('não aceita cadastrar disciplina num curso que está na Lixeira', function () {
    $outro = cursoComGrade($this->eixo, [1 => ['Banco de dados']], autor: $this->paeet)['curso'];
    app(ExcluirRegistroAction::class)->executar($outro, $this->paeet);

    Livewire::actingAs($this->paeet)->test(FormularioDisciplina::class)
        ->set('curso_id', (string) $outro->id)
        ->set('nome', 'Nova')
        ->set('codigo', 'NOV')
        ->set('periodo', '1')
        ->call('salvar')
        ->assertHasErrors(['curso_id']);
});

// --- Curso ------------------------------------------------------------------

it('exclui o curso com turmas, disciplinas e grades, em duas confirmações, saindo da tela dele', function () {
    $tela = Livewire::actingAs($this->paeet)->test(DetalheCurso::class, ['curso' => $this->curso])
        ->call('pedirExclusao', 'curso', $this->curso->id);

    expect($tela->get('exclusao')['contagens'])->toBe([
        'turma(s)' => 2,
        'disciplina(s)' => 2,
        'versão(ões) de grade' => 1,
        'aluno(s)' => 2,
        'prova(s)' => 1,
        'solicitação(ões) de questões' => 1,
        'questão(ões)' => 2,
        'aula(s) do quadro de horários' => 1,
    ])->and($tela->get('exclusao')['etapas'])->toBe(2);

    $tela->call('confirmarExclusao')->call('confirmarExclusao')->assertRedirect(route('cursos.index'));

    expect(Curso::query()->whereKey($this->curso->id)->exists())->toBeFalse()
        ->and(Turma::query()->where('curso_id', $this->curso->id)->count())->toBe(0)
        ->and(Disciplina::query()->where('curso_id', $this->curso->id)->count())->toBe(0);

    app(RestaurarExclusaoAction::class)->executar(Exclusao::query()->sole(), $this->admin);

    expect(Turma::query()->where('curso_id', $this->curso->id)->count())->toBe(2)
        ->and(Disciplina::query()->where('curso_id', $this->curso->id)->count())->toBe(2)
        ->and(QuadroHorario::query()->whereKey($this->aula->id)->exists())->toBeTrue();
});

// --- Acesso -----------------------------------------------------------------

it('não deixa o professor nem o PAEET de outro Eixo excluir', function () {
    $deFora = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect($this->professor->can('delete', $this->turma))->toBeFalse()
        ->and($this->professor->can('delete', $this->logica))->toBeFalse();

    // O PAEET de outro Eixo abre a lista dele, mas o id desta turma devolve 403.
    ($this->listaDeTurmas)($deFora)->call('pedirExclusao', 'turma', $this->turma->id)->assertForbidden();

    expect(fn () => app(ExcluirRegistroAction::class)->executar($this->turma, $deFora))
        ->toThrow(AuthorizationException::class);

    expect(Turma::query()->whereKey($this->turma->id)->exists())->toBeTrue();
});

it('mostra o botão de excluir só a quem pode', function () {
    $this->actingAs($this->paeet)->get(route('turmas.index'))->assertSeeHtml("pedirExclusao('turma', {$this->turma->id})");
    $this->actingAs($this->professor)->get(route('turmas.show', $this->turma))->assertDontSeeHtml('pedirExclusao(');
});

it('deixa a Lixeira só com o PAEET Admin, e só com o que é dos Eixos dele', function () {
    $this->actingAs($this->paeet)->get(route('lixeira.index'))->assertForbidden();

    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);
    $outroAdmin = paeetAdmin($outroEixo);
    $lote = app(ExcluirRegistroAction::class)->executar($this->turma, $this->paeet);

    Livewire::actingAs($outroAdmin)->test(Lixeira::class)->assertDontSee('Turma 1 A (2026)');

    expect(fn () => Livewire::actingAs($outroAdmin)->test(Lixeira::class)->call('restaurar', $lote->id))
        ->toThrow(ModelNotFoundException::class);

    $this->actingAs($this->admin)->get(route('painel'))->assertSee(route('lixeira.index'), false);
    $this->actingAs($this->paeet)->get(route('painel'))->assertDontSee(route('lixeira.index'), false);
});
