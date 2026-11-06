<?php

namespace App\Models;

use App\Enums\StatusAluno;
use App\Models\Concerns\AplicaEscopoDeEixo;
use App\Models\Concerns\VaiParaALixeira;
use App\Models\Contracts\Excluivel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Aluno extends Model implements Excluivel
{
    use AplicaEscopoDeEixo;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;
    use VaiParaALixeira;

    protected $table = 'alunos';

    protected $fillable = ['turma_id', 'nome', 'ra', 'codigo_reset', 'status'];

    /** Espelha o default da coluna, para valer já no objeto recém-criado. */
    protected $attributes = ['status' => 'ativo'];

    protected function casts(): array
    {
        return ['status' => StatusAluno::class];
    }

    public static function caminhoDoEixo(): string
    {
        return 'turma.curso.eixo';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('aluno');
    }

    /** O professor vê alunos apenas das turmas em que atua. */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        /*
         * Pelas PARTES da solicitação, e não pela solicitação: quem responde
         * uma disciplina é a parte, e `solicitacoes_prova` não tem
         * `professor_id`. O erro não aparecia — no SQLite um identificador
         * entre aspas que não resolve para coluna nenhuma vira texto
         * literal, e `'professor_id' = 5` é apenas falso. No MySQL seria
         * "Unknown column".
         */
        return $query->whereHas(
            'turma.solicitacoes.partes',
            fn (Builder $q) => $q->where('professor_id', $usuario->getKey())
        );
    }

    /** @return BelongsTo<Turma, $this> */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    /** @return HasMany<AlunoHistorico, $this> */
    public function historicos(): HasMany
    {
        return $this->hasMany(AlunoHistorico::class);
    }

    /** @return HasMany<ResultadoAluno, $this> */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoAluno::class);
    }

    /**
     * Só quem está ativo pode ser retirado da turma. Quem já saiu, saiu;
     * quem concluiu, concluiu.
     */
    public function podeSerDesligado(): bool
    {
        return $this->status === StatusAluno::Ativo;
    }

    /**
     * A página da SED que redefine a senha deste aluno, ou null enquanto
     * o código dele não foi cadastrado.
     *
     * Quem abre precisa estar logado na SED no mesmo navegador: a página
     * é dela, e a sessão também.
     */
    public function linkDeResetDeSenhaNaSed(): ?string
    {
        $codigo = trim((string) $this->codigo_reset);

        if ($codigo === '') {
            return null;
        }

        return 'https://sed.educacao.sp.gov.br/MinhasTurmas/ResetSenhaAluno?'
            .http_build_query(['codigoAluno' => $codigo]);
    }

    // --- Lixeira -----------------------------------------------------------

    public function rotuloParaExclusao(): string
    {
        return "Aluno {$this->nome} (RA {$this->ra})";
    }

    /** Os resultados dele saem junto: senão, as notas seguiriam contando na análise. */
    public function dependentesNaExclusao(): Collection
    {
        return collect($this->resultados()->get());
    }

    public function avisosDaExclusao(): array
    {
        return ['O histórico de turmas dele é mantido.'];
    }

    public function paiNaRestauracao(): ?Model
    {
        return $this->turma()->withTrashed()->first();
    }
}
