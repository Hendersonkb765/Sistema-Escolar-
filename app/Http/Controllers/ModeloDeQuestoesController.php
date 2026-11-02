<?php

namespace App\Http\Controllers;

use App\Models\SolicitacaoParte;
use App\Support\PlanilhaDeQuestoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * O modelo da planilha de questões de uma parte, em XLSX ou CSV.
 *
 * É da parte, e não genérico: o número de questões e de alternativas é
 * o que a solicitação pediu àquela disciplina.
 */
class ModeloDeQuestoesController extends Controller
{
    public function __invoke(Request $request, SolicitacaoParte $parte): StreamedResponse
    {
        Gate::authorize('responder', $parte);

        $parte->loadMissing('solicitacao.turma', 'disciplina');

        $csv = $request->query('formato') === 'csv';
        $planilha = PlanilhaDeQuestoes::modelo($parte);
        $nome = 'questoes-'.Str::slug($parte->disciplina->nome.' '.$parte->solicitacao->turma->nome);

        if ($csv) {
            $escritor = (new Csv($planilha))
                // O Excel brasileiro abre ";" em colunas e reconhece o acento pelo BOM.
                ->setDelimiter(';')
                ->setUseBOM(true)
                ->setSheetIndex(0);

            return response()->streamDownload(fn () => $escritor->save('php://output'), "{$nome}.csv", [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return response()->streamDownload(fn () => (new Xlsx($planilha))->save('php://output'), "{$nome}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
