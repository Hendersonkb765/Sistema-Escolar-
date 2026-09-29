<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * "Esqueci minha senha" responde a mesma coisa, ache ou não a conta.
 *
 * O Fortify separa os dois desfechos: o sucesso volta com `status` e a
 * falha volta com erro de validação. Mesmo dizendo a mesma frase — e
 * `lang/pt_BR/passwords.php` diz — a página sai diferente: alerta verde
 * de um lado, vermelho do outro, e até destino diferente. Quem quisesse
 * levantar a lista de e-mails da escola só precisava olhar a cor.
 *
 * Aqui a falha devolve exatamente a resposta do sucesso. O que continua
 * distinguível é o e-mail malformado, recusado antes disso pela
 * validação do formulário — e esse erro não diz nada sobre quem existe.
 */
final class RespostaUnicaDoLinkDeSenha implements FailedPasswordResetLinkRequestResponse
{
    public function toResponse($request)
    {
        $frase = trans(Password::RESET_LINK_SENT);

        return $request->wantsJson()
            ? new JsonResponse(['message' => $frase], 200)
            : back()->with('status', $frase);
    }
}
