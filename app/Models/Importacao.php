<?php

namespace App\Models;

use App\Enums\StatusImportacao;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Importacao extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;

    protected $table = 'importacoes';

    protected $fillable = [
        'prova_id',
        'usuario_id',
        'arquivo',
        'nome_original',
        'hash',
        'status',
        'relatorio',
        'total_linhas',
        'total_erros',
        'confirmada_em',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusImportacao::class,
            'relatorio' => 'array',
            'total_linhas' => 'integer',
            'total_erros' => 'integer',
            'confirmada_em' => 'datetime',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'prova.turma.curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['prova_id', 'status', 'total_linhas', 'total_erros', 'confirmada_em'])
            ->logOnlyDirty()
            ->useLogName('importacao');
    }

    /** @return BelongsTo<Prova, $this> */
    public function prova(): BelongsTo
    {
        return $this->belongsTo(Prova::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return HasMany<ResultadoAluno, $this> */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoAluno::class);
    }
}
