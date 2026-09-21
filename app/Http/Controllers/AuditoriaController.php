<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * Consulta do log de auditoria. A tela definitiva, com filtros por
 * entidade e período, chega no milestone 8.
 */
class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->ehGestao(), 403);

        return view('auditoria.index', [
            'registros' => Activity::query()
                ->with('causer')
                ->latest()
                ->paginate(25),
        ]);
    }
}
