<?php

namespace App\Http\Controllers;

use App\Support\SinalDeAvisos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O número que o sino consulta. Sem sessão, sem usuário, sem banco: só a
 * assinatura da URL, conferida com a chave da aplicação, e um arquivo.
 *
 * A assinatura cobre o id: trocar o número na URL a invalida.
 */
class SinalDeAvisosController extends Controller
{
    public function __invoke(Request $request, int $usuario): JsonResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        return response()
            ->json(['versao' => SinalDeAvisos::versao($usuario)])
            ->header('Cache-Control', 'no-store');
    }
}
