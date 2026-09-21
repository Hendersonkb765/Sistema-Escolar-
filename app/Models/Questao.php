<?php

namespace App\Models;

use App\Enums\StatusQuestao;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Questao extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'questoes';

    /**
     * `peso` fica fora do fillable de propósito: é copiado do item da
     * solicitação e o professor nunca pode alterá-lo.
     */
    protected $fillable = [
        'solicitacao_id',
        'solicitacao_item_id',
        'disciplina_id',
        'professor_id',
        'enunciado',
        'status',
        'versao',
        'enviada_em',
        'analisada_em',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusQuestao::class,
            'peso' => 'decimal:2',
            'versao' => 'integer',
            'enviada_em' => 'datetime',
            'analisada_em' => 'datetime',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'solicitacao.curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['enunciado', 'status', 'versao', 'enviada_em'])
            ->logOnlyDirty()
            ->useLogName('questao');
    }

    /** O professor só enxerga as próprias questões. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->where('professor_id', $usuario->getKey());
    }

    /** @return BelongsTo<SolicitacaoProva, $this> */
    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoProva::class, 'solicitacao_id');
    }

    /** @return BelongsTo<SolicitacaoItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoItem::class, 'solicitacao_item_id');
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

    /** @return HasMany<Alternativa, $this> */
    public function alternativas(): HasMany
    {
        return $this->hasMany(Alternativa::class)->orderBy('letra');
    }

    /** @return HasMany<QuestaoFeedback, $this> */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(QuestaoFeedback::class)->latest('created_at');
    }

    public function alternativaCorreta(): ?Alternativa
    {
        return $this->alternativas->firstWhere('correta', true);
    }

    /** @return Builder<Questao> */
    public function scopeAprovadas(Builder $query): Builder
    {
        return $query->where('status', StatusQuestao::Aprovada);
    }
}
