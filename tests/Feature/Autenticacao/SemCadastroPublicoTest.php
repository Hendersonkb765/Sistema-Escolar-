<?php

/*
 * Critério de aceite 1 — parte A:
 * não existe rota, tela ou fluxo de cadastro público.
 */

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

it('não registra nenhuma rota de cadastro público', function () {
    $nomes = collect(Route::getRoutes())->map->getName()->filter()->values();

    expect($nomes)->not->toContain('register')
        ->and($nomes)->not->toContain('register.store')
        ->and($nomes->filter(fn (string $nome) => str_contains($nome, 'register')))->toBeEmpty();
});

it('responde 404 nas URLs convencionais de cadastro', function (string $url) {
    $this->get($url)->assertNotFound();
})->with(['/register', '/registrar', '/cadastro', '/sign-up']);

it('recusa POST em /register', function () {
    $this->post('/register', [
        'name' => 'Invasor',
        'email' => 'invasor@exemplo.test',
        'password' => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
    ])->assertNotFound();

    expect(User::query()->where('email', 'invasor@exemplo.test')->exists())->toBeFalse();
});

it('mantém a feature de registro do Fortify desligada', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

it('não expõe uma action de criação de usuários ao Fortify', function () {
    expect(class_exists(CreateNewUser::class))->toBeFalse();
});

it('não oferece link de cadastro na tela de login', function () {
    $resposta = $this->get(route('login'));

    $resposta->assertOk()
        ->assertDontSee('register')
        ->assertDontSee('Criar conta')
        ->assertSee('Não há autocadastro', escape: false);
});
