<?php

/*
 * No primeiro acesso, o dono da conta escolhe a própria senha.
 *
 * Quem cria a conta define uma provisória e a entrega por algum canal —
 * conversa, mensagem, papel. Essa senha serve para entrar uma vez; daí
 * em diante vale a que o professor ou o PAEET escolher, e que mais
 * ninguém conheceu.
 *
 * Isto **não é autocadastro**: a conta já existe, criada pela
 * coordenação. O que muda é de quem é a senha.
 */

use App\Actions\Fortify\UpdateUserPassword;
use App\Enums\PerfilUsuario;
use App\Livewire\Autenticacao\PrimeiraSenha;
use App\Livewire\Usuarios\FormularioUsuario;
use App\Models\Eixo;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);

    $this->novato = User::factory()->semSenhaPropria()->create([
        'nome' => 'Renato Lima',
        'email' => 'renato@exemplo.test',
        'perfil' => PerfilUsuario::Professor,
        'password' => Hash::make('provisoria-123'),
    ]);
    $this->novato->eixos()->sync([$this->eixo->id]);
});

it('sabe quando a senha ainda é de outra pessoa', function () {
    expect($this->novato->precisaDefinirSenha())->toBeTrue()
        ->and(User::factory()->create()->precisaDefinirSenha())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| A barreira
|--------------------------------------------------------------------------
*/

it('leva para a escolha da senha em qualquer tela', function (string $rota) {
    $this->actingAs($this->novato)
        ->get(route($rota))
        ->assertRedirect(route('primeira-senha'));
})->with(['painel', 'solicitacoes.index', 'questoes.index', 'provas.index']);

it('barra a cada request, e não só no login', function () {
    // Bastaria digitar outro endereço para seguir com a senha alheia.
    $this->actingAs($this->novato)->get(route('primeira-senha'))->assertOk();
    $this->actingAs($this->novato)->get(route('painel'))->assertRedirect(route('primeira-senha'));
});

it('deixa sair sem definir a senha', function () {
    // Quem não quiser trocar agora pode sair; o que não pode é usar o
    // sistema com a senha de quem criou a conta.
    $this->actingAs($this->novato)->post(route('logout'))->assertRedirect();

    expect(Auth::check())->toBeFalse();
});

it('não atrapalha quem já escolheu a sua', function () {
    $veterano = paeet($this->eixo);

    $this->actingAs($veterano)->get(route('painel'))->assertOk();

    Livewire::actingAs($veterano)
        ->test(PrimeiraSenha::class)
        ->assertRedirect(route('painel'));
});

/*
|--------------------------------------------------------------------------
| A troca
|--------------------------------------------------------------------------
*/

it('grava a senha escolhida e libera o sistema', function () {
    Livewire::actingAs($this->novato)
        ->test(PrimeiraSenha::class)
        ->set('senha', 'a-minha-senha-forte')
        ->set('senha_confirmation', 'a-minha-senha-forte')
        ->call('definir')
        ->assertHasNoErrors()
        ->assertRedirect(route('painel'));

    $this->novato->refresh();

    expect($this->novato->precisaDefinirSenha())->toBeFalse()
        ->and($this->novato->senha_definida_em)->not->toBeNull()
        ->and(Hash::check('a-minha-senha-forte', $this->novato->password))->toBeTrue()
        // A provisória deixa de valer.
        ->and(Hash::check('provisoria-123', $this->novato->password))->toBeFalse();

    $this->actingAs($this->novato)->get(route('painel'))->assertOk();
});

it('recusa repetir a senha provisória', function () {
    // Manter a provisória deixaria a senha nas mãos de quem a entregou.
    Livewire::actingAs($this->novato)
        ->test(PrimeiraSenha::class)
        ->set('senha', 'provisoria-123')
        ->set('senha_confirmation', 'provisoria-123')
        ->call('definir')
        ->assertHasErrors('senha');

    expect($this->novato->refresh()->precisaDefinirSenha())->toBeTrue();
});

it('exige confirmação e tamanho mínimo', function () {
    Livewire::actingAs($this->novato)
        ->test(PrimeiraSenha::class)
        ->set('senha', 'curta')
        ->set('senha_confirmation', 'outra-coisa')
        ->call('definir')
        ->assertHasErrors('senha');

    expect($this->novato->refresh()->precisaDefinirSenha())->toBeTrue();
});

it('registra a troca na auditoria', function () {
    Livewire::actingAs($this->novato)
        ->test(PrimeiraSenha::class)
        ->set('senha', 'a-minha-senha-forte')
        ->set('senha_confirmation', 'a-minha-senha-forte')
        ->call('definir');

    expect(Activity::query()
        ->where('log_name', 'autenticacao')
        ->where('description', 'Senha definida no primeiro acesso')
        ->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| De onde vem a senha provisória
|--------------------------------------------------------------------------
*/

it('nasce provisória quando a coordenação cria a conta', function () {
    $admin = paeetAdmin($this->eixo);

    Livewire::actingAs($admin)
        ->test(FormularioUsuario::class)
        ->set('nome', 'Marta Reis')
        ->set('email', 'marta@exemplo.test')
        ->set('perfil', PerfilUsuario::Professor->value)
        ->set('eixosSelecionados', [$this->eixo->id])
        ->call('salvar')
        ->assertHasNoErrors();

    $criada = User::query()->where('email', 'marta@exemplo.test')->sole();

    expect($criada->precisaDefinirSenha())->toBeTrue();

    $this->actingAs($criada)->get(route('painel'))->assertRedirect(route('primeira-senha'));
});

it('volta a ser provisória quando a coordenação redefine a senha', function () {
    $admin = paeetAdmin($this->eixo);
    $usuario = professor($this->eixo);

    expect($usuario->precisaDefinirSenha())->toBeFalse();

    Livewire::actingAs($admin)
        ->test(FormularioUsuario::class, ['usuario' => $usuario])
        ->set('senha', 'redefinida-2026')
        ->set('senha_confirmation', 'redefinida-2026')
        ->call('salvar')
        ->assertHasNoErrors();

    // Senha definida por outra pessoa é sempre provisória.
    expect($usuario->refresh()->precisaDefinirSenha())->toBeTrue();
});

it('conta como escolhida quando o próprio usuário troca no perfil', function () {
    $usuario = User::factory()->semSenhaPropria()->create(['password' => Hash::make('provisoria-123')]);

    // `current_password:web` confere contra quem está autenticado.
    $this->actingAs($usuario);

    app(UpdateUserPassword::class)->update($usuario, [
        'current_password' => 'provisoria-123',
        'password' => 'escolhida-por-mim-2026',
        'password_confirmation' => 'escolhida-por-mim-2026',
    ]);

    expect($usuario->refresh()->precisaDefinirSenha())->toBeFalse();
});

it('continua sem autocadastro', function () {
    // A tela de primeira senha só existe para quem já entrou.
    $this->get(route('primeira-senha'))->assertRedirect(route('login'));
});
