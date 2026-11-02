<?php

namespace App\Models;

use App\Enums\TipoDeEvento;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Um evento ou feriado do calendário da escola.
 *
 * Não é escopado por Eixo: o feriado vale para a escola inteira. Todos
 * consultam; só o PAEET Admin cadastra, como as datas dos bimestres.
 */
class EventoEscolar extends Model
{
    use LogsActivity;

    protected $table = 'eventos_escolares';

    protected $fillable = ['titulo', 'tipo', 'inicio', 'fim', 'descricao'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDeEvento::class,
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('calendario');
    }

    /**
     * Os que tocam o período, ainda que comecem antes ou terminem depois.
     *
     * @param  Builder<EventoEscolar>  $query
     */
    public function scopeEntre(Builder $query, CarbonInterface $de, CarbonInterface $ate): Builder
    {
        return $query->whereDate('inicio', '<=', $ate)->whereDate('fim', '>=', $de);
    }

    /** O feriado ou recesso que cai na data, se houver: dia em que não há aula. */
    public static function semAulaEm(CarbonInterface $data): ?self
    {
        return static::query()
            ->entre($data, $data)
            ->whereIn('tipo', array_map(fn (TipoDeEvento $t) => $t->value, array_filter(TipoDeEvento::cases(), fn (TipoDeEvento $t) => $t->semAula())))
            ->orderBy('inicio')
            ->first();
    }

    /** "12/10/2026" ou "21/12/2026 a 31/12/2026". */
    public function periodo(): string
    {
        return $this->inicio->isSameDay($this->fim)
            ? $this->inicio->format('d/m/Y')
            : "{$this->inicio->format('d/m/Y')} a {$this->fim->format('d/m/Y')}";
    }

    public function jaPassou(): bool
    {
        return $this->fim->isBefore(today());
    }
}
