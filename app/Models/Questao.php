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
     * O peso é escolhido pelo professor que escreve a questão: é ele quem
     * sabe quanto ela vale dentro da disciplina.
     */
    protected $fillable = [
        'solicitacao_id',
        'solicitacao_item_id',
        'disciplina_id',
        'professor_id',
        'enunciado',
        'peso',
        'status',
        'versao',
        'enviada_em',
        'analisada_em',
    ];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'rascunho', 'versao' => 1, 'peso' => 1.0];

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

    /** Código, imagens e parágrafos que compõem o enunciado. */
    public function blocos(): HasMany
    {
        return $this->hasMany(QuestaoBloco::class)->orderBy('ordem');
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

    /**
     * O que ainda impede esta questão de ser enviada, em texto que o
     * professor entenda. Lista vazia significa pronta.
     *
     * @return array<int, string>
     */
    public function pendencias(?int $quantidadeEsperada = null): array
    {
        $quantidadeEsperada ??= $this->loadMissing('solicitacao')->solicitacao->quantidade_alternativas;

        $alternativas = $this->relationLoaded('alternativas')
            ? $this->alternativas
            : $this->alternativas()->get();

        $pendencias = [];

        if (blank($this->enunciado)) {
            $pendencias[] = 'escreva o enunciado';
        }

        if ((float) $this->peso <= 0) {
            $pendencias[] = 'informe um peso maior que zero';
        }

        $semTexto = $alternativas->filter(fn (Alternativa $a) => blank($a->texto))->count();

        if ($alternativas->count() < $quantidadeEsperada) {
            $faltando = $quantidadeEsperada - $alternativas->count();
            $pendencias[] = "preencha as {$faltando} alternativa(s) que faltam";
        } elseif ($semTexto > 0) {
            $pendencias[] = $semTexto === 1
                ? 'preencha o texto da alternativa que ficou vazia'
                : "preencha o texto das {$semTexto} alternativas vazias";
        }

        $corretas = $alternativas->where('correta', true)->count();

        if ($corretas === 0) {
            $pendencias[] = 'marque qual alternativa é a correta';
        } elseif ($corretas > 1) {
            $pendencias[] = 'deixe apenas uma alternativa marcada como correta';
        }

        return $pendencias;
    }

    /**
     * Uma questão está completa quando tem enunciado, peso, o número de
     * alternativas que a solicitação pediu e exatamente uma correta.
     */
    public function estaCompleta(?int $quantidadeEsperada = null): bool
    {
        $quantidadeEsperada ??= $this->loadMissing('solicitacao')->solicitacao->quantidade_alternativas;

        $alternativas = $this->relationLoaded('alternativas')
            ? $this->alternativas
            : $this->alternativas()->get();

        return (float) $this->peso > 0
            && filled($this->enunciado)
            && $alternativas->count() === (int) $quantidadeEsperada
            && $alternativas->every(fn (Alternativa $a) => filled($a->texto))
            && $alternativas->where('correta', true)->count() === 1;
    }

    /** @return Builder<Questao> */
    public function scopeAprovadas(Builder $query): Builder
    {
        return $query->where('status', StatusQuestao::Aprovada);
    }
}
