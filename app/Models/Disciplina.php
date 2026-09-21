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

class Disciplina extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'disciplinas';

    protected $fillable = ['eixo_id', 'nome', 'codigo', 'status'];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'ativo'];

    protected function casts(): array
    {
        return ['status' => StatusRegistro::class];
    }

    public static function caminhoDoEixo(): string
    {
        return 'eixo';
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

    /** @return BelongsTo<Eixo, $this> */
    public function eixo(): BelongsTo
    {
        return $this->belongsTo(Eixo::class);
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
}
