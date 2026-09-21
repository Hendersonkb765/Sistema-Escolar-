<?php

/*
 * Telas renderizadas com dados de verdade.
 *
 * Duas lições que custaram um 500 em produção local:
 *
 * 1. Um cenário vazio não exercita `@foreach`/`@forelse` populado, nem os
 *    acessos a relações dentro deles.
 * 2. O Laravel só marca os models com `preventsLazyLoading` quando a
 *    consulta devolve MAIS DE UM registro (Builder::hydrate). Com uma
 *    linha só, um lazy load passa despercebido no teste e estoura na tela
 *    de quem tem duas.
 *
 * Por isso toda coleção aqui tem no mínimo dois registros.
 */

use App\Actions\Academico\AvancarTurmaAction;
use App\Actions\Academico\MoverAlunoDeTurmaAction;
use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Actions\Academico\RegistrarHistoricoDeTurma;
use App\Enums\EventoHistorico;
use App\Enums\StatusAluno;
use App\Livewire\Turmas\DetalheTurma;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\GradeCurricular;
use App\Models\Turma;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    proibirLazyLoading();

    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->outroEixo = Eixo::factory()->create(['nome' => 'Gestão', 'codigo' => 'ADM']);
    $this->admin = paeetAdmin($this->eixo, $this->outroEixo);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes de Computadores'],
        2 => ['Processos de Desenvolvimento', 'Programação Web'],
        3 => ['Banco de Dados', 'Gestão de Projetos'],
    ], autor: $this->admin);

    $this->curso = $montagem['curso'];
    $this->grade = $montagem['grade'];

    // Segundo curso: as listagens precisam de mais de uma linha.
    $this->segundoCurso = cursoComGrade($this->outroEixo, [1 => ['Contabilidade']], autor: $this->admin)['curso'];

    $this->turma = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 2,
        'nome' => '2 A',
        'periodo_letivo' => '2026',
    ]);

    $this->outraTurma = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 1,
        'nome' => '1 A',
        'periodo_letivo' => '2026',
    ]);

    foreach ([['Marina Alves', '1001'], ['Caio Prado', '1002'], ['Rita Souza', '1003']] as [$nome, $matricula]) {
        Aluno::factory()->naTurma($this->turma)->create(['nome' => $nome, 'matricula' => $matricula]);
    }

    Aluno::factory()->naTurma($this->outraTurma)->create([
        'nome' => 'Ivo Nunes',
        'matricula' => '2001',
        'status' => StatusAluno::Transferido,
    ]);

    // Dois registros de histórico na turma: um da criação, outro do avanço.
    app(RegistrarHistoricoDeTurma::class)->executar(
        turma: $this->turma,
        evento: EventoHistorico::TurmaCriada,
        autor: $this->admin,
    );

    app(AvancarTurmaAction::class)->executar(
        turma: $this->turma,
        autor: $this->admin,
        observacoes: 'Virada de ano',
    );

    $this->turma->refresh();

    // Duas versões extras de grade, para o bloco "outras versões".
    $this->v2 = app(PublicarVersaoDeGradeAction::class)->executar($this->curso, $this->admin);
    $this->v3 = app(PublicarVersaoDeGradeAction::class)->executar($this->curso, $this->admin);
});

it('abre o detalhe da turma com histórico, alunos e disciplinas', function () {
    // Regressão: o histórico exibe a versão da grade congelada à época, e
    // essa relação não vinha carregada — com dois registros, estourava.
    expect($this->turma->historicos()->count())->toBeGreaterThan(1);

    $this->actingAs($this->admin)
        ->get(route('turmas.show', $this->turma))
        ->assertOk()
        ->assertSee('3 A')
        ->assertSee('Marina Alves')
        ->assertSee('Caio Prado')
        ->assertSee('Banco de Dados')
        ->assertSee('Gestão de Projetos')
        ->assertSee('Avanço de ano')
        ->assertSee('Virada de ano')
        ->assertSee('grade v'.$this->grade->versao, escape: false);
});

it('abre o detalhe do aluno com várias movimentações no histórico', function () {
    $aluno = Aluno::query()->where('matricula', '1001')->first();

    $destinoA = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 3, 'nome' => '3 B', 'periodo_letivo' => '2026',
    ]);
    $destinoB = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 3, 'nome' => '3 C', 'periodo_letivo' => '2026',
    ]);

    $mover = app(MoverAlunoDeTurmaAction::class);
    $mover->executar($aluno, $destinoA, $this->admin, 'Primeira troca');
    $mover->executar($aluno->refresh(), $destinoB, $this->admin, 'Segunda troca');

    expect($aluno->historicos()->count())->toBeGreaterThan(1);

    $this->actingAs($this->admin)
        ->get(route('alunos.editar', $aluno))
        ->assertOk()
        ->assertSee('Primeira troca')
        ->assertSee('Segunda troca')
        ->assertSee('3 B')
        ->assertSee('3 C');
});

it('abre o detalhe da grade com disciplinas, turmas e outras versões', function () {
    expect($this->grade->turmas()->count())->toBeGreaterThan(1);

    $this->actingAs($this->admin)
        ->get(route('grades.show', $this->grade))
        ->assertOk()
        ->assertSee('Lógica de Programação')
        ->assertSee('Banco de Dados')
        ->assertSee('3 A')
        ->assertSee('1 A')
        ->assertSee('v'.$this->v2->versao, escape: false)
        ->assertSee('v'.$this->v3->versao, escape: false);
});

it('abre o detalhe do curso com várias grades e turmas', function () {
    expect($this->curso->grades()->count())->toBeGreaterThan(1)
        ->and($this->curso->turmas()->count())->toBeGreaterThan(1);

    $this->actingAs($this->admin)
        ->get(route('cursos.show', $this->curso))
        ->assertOk()
        ->assertSee($this->curso->nome)
        ->assertSee('3 A')
        ->assertSee('1 A');
});

it('abre cada listagem com mais de um registro', function (string $rota, string $model) {
    expect($model::query()->count())->toBeGreaterThan(1);

    $this->actingAs($this->admin)->get(route($rota))->assertOk();
})->with([
    ['cursos.index', Curso::class],
    ['disciplinas.index', Disciplina::class],
    ['grades.index', GradeCurricular::class],
    ['turmas.index', Turma::class],
    ['alunos.index', Aluno::class],
    ['eixos.index', Eixo::class],
]);

it('abre a listagem de usuários com vários perfis', function () {
    professor($this->eixo);
    paeet($this->eixo);

    expect(User::query()->count())->toBeGreaterThan(2);

    $this->actingAs($this->admin)->get(route('usuarios.index'))->assertOk();
});

it('abre a auditoria com vários registros', function () {
    expect(Activity::query()->count())->toBeGreaterThan(1);

    $this->actingAs($this->admin)->get(route('auditoria.index'))->assertOk();
});

it('abre o painel com indicadores preenchidos', function () {
    $this->actingAs($this->admin)->get(route('painel'))->assertOk();
});

it('abre os formulários de edição com dados carregados', function () {
    $aluno = Aluno::query()->where('matricula', '1002')->first();
    $disciplina = Disciplina::query()->first();

    $como = fn (string $rota, $parametro) => $this->actingAs($this->admin)
        ->get(route($rota, $parametro))
        ->assertOk();

    $como('cursos.editar', $this->curso);
    $como('disciplinas.editar', $disciplina);
    $como('turmas.editar', $this->turma);
    $como('alunos.editar', $aluno);
    $como('eixos.editar', $this->eixo);
    $como('usuarios.editar', $this->admin);
});

it('abre os formulários de criação com listas de opções povoadas', function (string $rota) {
    $this->actingAs($this->admin)->get(route($rota))->assertOk();
})->with([
    'cursos.criar',
    'disciplinas.criar',
    'turmas.criar',
    'alunos.criar',
    'eixos.criar',
    'usuarios.criar',
]);

it('abre o painel do professor com mais de um vínculo docente', function () {
    $professor = professor($this->eixo);

    Disciplina::query()->where('eixo_id', $this->eixo->id)->take(2)->get()
        ->each(fn (Disciplina $disciplina) => $professor->vinculosDocentes()->create([
            'disciplina_id' => $disciplina->getKey(),
            'ativo' => true,
        ]));

    $this->actingAs($professor)->get(route('painel'))->assertOk();
});

it('abre o painel de avanço de período com turmas de destino', function () {
    $turma = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 1, 'nome' => '1 B', 'periodo_letivo' => '2027',
    ]);

    Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 2, 'nome' => '2 A', 'periodo_letivo' => '2027',
    ]);
    Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 2, 'nome' => '2 B', 'periodo_letivo' => '2027',
    ]);

    Livewire\Livewire::actingAs($this->admin)
        ->test(DetalheTurma::class, ['turma' => $turma])
        ->call('abrirPainelAvanco')
        ->assertOk()
        ->assertSee('2 A')
        ->assertSee('2 B');
});
