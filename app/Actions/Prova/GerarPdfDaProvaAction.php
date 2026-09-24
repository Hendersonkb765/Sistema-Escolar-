<?php

namespace App\Actions\Prova;

use App\Models\Prova;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

/**
 * Gera o PDF da prova a partir da mesma folha que a tela mostra.
 *
 * O motor é o mPDF, e não o dompdf, por um motivo só: o dompdf ignora
 * `column-count` em silêncio — a folha até sai, mas em coluna única. O
 * mPDF faz o fluxo em colunas de jornal, que é o que uma prova impressa
 * precisa. As margens e o papel vêm do `LayoutDaFolha`, para o PDF não
 * divergir do Word.
 */
class GerarPdfDaProvaAction
{
    public function __construct(
        protected RenderizarProvaAction $renderizar,
    ) {}

    /** Devolve o PDF pronto para download, sem tocar no disco. */
    public function conteudo(Prova $prova, User $autor, bool $comGabarito = false): string
    {
        Gate::forUser($autor)->authorize('view', $prova);

        $prova->loadMissing('modelo');
        $layout = $prova->modelo->layoutDaFolha();

        $mpdf = new Mpdf([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_top' => $layout->margens['superior'],
            'margin_bottom' => $layout->margens['inferior'],
            'margin_left' => $layout->margens['esquerda'],
            'margin_right' => $layout->margens['direita'],
            // A margem do cabeçalho precisa caber dentro da superior,
            // senão a numeração invade o texto.
            'margin_header' => max(5, $layout->margens['superior'] / 2),
            'tempDir' => $this->pastaTemporaria(),
        ]);

        // Numeração no alto à direita, como pede a NBR 14724.
        $mpdf->SetHTMLHeader(sprintf(
            '<div style="text-align: right; font-size: %dpt; color: #444;">{PAGENO}</div>',
            $layout->tamanhoSecundario(),
        ));

        $mpdf->WriteHTML($this->renderizar->paraPdf($prova, $comGabarito));

        return (string) $mpdf->Output('', 'S');
    }

    /**
     * O mPDF grava fontes e imagens processadas em disco enquanto monta
     * o documento; sem uma pasta própria ele tenta escrever dentro do
     * vendor, que em produção costuma ser somente leitura.
     */
    protected function pastaTemporaria(): string
    {
        $pasta = storage_path('framework/mpdf');

        if (! is_dir($pasta)) {
            mkdir($pasta, 0775, recursive: true);
        }

        return $pasta;
    }

    /** Guarda o PDF e devolve o caminho relativo no disco público. */
    public function guardar(Prova $prova, User $autor, bool $comGabarito = false): string
    {
        $conteudo = $this->conteudo($prova, $autor, $comGabarito);

        $caminho = "provas/{$prova->getKey()}/".$this->nomeDoArquivo($prova, $comGabarito, 'pdf');

        Storage::disk('public')->put($caminho, $conteudo);

        if (! $comGabarito) {
            $prova->update(['pdf_path' => $caminho]);
        }

        activity('prova')
            ->performedOn($prova)
            ->causedBy($autor)
            ->withProperties(['arquivo' => $caminho, 'gabarito' => $comGabarito])
            ->log('PDF da prova gerado');

        return $caminho;
    }

    public function nomeDoArquivo(Prova $prova, bool $comGabarito, string $extensao): string
    {
        $base = Str::slug($prova->titulo.'-'.$prova->turma->nome.'-v'.$prova->versao);

        return $base.($comGabarito ? '-gabarito' : '').'.'.$extensao;
    }
}
