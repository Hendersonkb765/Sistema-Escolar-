<?php

/*
 * Critério de aceite 3:
 * o professor recebe 403 ao tentar criar usuário, solicitação, prova ou
 * importação — e também ao tocar a estrutura acadêmica.
 */

use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Importacao;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->professor = professor($this->eixo);
    $this->curso = Curso::factory()->noEixo($this->eixo)->create();
});

it('responde 403 ao professor em toda rota de criação restrita à gestão', function (string $rota) {
    $this->actingAs($this->professor)->get(route($rota))->assertForbidden();
})->with([
    'usuarios.criar',
    'solicitacoes.criar',
    'provas.criar',
    'importacoes.criar',
]);

it('responde 403 ao professor na administração de usuários', function () {
    $outro = professor($this->eixo);

    $this->actingAs($this->professor)->get(route('usuarios.index'))->assertForbidden();
    $this->actingAs($this->professor)->get(route('usuarios.editar', $outro))->assertForbidden();
});

it('responde 403 ao professor em toda a estrutura acadêmica', function (string $rota) {
    $this->actingAs($this->professor)->get(route($rota))->assertForbidden();
})->with([
    'eixos.index',
    'eixos.criar',
    'cursos.index',
    'disciplinas.index',
    'grades.index',
    'turmas.index',
    'alunos.index',
    'modelos-prova.index',
    'importacoes.index',
    'auditoria.index',
]);

it('nega ao professor a criação de qualquer entidade de gestão', function () {
    $professor = $this->professor;

    expect($professor->can('create', User::class))->toBeFalse()
        ->and($professor->can('create', SolicitacaoProva::class))->toBeFalse()
        ->and($professor->can('create', Prova::class))->toBeFalse()
        ->and($professor->can('create', Importacao::class))->toBeFalse()
        ->and($professor->can('create', Eixo::class))->toBeFalse()
        ->and($professor->can('create', Curso::class))->toBeFalse()
        ->and($professor->can('create', Disciplina::class))->toBeFalse()
        ->and($professor->can('create', Turma::class))->toBeFalse()
        ->and($professor->can('create', ModeloProva::class))->toBeFalse();
});

it('nega ao professor alterar a estrutura acadêmica do próprio eixo', function () {
    $professor = $this->professor;

    expect($professor->can('update', $this->curso))->toBeFalse()
        ->and($professor->can('delete', $this->curso))->toBeFalse()
        ->and($professor->can('update', $this->eixo))->toBeFalse();
});

it('deixa o professor entrar no painel e na área dele', function () {
    $this->actingAs($this->professor)->get(route('painel'))->assertOk();
    $this->actingAs($this->professor)->get(route('solicitacoes.index'))->assertOk();
    $this->actingAs($this->professor)->get(route('questoes.index'))->assertOk();
    $this->actingAs($this->professor)->get(route('resultados.index'))->assertOk();
});

it('não mostra ao professor os menus de gestão', function () {
    $resposta = $this->actingAs($this->professor)->get(route('painel'));

    $resposta->assertOk()
        ->assertSee('Minhas solicitações')
        ->assertDontSee('Usuários')
        ->assertDontSee('Grades curriculares')
        ->assertDontSee('Importar resultados');
});
