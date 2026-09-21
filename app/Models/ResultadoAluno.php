<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultadoAluno extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'resultados_alunos';

    protected $fillable = ['prova_id', 'aluno_id', 'importacao_id'];

    public static function caminhoDoEixo(): string
    {
        return 'prova.turma.curso.eixo';
    }

    /** O professor vê somente os resultados das disciplinas que leciona. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereHas(
            'notas',
            fn (Builder $q) => $q->whereIn('disciplina_id', $usuario->disciplinaIds())
        );
    }

    /** @return BelongsTo<Prova, $this> */
    public function prova(): BelongsTo
    {
        return $this->belongsTo(Prova::class);
    }

    /** @return BelongsTo<Aluno, $this> */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    /** @return BelongsTo<Importacao, $this> */
    public function importacao(): BelongsTo
    {
        return $this->belongsTo(Importacao::class);
    }

    /** @return HasMany<RespostaAluno, $this> */
    public function respostas(): HasMany
    {
        return $this->hasMany(RespostaAluno::class, 'resultado_aluno_id');
    }

    /** @return HasMany<NotaDisciplina, $this> */
    public function notas(): HasMany
    {
        return $this->hasMany(NotaDisciplina::class, 'resultado_aluno_id');
    }
}
