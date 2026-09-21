<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nota calculada por disciplina — nunca uma nota única da prova inteira. */
class NotaDisciplina extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'notas_disciplina';

    protected $fillable = [
        'resultado_aluno_id',
        'disciplina_id',
        'soma_pesos_acertos',
        'soma_pesos_total',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'soma_pesos_acertos' => 'decimal:2',
            'soma_pesos_total' => 'decimal:2',
            'nota' => 'decimal:2',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'resultado.prova.turma.curso.eixo';
    }

    /** @return BelongsTo<ResultadoAluno, $this> */
    public function resultado(): BelongsTo
    {
        return $this->belongsTo(ResultadoAluno::class, 'resultado_aluno_id');
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }
}
