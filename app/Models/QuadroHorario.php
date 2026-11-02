<?php

namespace App\Models;

use App\Enums\DiaDaSemana;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Uma aula da semana no quadro de horários de uma turma.
 *
 * As regras de quem pode estar onde — disciplina do período, professor
 * vinculado, sem choque de horário — moram em SalvarHorarioAction.
 */
class QuadroHorario extends Model
{
    use AplicaEscopoDeEixo;
    use LogsActivity;

    protected $table = 'quadro_horarios';

    protected $fillable = [
        'turma_id', 'dia_semana', 'aula_numero', 'hora_inicio', 'hora_fim',
        'disciplina_id', 'professor_id',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => DiaDaSemana::class,
            'aula_numero' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'turma.curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('horario');
    }

    /** O professor vê as aulas que ele mesmo dá. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->where('professor_id', $usuario->getKey());
    }

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    /** @return BelongsTo<User, $this> */
    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    /**
     * HH:MM. O banco guarda segundos ("07:00:00") em uns drivers e não em
     * outros; a tela e as mensagens querem sempre o mesmo formato.
     */
    public function horaInicio(): string
    {
        return substr((string) $this->hora_inicio, 0, 5);
    }

    public function horaFim(): string
    {
        return substr((string) $this->hora_fim, 0, 5);
    }

    /** "07:00–07:50" */
    public function intervalo(): string
    {
        return $this->horaInicio().'–'.$this->horaFim();
    }

    /** A aula está acontecendo neste momento: é o dia dela, e a hora cai entre o início e o fim. */
    public function acontecendoEm(CarbonInterface $momento): bool
    {
        $hora = $momento->format('H:i');

        return $this->dia_semana === DiaDaSemana::daData($momento)
            && $this->horaInicio() <= $hora
            && $hora < $this->horaFim();
    }

    /** "1ª aula de segunda" */
    public function descricaoDaAula(): string
    {
        return "{$this->aula_numero}ª aula de ".mb_strtolower($this->dia_semana->rotulo());
    }
}
