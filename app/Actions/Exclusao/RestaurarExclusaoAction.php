<?php

namespace App\Actions\Exclusao;

use App\Exceptions\RegraDeNegocioException;
use App\Models\Contracts\Excluivel;
use App\Models\Exclusao;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Tira um lote da Lixeira: o registro e tudo o que saiu com ele, de volta
 * como estavam.
 *
 * Duas coisas impedem, e cada uma diz o quê: o registro de cima também na
 * Lixeira (a turma não volta para um curso excluído), e um registro novo
 * que ocupou o lugar do antigo — mesmo nome, código ou horário.
 */
class RestaurarExclusaoAction
{
    public function executar(Exclusao $lote, User $autor): Exclusao
    {
        Gate::forUser($autor)->authorize('administrarLixeira');

        if ($lote->restaurado_em !== null) {
            throw RegraDeNegocioException::porque("{$lote->rotulo} já foi restaurado em {$lote->restaurado_em->format('d/m/Y \à\s H:i')}.");
        }

        $raiz = $lote->loadMissing('registro')->registro;
        $pai = $raiz instanceof Excluivel ? $raiz->paiNaRestauracao() : null;

        if ($pai !== null && method_exists($pai, 'trashed') && $pai->trashed()) {
            $rotuloDoPai = $pai instanceof Excluivel ? $pai->rotuloParaExclusao() : class_basename($pai);

            throw RegraDeNegocioException::porque(
                "{$lote->rotulo} pertence a {$rotuloDoPai}, que também está na Lixeira. Restaure-o antes."
            );
        }

        try {
            DB::transaction(function () use ($lote, $autor) {
                foreach ($lote->itens()->orderBy('id')->get() as $item) {
                    $classe = $item->registro_type;

                    if ($item->dados !== null) {
                        (new $classe)->newQuery()->insert($item->dados);

                        continue;
                    }

                    $classe::withTrashed()->find($item->registro_id)?->restore();
                }

                $lote->update(['restaurado_em' => now(), 'restaurado_por' => $autor->getKey()]);

                activity('exclusao')
                    ->causedBy($autor)
                    ->withProperties(['lote' => $lote->getKey()])
                    ->log("Restaurado da Lixeira: {$lote->rotulo}");
            });
        } catch (UniqueConstraintViolationException) {
            throw RegraDeNegocioException::porque(
                "Não deu para restaurar {$lote->rotulo}: depois da exclusão, foi cadastrado outro registro com o mesmo "
                .'nome, código ou horário. Altere ou exclua o atual e tente de novo.'
            );
        }

        return $lote->refresh();
    }
}
