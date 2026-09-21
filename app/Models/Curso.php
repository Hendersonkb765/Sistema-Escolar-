<?php

namespace App\Models;

use App\Enums\StatusGrade;
use App\Enums\StatusRegistro;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Curso extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'cursos';

    protected $fillable = ['eixo_id', 'nome', 'codigo', 'duracao_anos', 'status'];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'ativo'];

    protected function casts(): array
    {
        return [
            'status' => StatusRegistro::class,
            'duracao_anos' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('curso');
    }

    /** O professor enxerga apenas cursos das turmas em que leciona. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereHas(
            'turmas.solicitacoes',
            fn (Builder $q) => $q->where('professor_id', $usuario->getKey())
        );
    }

    /** @return BelongsTo<Eixo, $this> */
    public function eixo(): BelongsTo
    {
        return $this->belongsTo(Eixo::class);
    }

    /** @return HasMany<GradeCurricular, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(GradeCurricular::class);
    }

    /** @return HasMany<Turma, $this> */
    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    /** Grade vigente mais recente do curso. */
    public function gradeVigente(): ?GradeCurricular
    {
        return $this->grades()
            ->where('status', StatusGrade::Vigente)
            ->orderByDesc('versao')
            ->first();
    }
}
