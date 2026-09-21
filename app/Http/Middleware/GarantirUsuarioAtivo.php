<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segunda barreira do `ativo`: o Fortify barra no login, este middleware
 * barra a cada request. Desativar uma conta derruba a sessão em curso
 * sem esperar o próximo login.
 */
class GarantirUsuarioAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario !== null && ! $usuario->ativo) {
            activity('autenticacao')
                ->performedOn($usuario)
                ->withProperties(['ip' => $request->ip(), 'rota' => $request->path()])
                ->log('Sessão encerrada: conta desativada');

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => __('Esta conta está inativa. Procure a coordenação PAEET.')]);
        }

        return $next($request);
    }
}
