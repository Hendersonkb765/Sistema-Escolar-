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
        'por_pagina' => self::AUTOMATICO,
    ];

    /** Quantas couberem, sem cortar nenhuma ao meio. */
    public const AUTOMATICO = 0;

    public const POR_PAGINA = [
        self::AUTOMATICO => 'Quantas couberem na folha',
        1 => 'Uma por folha',
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
     * As vias se empilham até encher a folha?
     *
     * O padrão é sim, e quem decide onde cortar é o próprio gerador: no
     * PDF, `page-break-inside: avoid`; no Word, `keepLines`/`keepNext`.
     * Contar vias por folha à mão errava sempre para menos — num
     * documento curto cabem seis onde o teto de três mandava usar duas
     * folhas.
     *
     * O documento coletivo é sempre uma via só: repetir a lista da turma
     * na mesma folha não significa nada.
     */
    public function empilhaNaFolha(): bool
    {
        return $this->ehIndividual() && (int) $this->por_pagina === self::AUTOMATICO;
    }

    /** @return array<int, string> campos do corpo que este tipo não aceita */
    public function camposInvalidos(): array
    {
        return CamposDoDocumento::validar((string) $this->corpo, $this->tipo);
    }
}
