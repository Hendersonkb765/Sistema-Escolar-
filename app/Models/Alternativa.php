<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alternativa extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'alternativas';

    protected $fillable = ['questao_id', 'letra', 'texto', 'correta'];

    protected function casts(): array
    {
        return ['correta' => 'boolean'];
    }

    public static function caminhoDoEixo(): string
    {
        return 'questao.solicitacao.curso.eixo';
    }

    /** @return BelongsTo<Questao, $this> */
    public function questao(): BelongsTo
    {
        return $this->belongsTo(Questao::class);
    }
}
