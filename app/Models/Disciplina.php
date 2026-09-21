<?php

namespace App\Models;

use App\Enums\StatusRegistro;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Disciplina de um curso, cursada em um período determinado.
 *
 * O período vive aqui, no cadastro — é o estado atual e editável. As
 * grades curriculares são fotos desse estado, tiradas ao publicar uma
 * versão, e é nelas que as turmas se apoiam para não terem o percurso
 * reescrito quando o cadastro muda.
 */
class Disciplina extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'disciplinas';

    protected $fillable = ['curso_id', 'nome', 'codigo', 'periodo', 'carga_horaria', 'status'];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'ativo', 'carga_horaria' => 80];

    protected function casts(): array
    {
        return [
            'status' => StatusRegistro::class,
            'periodo' => 'integer',
            'carga_horaria' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('disciplina');
    }

    /** O professor vê as disciplinas em que tem vínculo docente ativo. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereIn('disciplinas.id', $usuario->disciplinaIds());
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** @return HasMany<GradeDisciplina, $this> */
    public function gradeDisciplinas(): HasMany
    {
        return $this->hasMany(GradeDisciplina::class);
    }

    /** Docentes vinculados — professores e PAEETs que também lecionam. */
    public function professores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'professor_disciplina', 'disciplina_id', 'usuario_id')
            ->withPivot(['turma_id', 'ativo'])
            ->withTimestamps();
    }

    /** @return Builder<Disciplina> */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('status', StatusRegistro::Ativo);
    }

    /** @return Builder<Disciplina> */
    public function scopeDoPeriodo(Builder $query, int $periodo): Builder
    {
        return $query->where('periodo', $periodo);
    }
}
