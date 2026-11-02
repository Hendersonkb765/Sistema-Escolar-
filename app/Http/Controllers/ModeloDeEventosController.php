<?php

namespace App\Http\Controllers;

use App\Models\EventoEscolar;
use App\Support\PlanilhaDeEventos;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * O modelo da planilha de eventos e feriados, para preencher e importar.
 */
class ModeloDeEventosController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        Gate::authorize('gerenciar', EventoEscolar::class);

        $planilha = PlanilhaDeEventos::modelo();

        return response()->streamDownload(
            fn () => (new Xlsx($planilha))->save('php://output'),
            'modelo-eventos-e-feriados.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
