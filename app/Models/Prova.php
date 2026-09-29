<?php

namespace App\Models;

use App\Enums\Bimestre;
use App\Enums\StatusProva;
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
        'instituicao',
        'nome_avaliacao',
        'tamanho_instituicao',
        'bimestre',
        'data_aplicacao',
        'versao',
        'status',
        'instrucoes',
        'configuracao',
        'pdf_path',
        'docx_path',
        'gerada_por',
        'gerada_em',
    ];

    /**
     * Instância nova já nasce com os mesmos padrões da tabela: sem
     * isso, um model não persistido devolve `null` onde a view espera
     * um enum.
     */
    protected $attributes = [
        'status' => 'rascunho',
        'bimestre' => 1,
        'versao' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusProva::class,
            'bimestre' => Bimestre::class,
            'data_aplicacao' => 'date',
            'configuracao' => 'array',
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

    /**
     * Questões agrupadas por disciplina, na ordem em que aparecem na
     * prova — é assim que a folha impressa é lida.
     *
     * @return Collection<string, Collection<int, ProvaQuestao>>
     */
    public function questoesPorDisciplina(): Collection
    {
        return $this->questoes()
            // A tela de detalhe mostra de quem veio cada bloco; sem o
            // professor aqui, a view quebra por lazy loading.
            ->with(['disciplina', 'professor'])
            ->get()
            ->groupBy(fn (ProvaQuestao $questao) => $questao->disciplina->nome)
            ->sortBy(fn ($questoes) => $questoes->min('numero'));
    }

    /** Quantas colunas o texto ocupa na folha. */
    public function colunas(): int
    {
        return (int) ($this->configuracao['colunas'] ?? 2);
    }

    public function mostrarPesos(): bool
    {
        return (bool) ($this->configuracao['mostrar_pesos'] ?? false);
    }

    public function totalDeQuestoes(): int
    {
        return $this->questoes()->count();
    }

    public function somaDosPesos(): float
    {
        return (float) $this->questoes()->sum('peso');
    }

    /** Gabarito completo: número da questão => letra correta. */
    public function gabarito(): Collection
    {
        return $this->questoes()
            ->orderBy('numero')
            ->get()
            ->mapWithKeys(fn (ProvaQuestao $questao) => [$questao->numero => $questao->letra_correta]);
    }

    /**
     * O que sai na primeira linha da folha.
     *
     * A prova pode ter escolhido um texto próprio na montagem; sem
     * isso vale o do modelo, e corrigir o modelo corrige a reimpressão
     * de todas as provas que não escolheram.
     */
    public function instituicaoDaFolha(): string
    {
        return $this->instituicao
            ?: ($this->loadMissing('modelo')->modelo->instituicao ?: (string) config('instituicao.nome'));
    }

    /** O corpo do nome da instituição, em pontos. */
    public function tamanhoDaInstituicao(): int
    {
        return $this->loadMissing('modelo')->modelo
            ->layoutDaFolha()
            ->tamanhoDaInstituicao($this->tamanho_instituicao);
    }

    /** O que sai na segunda linha, antes do título da prova. */
    public function nomeDaAvaliacao(): string
    {
        return $this->nome_avaliacao
            ?: ($this->loadMissing('modelo')->modelo->nome_avaliacao ?: 'Avaliação');
    }

    /** Uma prova já gerada tem snapshot imutável. */
    public function foiGerada(): bool
    {
        return $this->gerada_em !== null;
    }

    /** Só se aplica o que já foi gerado e ainda não foi aplicado. */
    public function podeSerAplicada(): bool
    {
        return $this->status === StatusProva::Gerada;
    }

    /** Por que a aplicação não cabe agora — a tela mostra este texto. */
    public function motivoParaNaoAplicar(): ?string
    {
        return match (true) {
            $this->status === StatusProva::Rascunho => 'A prova ainda não foi gerada.',
            $this->status === StatusProva::Aplicada => 'Esta prova já foi marcada como aplicada.',
            $this->status === StatusProva::Encerrada => 'Esta prova já foi encerrada.',
            default => null,
        };
    }
}
