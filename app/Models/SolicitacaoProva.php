<?php

namespace App\Models;

use App\Enums\Bimestre;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Models\Concerns\AplicaEscopoDeEixo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Pedido de questões para montar uma prova.
 *
 * A prova reúne várias disciplinas, cada uma com seu professor: esses
 * pares são as partes (`solicitacao_partes`). O prazo é da prova inteira;
 * o envio é de cada parte, feito por seu professor.
 */
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
        'criado_por',
        'titulo',
        'bimestre',
        'quantidade_alternativas',
        'prazo',
        'encerrada_em',
        'cancelada_em',
        'status',
        'observacoes',
    ];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    /**
     * Instância nova já nasce com os mesmos padrões da tabela: sem
     * isso, um model não persistido devolve `null` onde a view espera
     * um enum.
     */
    protected $attributes = [
        'status' => 'aberta',
        'bimestre' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusSolicitacao::class,
            'bimestre' => Bimestre::class,
            'prazo' => 'datetime',
            'encerrada_em' => 'datetime',
            'cancelada_em' => 'datetime',
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

    /** O professor enxerga as solicitações em que tem alguma parte. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereHas(
            'partes',
            fn (Builder $q) => $q->where('professor_id', $usuario->getKey())
        );
    }

    // ------------------------------------------------------------------
    // Relações
    // ------------------------------------------------------------------

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

    /** @return BelongsTo<User, $this> */
    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    /** Pares disciplina + professor que compõem a prova. */
    public function partes(): HasMany
    {
        return $this->hasMany(SolicitacaoParte::class, 'solicitacao_id')->orderBy('ordem');
    }

    /** @return HasMany<Questao, $this> */
    public function questoes(): HasMany
    {
        return $this->hasMany(Questao::class, 'solicitacao_id');
    }

    // ------------------------------------------------------------------
    // Estado
    // ------------------------------------------------------------------

    /*
    |--------------------------------------------------------------------------
    | Escopos de prazo e envio
    |--------------------------------------------------------------------------
    |
    | O envio é de cada PARTE, não da solicitação: `enviada_em` mora em
    | `solicitacao_partes`. Consultar `enviada_em` direto aqui não
    | reclama no SQLite — um identificador que não resolve para coluna
    | vira texto literal, e `'enviada_em' IS NULL` é apenas falso. O
    | contador zerava em silêncio; no MySQL seria "Unknown column".
    |
    | Por isso os dois recortes moram aqui, e não copiados na listagem e
    | no painel: é o painel apontando para a tela com o mesmo número.
    */

    /**
     * Com alguma parte ainda não enviada.
     *
     * Dado um professor, só a parte dele conta: a solicitação do colega
     * em atraso não é pendência de quem já entregou.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeComPartePendente(Builder $query, ?User $professor = null): Builder
    {
        return $query->whereHas('partes', fn (Builder $parte) => $parte
            ->whereNull('enviada_em')
            ->when($professor !== null, fn (Builder $q) => $q->where('professor_id', $professor->getKey())));
    }

    /**
     * Pendente com prazo vencido, ou entregue depois do combinado.
     *
     * Atraso é estado de prazo, não de fluxo — e nunca bloqueia o envio.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAtrasadas(Builder $query, ?User $professor = null): Builder
    {
        return $query->whereHas('partes', fn (Builder $parte) => $parte
            ->when($professor !== null, fn (Builder $q) => $q->where('professor_id', $professor->getKey()))
            ->where(fn (Builder $sub) => $sub
                ->where('enviada_em_atraso', true)
                ->orWhere(fn (Builder $pendente) => $pendente
                    ->whereNull('enviada_em')
                    ->whereHas('solicitacao', fn (Builder $s) => $s->where('prazo', '<', now())))));
    }

    /**
     * Com a parte do professor já enviada.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeComParteEnviada(Builder $query, ?User $professor = null): Builder
    {
        return $query->whereHas('partes', fn (Builder $parte) => $parte
            ->whereNotNull('enviada_em')
            ->when($professor !== null, fn (Builder $q) => $q->where('professor_id', $professor->getKey())));
    }

    /**
     * Prazo vencido não bloqueia: só o encerramento ou o cancelamento
     * manual pelo PAEET fecham o envio.
     */
    public function aceitaEnvio(): bool
    {
        return $this->encerrada_em === null && $this->cancelada_em === null;
    }

    /** Pendente com prazo vencido, ou com alguma parte entregue atrasada. */
    public function estaAtrasada(): bool
    {
        if ($this->partes()->where('enviada_em_atraso', true)->exists()) {
            return true;
        }

        return $this->aceitaEnvio()
            && $this->prazo->isPast()
            && $this->partes()->whereNull('enviada_em')->exists();
    }

    public function rotuloDePrazo(): ?string
    {
        if (! $this->estaAtrasada()) {
            return null;
        }

        return $this->partes()->whereNull('enviada_em')->exists()
            ? 'Atrasada'
            : 'Entregue em atraso';
    }

    public function totalDeQuestoes(): int
    {
        return (int) $this->partes()->sum('quantidade_questoes');
    }

    public function questoesCompletas(): int
    {
        return $this->questoes()
            ->with('alternativas')
            ->get()
            ->filter(fn (Questao $questao) => $questao->estaCompleta($this->quantidade_alternativas))
            ->count();
    }

    /** Soma dos pesos que os professores atribuíram às questões. */
    public function somaDosPesos(): float
    {
        return (float) $this->questoes()->sum('peso');
    }

    public function questoesDevolvidas(): int
    {
        return $this->questoes()->where('status', StatusQuestao::Rejeitada)->count();
    }

    /** Disciplinas devolvidas, para a tela dizer onde está o problema. */
    public function disciplinasDevolvidas(): Collection
    {
        return $this->partes()
            ->whereHas('questoes', fn (Builder $q) => $q->where('status', StatusQuestao::Rejeitada))
            ->with('disciplina')
            ->get()
            ->map(fn (SolicitacaoParte $parte) => $parte->disciplina->nome)
            ->values();
    }

    /** Dias restantes até o prazo; negativo quando já venceu. */
    public function diasAteOPrazo(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->prazo->startOfDay(), false);
    }

    /** Nome curto para listagens: o título dado ou a turma e o período. */
    public function identificacao(): string
    {
        if (filled($this->titulo)) {
            return $this->titulo;
        }

        $turma = $this->loadMissing('turma')->turma;

        return "Prova · {$turma->nome} · {$turma->periodo_letivo}";
    }
}
