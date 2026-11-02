<?php

namespace App\Models;

use App\Enums\Bimestre;
use App\Support\SemanaLetiva;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * As datas de um bimestre num ano letivo.
 *
 * Não é escopado por Eixo: o calendário é da escola, e vale para todas
 * as turmas. Todos consultam; só o PAEET Admin altera. A turma com
 * calendário excepcional tem as próprias datas em TurmaBimestre.
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
     * Bimestre e semana de uma data no ano letivo indicado, pelo
     * calendário da escola.
     *
     * A semana 1 é a semana, de segunda a domingo, em que o bimestre
     * começa — mesmo que ele comece numa quarta. É como a escola conta, e
     * é como o escopo-sequência numera.
     */
    public static function localizar(string $periodoLetivo, CarbonInterface $data): SemanaLetiva
    {
        return self::naSequencia(
            static::query()->where('periodo_letivo', $periodoLetivo)->orderBy('bimestre')->get(),
            $data,
            semCalendario: "O calendário letivo de {$periodoLetivo} ainda não foi cadastrado.",
            fora: "fica fora dos bimestres cadastrados para {$periodoLetivo} (férias ou recesso).",
        );
    }

    /**
     * Bimestre e semana de uma data para uma turma: pelas datas próprias
     * dela, se tiver, e pelo calendário da escola, se não.
     */
    public static function localizarNaTurma(Turma $turma, CarbonInterface $data): SemanaLetiva
    {
        $proprios = $turma->relationLoaded('bimestresProprios')
            ? $turma->bimestresProprios
            : $turma->bimestresProprios()->get();

        if ($proprios->isEmpty()) {
            return self::localizar($turma->periodo_letivo, $data);
        }

        return self::naSequencia(
            $proprios,
            $data,
            semCalendario: '',
            fora: "fica fora dos bimestres do calendário próprio da turma {$turma->nome} (férias ou recesso).",
        );
    }

    /**
     * @param  Collection<int, BimestreLetivo|TurmaBimestre>  $bimestres
     */
    private static function naSequencia(Collection $bimestres, CarbonInterface $data, string $semCalendario, string $fora): SemanaLetiva
    {
        if ($bimestres->isEmpty()) {
            return SemanaLetiva::naoEncontrada($semCalendario);
        }

        $dia = $data->copy()->startOfDay();

        $bimestre = $bimestres->first(fn ($b) => $dia->betweenIncluded($b->inicio, $b->fim));

        if ($bimestre === null) {
            return SemanaLetiva::naoEncontrada("{$dia->format('d/m/Y')} {$fora}");
        }

        $primeiraSegunda = $bimestre->inicio->copy()->startOfWeek(CarbonInterface::MONDAY);
        $semana = intdiv((int) $primeiraSegunda->diffInDays($dia->copy()->startOfWeek(CarbonInterface::MONDAY)), 7) + 1;

        return SemanaLetiva::em($bimestre->bimestre, $semana);
    }
}
