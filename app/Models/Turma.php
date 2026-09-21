<?php

namespace App\Models;

use App\Enums\StatusTurma;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Turma extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'turmas';

    protected $fillable = [
        'curso_id',
        'grade_curricular_id',
        'ano_curso',
        'identificacao',
        'periodo_letivo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusTurma::class,
            'ano_curso' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('turma');
    }

    /** O professor vê as turmas para as quais recebeu solicitações. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereHas(
            'solicitacoes',
            fn (Builder $q) => $q->where('professor_id', $usuario->getKey())
        );
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** Versão da grade congelada nesta turma. */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(GradeCurricular::class, 'grade_curricular_id');
    }

    /** @return HasMany<Aluno, $this> */
    public function alunos(): HasMany
    {
        return $this->hasMany(Aluno::class);
    }

    /** @return HasMany<TurmaHistorico, $this> */
    public function historicos(): HasMany
    {
        return $this->hasMany(TurmaHistorico::class);
    }

    /** @return HasMany<SolicitacaoProva, $this> */
    public function solicitacoes(): HasMany
    {
        return $this->hasMany(SolicitacaoProva::class);
    }

    /** @return HasMany<Prova, $this> */
    public function provas(): HasMany
    {
        return $this->hasMany(Prova::class);
    }

    /**
     * Disciplinas do ano corrente da turma, segundo a grade congelada.
     *
     * @return Collection<int, GradeDisciplina>
     */
    public function disciplinasDoAno(): Collection
    {
        return $this->grade
            ->disciplinas()
            ->with('disciplina')
            ->where('ano_curso', $this->ano_curso)
            ->get();
    }
}
