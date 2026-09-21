<?php

namespace App\Http\Middleware;

use App\Enums\PerfilUsuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filtro grosso por perfil na definição da rota. A autorização fina
 * continua sendo das Policies — este middleware apenas evita que um
 * perfil sequer alcance um módulo inteiro que não lhe pertence.
 */
class GarantirPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $usuario = Auth::user();

        abort_if($usuario === null, 401);

        $permitidos = array_map(
            fn (string $perfil) => PerfilUsuario::from($perfil),
            $perfis
        );

        abort_unless(in_array($usuario->perfil, $permitidos, true), 403);

        return $next($request);
    }
}
