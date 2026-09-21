<?php

namespace App\Models;

use App\Enums\StatusProva;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Prova extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'provas';

    protected $fillable = [
        'turma_id',
        'modelo_prova_id',
        'titulo',
        'data_aplicacao',
        'versao',
        'status',
        'pdf_path',
        'gerada_por',
        'gerada_em',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusProva::class,
            'data_aplicacao' => 'date',
            'gerada_em' => 'datetime',
            'versao' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'turma.curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('prova');
    }

    /** O professor vê as provas que contêm questões suas. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereHas(
            'questoes',
            fn (Builder $q) => $q->where('professor_id', $usuario->getKey())
        );
    }

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    /** @return BelongsTo<ModeloProva, $this> */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloProva::class, 'modelo_prova_id');
    }

    /** @return HasMany<ProvaQuestao, $this> */
    public function questoes(): HasMany
    {
        return $this->hasMany(ProvaQuestao::class)->orderBy('numero');
    }

    /** @return HasMany<Importacao, $this> */
    public function importacoes(): HasMany
    {
        return $this->hasMany(Importacao::class);
    }

    /** @return HasMany<ResultadoAluno, $this> */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoAluno::class);
    }

    /** @return BelongsTo<User, $this> */
    public function geradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerada_por');
    }
}
