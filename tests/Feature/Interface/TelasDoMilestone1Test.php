<?php

/*
 * Fumaça das telas entregues no milestone 1: nenhuma rota autenticada
 * pode quebrar por erro de view, componente ou navegação.
 */

use App\Livewire\Eixos\FormularioEixo;
use App\Livewire\Usuarios\ListaUsuarios;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Eixo;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);
    $this->admin->update(['nome' => 'Coordenação Geral', 'email' => 'coordenacao@exemplo.test']);
    $this->curso = Curso::factory()->noEixo($this->eixo)->create();
});

it('redireciona visitante não autenticado para o login', function (string $rota) {
    $this->get(route($rota))->assertRedirect(route('login'));
})->with(['painel', 'usuarios.index', 'eixos.index', 'cursos.index']);

it('renderiza cada tela do PAEET Admin', function (string $rota) {
    $this->actingAs($this->admin)->get(route($rota))->assertOk();
})->with([
    'painel',
    'eixos.index',
    'eixos.criar',
    'cursos.index',
    'disciplinas.index',
    'grades.index',
    'turmas.index',
    'alunos.index',
    'solicitacoes.index',
    'solicitacoes.criar',
    'questoes.index',
    'provas.index',
    'provas.criar',
    'modelos-prova.index',
    'importacoes.index',
    'importacoes.criar',
    'resultados.index',
    'usuarios.index',
    'usuarios.criar',
    'auditoria.index',
]);

it('renderiza as telas com parâmetro de rota', function () {
    $this->actingAs($this->admin)->get(route('cursos.show', $this->curso))->assertOk();
    $this->actingAs($this->admin)->get(route('cursos.editar', $this->curso))->assertOk();
    $this->actingAs($this->admin)->get(route('eixos.editar', $this->eixo))->assertOk();
    $this->actingAs($this->admin)->get(route('usuarios.editar', $this->admin))->assertOk();
});

it('renderiza as telas do milestone 2', function () {
    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica'], 2 => ['Banco de Dados']]);
    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create();
    $aluno = Aluno::factory()->naTurma($turma)->create();
    $disciplina = $montagem['disciplinas']->first();

    $como = fn (string $rota, $parametro = null) => $this->actingAs($this->admin)
        ->get($parametro === null ? route($rota) : route($rota, $parametro))
        ->assertOk();

    $como('cursos.criar');
    $como('disciplinas.criar');
    $como('disciplinas.editar', $disciplina);
    $como('grades.criar');
    $como('grades.show', $montagem['grade']);
    $como('turmas.criar');
    $como('turmas.show', $turma);
    $como('turmas.editar', $turma);
    $como('alunos.criar');
    $como('alunos.editar', $aluno);
});

it('filtra a listagem de usuários por busca, perfil e situação', function () {
    // Nome e e-mail fixos: a busca cobre as duas colunas, e um e-mail
    // aleatório contendo o termo tornaria o teste intermitente.
    professor($this->eixo)->update([
        'nome' => 'Ana Ferreira',
        'email' => 'ana.ferreira@exemplo.test',
    ]);

    professor($this->eixo)->update([
        'nome' => 'Bruno Cardoso',
        'email' => 'bruno.cardoso@exemplo.test',
        'ativo' => false,
    ]);

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->set('busca', 'Ana')
        ->assertSee('Ana Ferreira')
        ->assertDontSee('Bruno Cardoso')
        ->set('busca', '')
        ->set('filtroSituacao', 'inativos')
        ->assertSee('Bruno Cardoso')
        ->assertDontSee('Ana Ferreira');
});

it('desativa e reativa uma conta pela listagem', function () {
    $alvo = professor($this->eixo);

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('alternarAtivacao', $alvo->id);

    expect($alvo->refresh()->ativo)->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('alternarAtivacao', $alvo->id);

    expect($alvo->refresh()->ativo)->toBeTrue();
});

it('não deixa o usuário desativar a própria conta', function () {
    Livewire::actingAs($this->admin)
        ->test(ListaUsuarios::class)
        ->call('alternarAtivacao', $this->admin->id)
        ->assertForbidden();

    expect($this->admin->refresh()->ativo)->toBeTrue();
});

it('vincula ao criador o eixo recém-criado', function () {
    $admin = paeetAdmin();

    Livewire::actingAs($admin)
        ->test(FormularioEixo::class)
        ->set('nome', 'Ambiente e Saúde')
        ->set('codigo', 'AMB')
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = Eixo::query()->where('codigo', 'AMB')->first();

    expect($criado)->not->toBeNull()
        ->and($admin->refresh()->esquecerEscopo()->temAcessoAoEixo($criado->id))->toBeTrue();
});

it('recusa código de eixo duplicado', function () {
    Livewire::actingAs($this->admin)
        ->test(FormularioEixo::class)
        ->set('nome', 'Duplicado')
        ->set('codigo', 'TEC')
        ->call('salvar')
        ->assertHasErrors(['codigo' => 'unique']);
});
