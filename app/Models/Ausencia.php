<?php

namespace App\Models;

use App\Enums\DiaDaSemana;
use App\Enums\StatusAusencia;
use Illuminate\Database\Eloquent\Builder;
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
 * um. Quem a vê é o próprio professor e os PAEETs dos Eixos das turmas
 * afetadas — cada um enxergando só as aulas do seu Eixo.
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
        return $this->hasMany(AusenciaAula::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cientePor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ciente_por');
    }

    /**
     * As ausências que tocam os Eixos do usuário: as que têm ao menos
     * uma aula numa turma de lá. É o que a coordenação acompanha.
     *
     * @param  Builder<Ausencia>  $query
     */
    public function scopeDaCoordenacaoDe(Builder $query, User $usuario): Builder
    {
        return $query->whereHas('aulas', fn (Builder $aulas) => $aulas->nosEixos($usuario->eixoIds()));
    }

    /**
     * O que espera a coordenação: pendente e ainda por acontecer. A
     * pendente que já passou não é mais aviso, é histórico.
     *
     * @param  Builder<Ausencia>  $query
     */
    public function scopeAguardandoCiencia(Builder $query): Builder
    {
        return $query->where('status', StatusAusencia::Pendente)->whereDate('data', '>=', today());
    }

    /**
     * Os Eixos das turmas em que o professor daria aula no dia.
     *
     * @return array<int, int>
     */
    public function eixoIds(): array
    {
        return Curso::query()
            ->whereHas('turmas', fn (Builder $q) => $q->whereIn('turmas.id', $this->aulas()->select('turma_id')))
            ->pluck('eixo_id')
            ->unique()
            ->values()
            ->all();
    }

    public function afetaEixosDe(User $usuario): bool
    {
        return $this->aulas()->nosEixos($usuario->eixoIds())->exists();
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
