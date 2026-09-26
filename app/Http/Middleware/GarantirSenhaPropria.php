<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * No primeiro acesso, o dono da conta escolhe a própria senha.
 *
 * Quem cria a conta define uma senha provisória e a entrega por algum
 * canal — conversa, mensagem, papel. Essa senha serve para entrar uma
 * vez; daí em diante vale a que o professor ou o PAEET escolher, e que
 * ninguém mais conheceu.
 *
 * A barreira é a cada request, e não só no login: sem isso bastaria
 * digitar qualquer outro endereço para seguir usando a senha de quem
 * criou a conta.
 */
class GarantirSenhaPropria
{
    /**
     * As rotas que continuam abertas: a própria troca e a saída. Quem
     * não quiser trocar agora pode sair — o que não pode é usar o
     * sistema com a senha de outra pessoa.
     */
    protected const LIBERADAS = ['primeira-senha', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario === null || ! $usuario->precisaDefinirSenha()) {
            return $next($request);
        }

        if ($request->routeIs(...self::LIBERADAS)) {
            return $next($request);
        }

        return redirect()->route('primeira-senha');
    }
}
