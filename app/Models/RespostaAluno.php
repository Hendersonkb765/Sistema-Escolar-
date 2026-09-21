<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespostaAluno extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'respostas_alunos';

    protected $fillable = [
        'resultado_aluno_id',
        'prova_questao_id',
        'alternativa_marcada',
        'acertou',
        'peso',
        'pontuacao',
    ];

    protected function casts(): array
    {
        return [
            'acertou' => 'boolean',
            'peso' => 'decimal:2',
            'pontuacao' => 'decimal:2',
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

    /** @return BelongsTo<ProvaQuestao, $this> */
    public function provaQuestao(): BelongsTo
    {
        return $this->belongsTo(ProvaQuestao::class, 'prova_questao_id');
    }
}
