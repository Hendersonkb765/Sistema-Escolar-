<?php

namespace App\Models;

use App\Enums\NormaDaFolha;
use App\Enums\OrigemDaLogo;
use App\Models\Concerns\AplicaEscopoDeEixo;
use App\Support\LayoutDaFolha;
use App\Support\LogoDaFolha;
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
        'instituicao',
        'cabecalho',
        'instrucoes',
        'logo_esquerda_path',
        'logo_direita_path',
        'origem_logo_esquerda',
        'origem_logo_direita',
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
            'origem_logo_esquerda' => OrigemDaLogo::class,
            'origem_logo_direita' => OrigemDaLogo::class,
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

    public function layoutDaFolha(): LayoutDaFolha
    {
        return LayoutDaFolha::doModelo($this);
    }

    public function norma(): NormaDaFolha
    {
        return $this->layoutDaFolha()->norma;
    }

    public function logo(string $lado): LogoDaFolha
    {
        return LogoDaFolha::de($this, $lado);
    }

    /** @return BelongsTo<Eixo, $this> */
    public function eixo(): BelongsTo
    {
        return $this->belongsTo(Eixo::class);
    }

    /** As duas logos do cabeçalho: uma em cada extremo. */
    public const LADOS_DA_LOGO = [
        'esquerda' => 'Logo à esquerda',
        'direita' => 'Logo à direita',
    ];

    protected $attributes = [
        'origem_logo_esquerda' => 'padrao',
        'origem_logo_direita' => 'padrao',
    ];

    /** Campos que a folha imprime no quadro de identificação do aluno. */
    public const CAMPOS_DE_IDENTIFICACAO = [
        'aluno' => 'Nome do aluno',
        'ra' => 'RA',
        'turma' => 'Turma',
        'curso' => 'Curso',
        'data' => 'Data',
        'nota' => 'Nota',
        'assinatura' => 'Assinatura do professor',
    ];

    /** @return HasMany<Prova, $this> */
    public function provas(): HasMany
    {
        return $this->hasMany(Prova::class);
    }
}
