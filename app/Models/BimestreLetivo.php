<?php

namespace App\Models;

use App\Enums\Bimestre;
use App\Support\SemanaLetiva;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * As datas de um bimestre num ano letivo.
 *
 * Não é escopado por Eixo: o calendário é da escola. Todos consultam;
 * só o PAEET Admin altera.
 */
class BimestreLetivo extends Model
{
    use LogsActivity;

    protected $table = 'bimestres_letivos';

    protected $fillable = ['periodo_letivo', 'bimestre', 'inicio', 'fim'];

    protected function casts(): array
    {
        return [
            'bimestre' => Bimestre::class,
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('calendario');
    }

    /**
     * Bimestre e semana de uma data no ano letivo indicado.
     *
     * A semana 1 é a semana, de segunda a domingo, em que o bimestre
     * começa — mesmo que ele comece numa quarta. É como a escola conta, e
     * é como o escopo-sequência numera.
     */
    public static function localizar(string $periodoLetivo, CarbonInterface $data): SemanaLetiva
    {
        $bimestres = static::query()
            ->where('periodo_letivo', $periodoLetivo)
            ->orderBy('bimestre')
            ->get();

        if ($bimestres->isEmpty()) {
            return SemanaLetiva::naoEncontrada(
                "O calendário letivo de {$periodoLetivo} ainda não foi cadastrado."
            );
        }

        $dia = $data->copy()->startOfDay();

        $bimestre = $bimestres->first(
            fn (self $b) => $dia->betweenIncluded($b->inicio, $b->fim)
        );

        if ($bimestre === null) {
            return SemanaLetiva::naoEncontrada(
                "{$dia->format('d/m/Y')} fica fora dos bimestres cadastrados para {$periodoLetivo} (férias ou recesso)."
            );
        }

        $primeiraSegunda = $bimestre->inicio->copy()->startOfWeek(CarbonInterface::MONDAY);
        $semana = intdiv((int) $primeiraSegunda->diffInDays($dia->copy()->startOfWeek(CarbonInterface::MONDAY)), 7) + 1;

        return SemanaLetiva::em($bimestre->bimestre, $semana);
    }
}
