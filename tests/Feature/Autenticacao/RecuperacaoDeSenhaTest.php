<?php

/*
 * O "esqueci minha senha" é a única tela pública que aceita um e-mail e
 * responde de acordo com o que existe no banco. Se a resposta mudar
 * conforme a conta exista, ela vira uma lista de e-mails válidos da
 * escola para quem tiver paciência de tentar.
 *
 * O texto já é o mesmo nos dois casos, de propósito (lang/pt_BR/passwords).
 * Estes testes cuidam de que o resto da página também seja.
 */

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

/**
 * O HTML que o usuário vê depois de pedir o link, já seguido o
 * redirecionamento.
 *
 * O GET antes do POST não é enfeite: é o que um navegador faz, e é o que
 * dá ao `back()` do Fortify um destino. Sem ele os dois casos caem em
 * páginas diferentes por motivo nenhum, e a comparação não diria nada
 * sobre o que o visitante consegue distinguir.
 */
function paginaDoEsqueciSenha(string $email): string
{
    test()->get(route('password.request'))->assertOk();

    $html = test()->followingRedirects()
        ->post(route('password.email'), ['email' => $email])
        ->getContent();

    // O token do formulário muda de resposta para resposta e não diz nada
    // sobre a conta procurada.
    return (string) preg_replace('/name="_token" value="[^"]*"/', 'name="_token"', $html);
}

it('mantém a recuperação de senha ligada', function () {
    expect(Features::enabled(Features::resetPasswords()))->toBeTrue();
});

it('responde a mesma coisa para e-mail que existe e para o que não existe', function () {
    Notification::fake();

    User::factory()->create(['email' => 'existe@escola.test']);

    $conhecido = paginaDoEsqueciSenha('existe@escola.test');
    $desconhecido = paginaDoEsqueciSenha('naoexiste@escola.test');

    expect($conhecido)->toBe($desconhecido);
});

it('responde a mesma coisa para conta inativa', function () {
    Notification::fake();

    User::factory()->create(['email' => 'ativa@escola.test']);
    User::factory()->inativo()->create(['email' => 'inativa@escola.test']);

    expect(paginaDoEsqueciSenha('inativa@escola.test'))
        ->toBe(paginaDoEsqueciSenha('ativa@escola.test'));
});

it('diz a mesma frase nos dois casos', function () {
    expect(__('passwords.sent'))->toBe(__('passwords.user'));
});

it('ainda envia o link para quem tem conta ativa', function () {
    Notification::fake();

    $usuario = User::factory()->create(['email' => 'ativa@escola.test']);

    $this->post(route('password.email'), ['email' => 'ativa@escola.test']);

    Notification::assertSentTo($usuario, ResetPassword::class);
});

it('continua reclamando de e-mail malformado', function () {
    $this->post(route('password.email'), ['email' => 'nao-é-email'])
        ->assertSessionHasErrors('email');
});
