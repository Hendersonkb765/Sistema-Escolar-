<?php

namespace App\Models;

use App\Enums\TipoDeDocumento;
use App\Models\Concerns\AplicaEscopoDeEixo;
use App\Support\CamposDoDocumento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Modelo de documento do aluno.
 *
 * Pertence a um Eixo, como todo o resto. O compartilhamento não fura esse
 * escopo: aceitar cria uma cópia no Eixo de quem aceitou
 * ({@see CompartilhamentoDeModelo}), de modo que `visivelPara` continua
 * sendo só "os meus Eixos" e nenhuma consulta precisa de exceção.
 */
class ModeloDocumento extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'modelos_documento';

    protected $fillable = [
        'eixo_id',
        'nome',
        'descricao',
        'tipo',
        'corpo',
        'por_pagina',
        'ativo',
        'criado_por',
        'copia_de',
    ];

    protected $attributes = [
        'tipo' => 'individual',
        'por_pagina' => 1,
    ];

    /** Uma via ocupa a página inteira, metade dela ou um terço. */
    public const POR_PAGINA = [
        1 => '1 por página',
        2 => '2 por página',
        3 => '3 por página',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDeDocumento::class,
            'por_pagina' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('modelo_documento');
    }

    /** @return BelongsTo<Eixo, $this> */
    public function eixo(): BelongsTo
    {
        return $this->belongsTo(Eixo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /** O modelo de que este é cópia, quando nasceu de um compartilhamento aceito. */
    /** @return BelongsTo<ModeloDocumento, $this> */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copia_de');
    }

    /** @return HasMany<CompartilhamentoDeModelo, $this> */
    public function compartilhamentos(): HasMany
    {
        return $this->hasMany(CompartilhamentoDeModelo::class, 'modelo_documento_id');
    }

    public function ehIndividual(): bool
    {
        return $this->tipo === TipoDeDocumento::Individual;
    }

    /**
     * Quantas vias cabem numa página. O documento coletivo é sempre uma
     * via só: repetir a lista da turma três vezes na mesma folha não
     * significa nada.
     */
    public function viasPorPagina(): int
    {
        return $this->ehIndividual() ? max(1, min(3, (int) $this->por_pagina)) : 1;
    }

    /** @return array<int, string> campos do corpo que este tipo não aceita */
    public function camposInvalidos(): array
    {
        return CamposDoDocumento::validar((string) $this->corpo, $this->tipo);
    }
}
