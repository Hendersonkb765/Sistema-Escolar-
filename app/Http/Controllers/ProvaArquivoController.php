<?php

namespace App\Http\Controllers;

use App\Actions\Prova\GerarDocxDaProvaAction;
use App\Actions\Prova\GerarPdfDaProvaAction;
use App\Models\Prova;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads da prova. O arquivo é gerado na hora, a partir do snapshot,
 * para refletir sempre o que está guardado — e não uma versão antiga em
 * disco.
 */
class ProvaArquivoController extends Controller
{
    public function pdf(Request $request, Prova $prova, GerarPdfDaProvaAction $action): Response
    {
        $this->authorize('view', $prova);

        $comGabarito = $this->pediuGabarito($request, $prova);

        return response($action->conteudo($prova, $request->user(), $comGabarito))
            ->header('Content-Type', 'application/pdf')
            ->header(
                'Content-Disposition',
                'attachment; filename="'.$action->nomeDoArquivo($prova, $comGabarito, 'pdf').'"'
            );
    }

    public function docx(Request $request, Prova $prova, GerarDocxDaProvaAction $action): Response
    {
        $this->authorize('view', $prova);

        $comGabarito = $this->pediuGabarito($request, $prova);

        return response($action->conteudo($prova, $request->user(), $comGabarito))
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->header(
                'Content-Disposition',
                'attachment; filename="'
                .app(GerarPdfDaProvaAction::class)->nomeDoArquivo($prova, $comGabarito, 'docx').'"'
            );
    }

    /** Gabarito só sai para quem pode vê-lo, mesmo com ?gabarito=1 na URL. */
    protected function pediuGabarito(Request $request, Prova $prova): bool
    {
        return $request->boolean('gabarito')
            && $request->user()->can('verGabarito', $prova);
    }
}
