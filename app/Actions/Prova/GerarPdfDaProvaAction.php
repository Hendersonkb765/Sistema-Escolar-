<?php

namespace App\Actions\Prova;

use App\Models\Prova;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gera o PDF da prova a partir da mesma folha que a tela mostra.
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

        return Pdf::loadHTML($this->renderizar->paraPdf($prova, $comGabarito))
            ->setPaper('a4', 'portrait')
            ->output();
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
