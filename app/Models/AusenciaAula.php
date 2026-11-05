<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma aula do dia da ausência, como estava no quadro quando o professor
 * a informou, com o que ele planejou para ela.
 */
class AusenciaAula extends Model
{
    protected $table = 'ausencia_aulas';

    protected $fillable = [
        'ausencia_id', 'quadro_horario_id', 'turma_id', 'disciplina_id', 'aula_numero',
        'hora_inicio', 'hora_fim', 'escopo_aula_id', 'escopo_titulo', 'conteudo_planejado',
    ];

    protected function casts(): array
    {
        return ['aula_numero' => 'integer'];
    }

    /** @return BelongsTo<Ausencia, $this> */
    public function ausencia(): BelongsTo
    {
        return $this->belongsTo(Ausencia::class);
    }

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        // Registro do que aconteceu: mostra o nome mesmo com ele na Lixeira.
        return $this->belongsTo(Turma::class)->withTrashed();
    }

    /** @return BelongsTo<Disciplina, $this> */
    public function disciplina(): BelongsTo
    {
        // Registro do que aconteceu: mostra o nome mesmo com ele na Lixeira.
        return $this->belongsTo(Disciplina::class)->withTrashed();
    }

    /**
     * As aulas em turmas destes Eixos.
     *
     * @param  Builder<AusenciaAula>  $query
     * @param  array<int, int>  $eixoIds
     */
    public function scopeNosEixos(Builder $query, array $eixoIds): Builder
    {
        return $query->whereHas('turma.curso', fn (Builder $curso) => $curso->whereIn('eixo_id', $eixoIds));
    }

    /** "07:00–07:50" */
    public function intervalo(): string
    {
        return substr((string) $this->hora_inicio, 0, 5).'–'.substr((string) $this->hora_fim, 0, 5);
    }
}
