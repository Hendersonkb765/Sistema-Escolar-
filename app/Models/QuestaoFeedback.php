<?php

namespace App\Models;

use App\Enums\AcaoFeedback;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Histórico append-only de cada análise feita sobre uma questão. */
class QuestaoFeedback extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'questao_feedbacks';

    public const UPDATED_AT = null;

    protected $fillable = [
        'questao_id',
        'analisado_por',
        'acao',
        'comentario',
        'versao_questao',
    ];

    protected function casts(): array
    {
        return [
            'acao' => AcaoFeedback::class,
            'versao_questao' => 'integer',
            'created_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function analisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analisado_por');
    }
}
