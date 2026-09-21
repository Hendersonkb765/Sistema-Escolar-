<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Uma linha por questão pedida, com o peso definido pelo PAEET. */
class SolicitacaoItem extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'solicitacao_itens';

    protected $fillable = ['solicitacao_id', 'ordem', 'peso'];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'peso' => 'decimal:2',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'solicitacao.curso.eixo';
    }

    /** @return BelongsTo<SolicitacaoProva, $this> */
    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoProva::class, 'solicitacao_id');
    }

    /** @return HasOne<Questao, $this> */
    public function questao(): HasOne
    {
        return $this->hasOne(Questao::class, 'solicitacao_item_id');
    }
}
