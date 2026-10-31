<?php

/*
 * O formulário aceita criar um PAEET sem Eixo. Sem Eixo em comum, a
 * Policy não achava ninguém que pudesse editá-lo — e editar é o único
 * jeito de vinculá-lo a um Eixo. A conta ficava na lista, ativa e
 * inalcançável.
 *
 * Conta sem Eixo, como a do professor recém-criado, fica com quem a
 * criou e com o PAEET Admin.
 */

use App\Enums\PerfilUsuario;
use App\Livewire\Usuarios\FormularioUsuario;
use App\Livewire\Usuarios\ListaUsuarios;
use App\Models\Eixo;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);
});

it('mostra Editar nos PAEETs sem Eixo que o PAEET Admin criou', function () {
    foreach (['emily.sem.eixo@exemplo.test', 'bruno.sem.eixo@exemplo.test'] as $email) {
        User::factory()->paeet()->create(['email' => $email, 'criado_por' => $this->admin->id]);
    }

    $html = Livewire::actingAs($this->admin)->test(ListaUsuarios::class)->html();

    foreach (User::query()->where('email', 'like', '%.sem.eixo@%')->get() as $paeet) {
        expect($html)->toContain(route('usuarios.editar', $paeet));
    }
});

it('deixa o PAEET Admin vincular a um Eixo o PAEET criado sem nenhum', function () {
    $semEixo = User::factory()->paeet()->create(['criado_por' => $this->admin->id]);

    Livewire::actingAs($this->admin)
        ->test(FormularioUsuario::class, ['usuario' => $semEixo])
        ->set('eixosSelecionados', [$this->eixo->id])
        ->call('salvar')
        ->assertHasNoErrors();

    expect($semEixo->eixos()->pluck('eixos.id')->all())->toBe([$this->eixo->id]);
});

it('não deixa um PAEET editar outro PAEET sem Eixo', function () {
    $semEixo = User::factory()->paeet()->create(['criado_por' => $this->admin->id]);

    $this->actingAs(paeet($this->eixo))
        ->get(route('usuarios.editar', $semEixo))
        ->assertForbidden();
});

it('não deixa um PAEET Admin editar outro PAEET Admin sem Eixo', function () {
    $outroAdmin = User::factory()->paeetAdmin()->create();

    expect($this->admin->can('update', $outroAdmin))->toBeFalse()
        ->and($outroAdmin->perfil)->toBe(PerfilUsuario::PaeetAdmin);
});
