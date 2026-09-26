<?php

namespace App\Http\Controllers;

use App\Actions\Prova\GerarDocxDaProvaAction;
use App\Actions\Prova\GerarGabaritoCsvAction;
use App\Actions\Prova\GerarPdfDaProvaAction;
use App\Actions\Resultado\GerarBoletimAction;
use App\Enums\Bimestre;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Prova;
use App\Models\Turma;
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

    /**
     * Gabarito em CSV, no formato que os leitores de folha de respostas
     * importam. A autorização é a mesma da folha com as respostas
     * marcadas: `verGabarito`, que a própria action aplica.
     */
    public function gabarito(Request $request, Prova $prova, GerarGabaritoCsvAction $action): Response
    {
        return response($action->conteudo($prova, $request->user()))
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header(
                'Content-Disposition',
                'attachment; filename="'.$action->nomeDoArquivo($prova).'"'
            );
    }

    /**
     * Boletins da turma: um PDF com uma página por aluno.
     *
     * A folha de cada um sai sozinha da pilha, para a escola entregar a
     * certa a cada aluno sem mostrar a nota de um para o outro.
     */
    public function boletim(Request $request, GerarBoletimAction $action): Response
    {
        $turma = Turma::query()->findOrFail($request->integer('turma'));
        $bimestre = Bimestre::tryFrom($request->integer('bimestre'));

        try {
            $conteudo = $action->conteudo($turma, $request->user(), $bimestre);
        } catch (RegraDeNegocioException $excecao) {
            return response($excecao->getMessage(), 422)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response($conteudo)
            ->header('Content-Type', 'application/pdf')
            ->header(
                'Content-Disposition',
                'attachment; filename="'.$action->nomeDoArquivo($turma, $bimestre).'"'
            );
    }

    /** Gabarito só sai para quem pode vê-lo, mesmo com ?gabarito=1 na URL. */
    protected function pediuGabarito(Request $request, Prova $prova): bool
    {
        return $request->boolean('gabarito')
            && $request->user()->can('verGabarito', $prova);
    }
}
