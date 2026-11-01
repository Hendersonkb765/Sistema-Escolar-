<?php

namespace App\Models;

use App\Enums\DiaDaSemana;
use App\Enums\StatusAusencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Um dia em que o professor avisa que não estará, com as aulas que ele
 * daria nesse dia.
 *
 * Não pertence a um Eixo: o professor pode dar aula em turmas de mais de
 * um. Quem a vê é o próprio professor e, a partir da etapa da
 * coordenação, os PAEETs dos Eixos das turmas afetadas.
 */
class Ausencia extends Model
{
    use LogsActivity;

    protected $table = 'ausencias';

    protected $fillable = [
        'professor_id', 'data', 'motivo', 'status', 'ciente_por', 'ciente_em', 'cancelada_em',
    ];

    protected $attributes = ['status' => 'pendente'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'status' => StatusAusencia::class,
            'ciente_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('ausencia');
    }

    /** @return BelongsTo<User, $this> */
    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    /** @return HasMany<AusenciaAula, $this> */
    public function aulas(): HasMany
    {
        return $this->hasMany(AusenciaAula::class)->orderBy('hora_inicio');
    }

    /**
     * Cancelar é do próprio professor, e só até o dia: depois dele a
     * ausência já aconteceu.
     */
    public function podeSerCancelada(): bool
    {
        return $this->status !== StatusAusencia::Cancelada
            && ! $this->data->isBefore(today());
    }

    /** "Terça, 12/05/2026" */
    public function rotuloDoDia(): string
    {
        $dia = DiaDaSemana::daData($this->data);

        return ($dia !== null ? $dia->rotulo().', ' : '').$this->data->format('d/m/Y');
    }
}
