<?php

namespace App\Models;

use App\Enums\StatusSolicitacao;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SolicitacaoProva extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'solicitacoes_prova';

    protected $fillable = [
        'curso_id',
        'turma_id',
        'disciplina_id',
        'professor_id',
        'criado_por',
        'quantidade_questoes',
        'quantidade_alternativas',
        'prazo',
        'enviada_em',
        'enviada_em_atraso',
        'encerrada_em',
        'cancelada_em',
        'status',
        'observacoes',
    ];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'aberta', 'enviada_em_atraso' => false];

    protected function casts(): array
    {
        return [
            'status' => StatusSolicitacao::class,
            'prazo' => 'datetime',
            'enviada_em' => 'datetime',
            'encerrada_em' => 'datetime',
            'cancelada_em' => 'datetime',
            'enviada_em_atraso' => 'boolean',
            'quantidade_questoes' => 'integer',
            'quantidade_alternativas' => 'integer',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('solicitacao');
    }

    /** O professor só enxerga as solicitações endereçadas a ele. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->where('professor_id', $usuario->getKey());
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
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

    /** @return BelongsTo<User, $this> */
    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /** @return HasMany<SolicitacaoItem, $this> */
    public function itens(): HasMany
    {
        return $this->hasMany(SolicitacaoItem::class, 'solicitacao_id')->orderBy('ordem');
    }

    /** @return HasMany<Questao, $this> */
    public function questoes(): HasMany
    {
        return $this->hasMany(Questao::class, 'solicitacao_id');
    }

    /**
     * Prazo vencido não bloqueia o envio — apenas marca a solicitação
     * como atrasada na interface.
     */
    public function estaAtrasada(): bool
    {
        if ($this->enviada_em !== null) {
            return $this->enviada_em_atraso;
        }

        return $this->status->aceitaEnvio() && $this->prazo->isPast();
    }

    /** Só o encerramento ou o cancelamento manual fecham o envio. */
    public function aceitaEnvio(): bool
    {
        return $this->status->aceitaEnvio()
            && $this->encerrada_em === null
            && $this->cancelada_em === null;
    }

    /** Soma dos pesos que o professor atribuiu às questões. */
    public function somaDosPesos(): float
    {
        return (float) $this->questoes()->sum('peso');
    }

    /**
     * Quantas questões já estão preenchidas a ponto de poderem ser
     * enviadas — o que a barra de progresso do professor mostra.
     */
    public function questoesCompletas(): int
    {
        return $this->questoes()
            ->whereNotNull('enunciado')
            ->where('enunciado', '!=', '')
            ->where('peso', '>', 0)
            ->whereHas('alternativas', fn ($q) => $q->where('correta', true))
            ->withCount('alternativas')
            ->get()
            ->filter(fn (Questao $questao) => $questao->alternativas_count === $this->quantidade_alternativas)
            ->count();
    }

    public function totalDeQuestoes(): int
    {
        return (int) $this->quantidade_questoes;
    }

    /** Dias restantes até o prazo; negativo quando já venceu. */
    public function diasAteOPrazo(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->prazo->startOfDay(), false);
    }

    /** Rótulo do estado de prazo, para os badges da interface. */
    public function rotuloDePrazo(): ?string
    {
        if ($this->enviada_em !== null) {
            return $this->enviada_em_atraso ? 'Enviada em atraso' : null;
        }

        if (! $this->estaAtrasada()) {
            return null;
        }

        return 'Atrasada';
    }
}
