<?php

namespace App\Models;

use App\Enums\StatusGrade;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GradeCurricular extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'grades_curriculares';

    protected $fillable = [
        'curso_id',
        'versao',
        'ano_vigencia',
        'status',
        'observacoes',
        'criado_por',
        'origem_grade_id',
    ];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'rascunho'];

    protected function casts(): array
    {
        return [
            'status' => StatusGrade::class,
            'versao' => 'integer',
            'ano_vigencia' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('grade');
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** @return BelongsTo<GradeCurricular, $this> */
    public function origem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origem_grade_id');
    }

    /** @return HasMany<GradeDisciplina, $this> */
    public function disciplinas(): HasMany
    {
        return $this->hasMany(GradeDisciplina::class);
    }

    /** @return HasMany<Turma, $this> */
    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    /** Uma grade já congelada em alguma turma nunca pode ser alterada. */
    public function emUso(): bool
    {
        return $this->turmas()->exists();
    }

    /**
     * Grades são fotos: não se editam, publicam-se novas. O método fica
     * para quem perguntar, respondendo sempre que não.
     */
    public function editavel(): bool
    {
        return false;
    }

    /**
     * Disciplinas da foto agrupadas por período.
     *
     * @return Collection<int, Collection<int, GradeDisciplina>>
     */
    public function porPeriodo(): Collection
    {
        return $this->disciplinas()
            ->with('disciplina')
            ->orderBy('periodo')
            ->get()
            ->groupBy('periodo')
            ->sortKeys();
    }
}
