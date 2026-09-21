<?php

namespace App\Models;

use App\Enums\EventoHistorico;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro append-only do estado de uma turma antes de cada mudança.
 * Nunca recebe update nem delete.
 */
class TurmaHistorico extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;

    protected $table = 'turma_historicos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'turma_id',
        'evento',
        'curso_id',
        'grade_curricular_id',
        'periodo',
        'nome',
        'periodo_letivo',
        'status',
        'metadados',
        'observacoes',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'evento' => EventoHistorico::class,
            'metadados' => 'array',
            'periodo' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'curso.eixo';
    }

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** @return BelongsTo<GradeCurricular, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(GradeCurricular::class, 'grade_curricular_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
