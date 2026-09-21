<?php

/*
 * Matriz de perfis para a criação de contas:
 * PAEET Admin cria PAEET e Professor; PAEET cria apenas Professor;
 * ninguém cria PAEET Admin pela interface.
 */

use App\Enums\PerfilUsuario;
use App\Livewire\Usuarios\FormularioUsuario;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
});

it('deixa o PAEET Admin criar um PAEET', function () {
    $admin = paeetAdmin($this->eixo);

    Livewire::actingAs($admin)
        ->test(FormularioUsuario::class)
        ->set('nome', 'Nova Coordenadora')
        ->set('email', 'nova.paeet@exemplo.test')
        ->set('perfil', PerfilUsuario::Paeet->value)
        ->set('eixosSelecionados', [$this->eixo->id])
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = User::query()->where('email', 'nova.paeet@exemplo.test')->first();

    expect($criado)->not->toBeNull()
        ->and($criado->perfil)->toBe(PerfilUsuario::Paeet)
        ->and($criado->criado_por)->toBe($admin->id)
        ->and($criado->eixos()->pluck('eixos.id')->all())->toBe([$this->eixo->id]);
});

it('impede um PAEET de criar outro PAEET', function () {
    Livewire::actingAs(paeet($this->eixo))
        ->test(FormularioUsuario::class)
        ->set('nome', 'PAEET Clandestino')
        ->set('email', 'clandestino@exemplo.test')
        ->set('perfil', PerfilUsuario::Paeet->value)
        ->call('salvar')
        ->assertForbidden();

    expect(User::query()->where('email', 'clandestino@exemplo.test')->exists())->toBeFalse();
});

it('deixa um PAEET criar professor', function () {
    $autor = paeet($this->eixo);

    Livewire::actingAs($autor)
        ->test(FormularioUsuario::class)
        ->set('nome', 'Prof. Novo')
        ->set('email', 'prof.novo@exemplo.test')
        ->set('perfil', PerfilUsuario::Professor->value)
        ->set('eixosSelecionados', [$this->eixo->id])
        ->call('salvar')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'prof.novo@exemplo.test')->first()?->perfil)
        ->toBe(PerfilUsuario::Professor);
});

it('impede qualquer um de criar um PAEET Admin pela interface', function () {
    Livewire::actingAs(paeetAdmin($this->eixo))
        ->test(FormularioUsuario::class)
        ->set('nome', 'Admin Paralelo')
        ->set('email', 'admin.paralelo@exemplo.test')
        ->set('perfil', PerfilUsuario::PaeetAdmin->value)
        ->call('salvar')
        ->assertForbidden();

    expect(User::query()->where('email', 'admin.paralelo@exemplo.test')->exists())->toBeFalse();
});

it('impede o professor de abrir o formulário de usuários', function () {
    Livewire::actingAs(professor($this->eixo))
        ->test(FormularioUsuario::class)
        ->assertForbidden();
});

it('recusa vincular a conta a um eixo fora do escopo de quem cria', function () {
    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);

    Livewire::actingAs(paeet($this->eixo))
        ->test(FormularioUsuario::class)
        ->set('nome', 'Prof. Fora do Escopo')
        ->set('email', 'fora@exemplo.test')
        ->set('perfil', PerfilUsuario::Professor->value)
        ->set('eixosSelecionados', [$outroEixo->id])
        ->call('salvar')
        ->assertHasErrors('eixosSelecionados.0');

    expect(User::query()->where('email', 'fora@exemplo.test')->exists())->toBeFalse();
});

it('cria a conta com senha provisória quando nenhuma é informada', function () {
    Livewire::actingAs(paeetAdmin($this->eixo))
        ->test(FormularioUsuario::class)
        ->set('nome', 'Prof. Senha Automática')
        ->set('email', 'auto@exemplo.test')
        ->set('perfil', PerfilUsuario::Professor->value)
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = User::query()->where('email', 'auto@exemplo.test')->first();

    expect($criado)->not->toBeNull()
        ->and($criado->password)->not->toBeEmpty()
        ->and(Hash::check('', $criado->password))->toBeFalse();
});

it('registra o mesmo usuário como docente sem criar uma segunda conta', function () {
    $admin = paeetAdmin($this->eixo);
    $disciplina = Disciplina::factory()
        ->doCurso(Curso::factory()->noEixo($this->eixo)->create())
        ->create();

    $paeetQueLeciona = paeet($this->eixo);

    Livewire::actingAs($admin)
        ->test(FormularioUsuario::class, ['usuario' => $paeetQueLeciona])
        ->set('disciplinasSelecionadas', [$disciplina->id])
        ->call('salvar')
        ->assertHasNoErrors();

    $paeetQueLeciona->refresh()->esquecerEscopo();

    expect(User::query()->where('email', $paeetQueLeciona->email)->count())->toBe(1)
        ->and($paeetQueLeciona->perfil)->toBe(PerfilUsuario::Paeet)
        ->and($paeetQueLeciona->lecionaDisciplina($disciplina->id))->toBeTrue();
});
