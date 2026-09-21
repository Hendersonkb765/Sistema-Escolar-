<?php

namespace App\Models;

use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ModeloProva extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'modelos_prova';

    protected $fillable = [
        'eixo_id',
        'nome',
        'nome_avaliacao',
        'cabecalho',
        'logo_path',
        'campos_identificacao',
        'layout',
        'rodape',
        'versao',
        'ativo',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'campos_identificacao' => 'array',
            'layout' => 'array',
            'ativo' => 'boolean',
            'versao' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('modelo_prova');
    }

    /** @return BelongsTo<Eixo, $this> */
    public function eixo(): BelongsTo
    {
        return $this->belongsTo(Eixo::class);
    }

    /** @return HasMany<Prova, $this> */
    public function provas(): HasMany
    {
        return $this->hasMany(Prova::class);
    }
}
