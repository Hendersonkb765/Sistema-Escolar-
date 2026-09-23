<?php

namespace App\Models;

use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Um par disciplina + professor dentro de uma solicitação.
 *
 * A prova é uma só, mas cada disciplina tem seu professor e sua cota de
 * questões. Cada professor responde e entrega apenas a sua parte.
 */
class SolicitacaoParte extends Model
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;

    protected $table = 'solicitacao_partes';

    protected $fillable = [
        'solicitacao_id',
        'disciplina_id',
        'professor_id',
        'ordem',
        'quantidade_questoes',
        'status',
        'enviada_em',
        'enviada_em_atraso',
        'observacoes',
    ];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'aberta', 'enviada_em_atraso' => false];

    protected function casts(): array
    {
        return [
            'status' => StatusSolicitacao::class,
            'ordem' => 'integer',
            'quantidade_questoes' => 'integer',
            'enviada_em' => 'datetime',
            'enviada_em_atraso' => 'boolean',
        ];
    }

    public static function caminhoDoEixo(): string
    {
        return 'solicitacao.curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'enviada_em', 'quantidade_questoes'])
            ->logOnlyDirty()
            ->useLogName('solicitacao');
    }

    /** O professor enxerga apenas as partes endereçadas a ele. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->where('professor_id', $usuario->getKey());
    }

    /** @return BelongsTo<SolicitacaoProva, $this> */
    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoProva::class, 'solicitacao_id');
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

    /** @return HasMany<Questao, $this> */
    public function questoes(): HasMany
    {
        return $this->hasMany(Questao::class, 'solicitacao_parte_id')->orderBy('ordem');
    }

    /**
     * Só o encerramento ou o cancelamento da solicitação fecham o envio —
     * prazo vencido apenas marca o atraso.
     */
    public function aceitaEnvio(): bool
    {
        return $this->loadMissing('solicitacao')->solicitacao->aceitaEnvio()
            && $this->enviada_em === null;
    }

    public function estaAtrasada(): bool
    {
        if ($this->enviada_em !== null) {
            return $this->enviada_em_atraso;
        }

        return $this->aceitaEnvio()
            && $this->loadMissing('solicitacao')->solicitacao->prazo->isPast();
    }

    public function rotuloDePrazo(): ?string
    {
        if ($this->enviada_em !== null) {
            return $this->enviada_em_atraso ? 'Enviada em atraso' : null;
        }

        return $this->estaAtrasada() ? 'Atrasada' : null;
    }

    public function questoesCompletas(): int
    {
        $esperadas = (int) $this->loadMissing('solicitacao')->solicitacao->quantidade_alternativas;

        return $this->questoes()
            ->with('alternativas')
            ->get()
            ->filter(fn (Questao $questao) => $questao->estaCompleta($esperadas))
            ->count();
    }

    public function somaDosPesos(): float
    {
        return (float) $this->questoes()->sum('peso');
    }

    public function questoesDevolvidas(): int
    {
        return $this->questoes()->where('status', StatusQuestao::Rejeitada)->count();
    }

    /** Identificação curta para títulos e mensagens. */
    public function rotulo(): string
    {
        return $this->loadMissing('disciplina')->disciplina->nome;
    }
}
