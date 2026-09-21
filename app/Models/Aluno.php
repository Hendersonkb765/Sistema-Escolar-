<?php

namespace App\Models;

use App\Enums\StatusAluno;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Aluno extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'alunos';

    protected $fillable = ['turma_id', 'nome', 'matricula', 'status'];

    protected function casts(): array
    {
        return ['status' => StatusAluno::class];
    }

    public static function caminhoDoEixo(): string
    {
        return 'turma.curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('aluno');
    }

    /** O professor vê alunos apenas das turmas em que atua. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereHas(
            'turma.solicitacoes',
            fn (Builder $q) => $q->where('professor_id', $usuario->getKey())
        );
    }

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    /** @return HasMany<AlunoHistorico, $this> */
    public function historicos(): HasMany
    {
        return $this->hasMany(AlunoHistorico::class);
    }

    /** @return HasMany<ResultadoAluno, $this> */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoAluno::class);
    }
}
