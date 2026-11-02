<?php

namespace App\Models;

use App\Enums\PerfilUsuario;
use App\Enums\StatusQuestao;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class User extends Authenticatable
{
    use HasActivity;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nome',
        'email',
        'password',
        'perfil',
        'ativo',
        'senha_definida_em',
        'telefone',
        'criado_por',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** @var array<int, int>|null */
    protected ?array $eixoIdsCache = null;

    /** @var array<int, int>|null */
    protected ?array $disciplinaIdsCache = null;

    protected ?int $questoesDevolvidasCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_login_em' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'perfil' => PerfilUsuario::class,
            'ativo' => 'boolean',
            'senha_definida_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'email', 'perfil', 'ativo', 'telefone'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('usuario');
    }

    /**
     * Alias para integrações que esperam o atributo `name` (Fortify,
     * notificações do Laravel). A coluna canônica é `nome`.
     */
    public function getNameAttribute(): string
    {
        return (string) $this->attributes['nome'];
    }

    // ------------------------------------------------------------------
    // Relações
    // ------------------------------------------------------------------

    /** @return BelongsToMany<Eixo, $this> */
    public function eixos(): BelongsToMany
    {
        return $this->belongsToMany(Eixo::class, 'eixo_usuario', 'usuario_id', 'eixo_id')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Disciplina, $this> */
    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'professor_disciplina', 'usuario_id', 'disciplina_id')
            ->withPivot(['turma_id', 'ativo'])
            ->withTimestamps();
    }

    /** @return HasMany<ProfessorDisciplina, $this> */
    public function vinculosDocentes(): HasMany
    {
        return $this->hasMany(ProfessorDisciplina::class, 'usuario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'criado_por');
    }

    /** @return HasMany<SolicitacaoProva, $this> */
    public function solicitacoesComoProfessor(): HasMany
    {
        return $this->hasMany(SolicitacaoProva::class, 'professor_id');
    }

    /** @return HasMany<Questao, $this> */
    public function questoes(): HasMany
    {
        return $this->hasMany(Questao::class, 'professor_id');
    }

    // ------------------------------------------------------------------
    // Perfil
    // ------------------------------------------------------------------

    public function ehPaeetAdmin(): bool
    {
        return $this->perfil === PerfilUsuario::PaeetAdmin;
    }

    public function ehPaeet(): bool
    {
        return $this->perfil === PerfilUsuario::Paeet;
    }

    public function ehProfessor(): bool
    {
        return $this->perfil === PerfilUsuario::Professor;
    }

    /**
     * A senha em uso ainda é a que outra pessoa definiu.
     *
     * Quem cria a conta entrega uma senha provisória; até o dono
     * escolher a dele, o sistema não abre.
     */
    public function precisaDefinirSenha(): bool
    {
        return $this->senha_definida_em === null;
    }

    /** PAEET Admin e PAEET: equipe de gestão. */
    public function ehGestao(): bool
    {
        return $this->perfil->ehGestao();
    }

    // ------------------------------------------------------------------
    // Escopo
    // ------------------------------------------------------------------

    /** @return array<int, int> */
    public function eixoIds(): array
    {
        return $this->eixoIdsCache ??= $this->eixos()->pluck('eixos.id')->all();
    }

    /**
     * As contas que este usuário enxerga: as que tocam seus Eixos, as que
     * ele mesmo criou e a própria.
     *
     * Morava copiada na tela de usuários e no painel. Duas cópias da
     * mesma regra é uma divergência esperando acontecer — e, quando
     * acontece, o painel mostra um número e a tela para onde ele aponta
     * mostra outro.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisivelPara(Builder $query, ?self $usuario): Builder
    {
        if (! $usuario instanceof self || ! $usuario->ativo || ! $usuario->ehGestao()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $escopo) => $escopo
            ->whereHas('eixos', fn (Builder $q) => $q->whereIn('eixos.id', $usuario->eixoIds()))
            ->orWhere('criado_por', $usuario->getKey())
            ->orWhere('usuarios.id', $usuario->getKey()));
    }

    /**
     * Os avisos do sino, com a hora em microssegundos (ver Aviso). O canal
     * `database` grava por esta relação, e os não lidos partem dela.
     *
     * @return MorphMany<Aviso, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Aviso::class, 'notifiable')->latest();
    }

    /**
     * A coordenação ativa destes Eixos: quem recebe os avisos do que
     * acontece neles.
     *
     * @param  Builder<User>  $query
     * @param  array<int, int>  $eixoIds
     * @return Builder<User>
     */
    public function scopeCoordenacaoDosEixos(Builder $query, array $eixoIds): Builder
    {
        return $query
            ->where('ativo', true)
            ->whereIn('perfil', [PerfilUsuario::PaeetAdmin->value, PerfilUsuario::Paeet->value])
            ->whereHas('eixos', fn (Builder $q) => $q->whereIn('eixos.id', $eixoIds));
    }

    /**
     * O primeiro nome, pulando o tratamento.
     *
     * O cadastro guarda o nome como ele vem da escola, e lá vem
     * "Prof. Renato Lima". Cortar no primeiro espaço dava
     * "Bem-vindo, Prof.".
     */
    public function primeiroNome(): string
    {
        $tratamentos = ['prof', 'profa', 'dr', 'dra', 'sr', 'sra', 'srta'];

        foreach (preg_split('/\s+/', trim($this->nome)) ?: [] as $parte) {
            $limpo = mb_strtolower(rtrim($parte, '.'));

            if ($parte !== '' && ! in_array($limpo, $tratamentos, true)) {
                return $parte;
            }
        }

        return $this->nome;
    }

    public function temAcessoAoEixo(?int $eixoId): bool
    {
        return $eixoId !== null
            && $this->ativo
            && in_array($eixoId, $this->eixoIds(), true);
    }

    /** Disciplinas em que o usuário atua como docente (qualquer perfil). */
    public function disciplinaIds(): array
    {
        return $this->disciplinaIdsCache ??= $this->vinculosDocentes()
            ->where('ativo', true)
            ->pluck('disciplina_id')
            ->unique()
            ->values()
            ->all();
    }

    public function lecionaDisciplina(?int $disciplinaId): bool
    {
        return $disciplinaId !== null && in_array($disciplinaId, $this->disciplinaIds(), true);
    }

    /**
     * Conta inativa não recebe link de redefinição de senha: a rota de
     * recuperação existe, mas nunca é auto-executável para quem não tem
     * acesso válido. A resposta ao visitante permanece genérica.
     */
    public function sendPasswordResetNotification($token): void
    {
        if (! $this->ativo) {
            activity('autenticacao')
                ->performedOn($this)
                ->withProperties(['motivo' => 'conta_inativa'])
                ->log('Pedido de redefinição de senha ignorado');

            return;
        }

        parent::sendPasswordResetNotification($token);
    }

    /**
     * Quantas questões deste professor voltaram para correção. Fica em
     * cache de requisição porque o menu lateral consulta em toda página.
     */
    public function questoesDevolvidas(): int
    {
        return $this->questoesDevolvidasCache ??= Questao::query()
            ->where('professor_id', $this->getKey())
            ->where('status', StatusQuestao::Rejeitada)
            ->count();
    }

    /** Invalida os caches de escopo após alterar vínculos. */
    public function esquecerEscopo(): static
    {
        $this->eixoIdsCache = null;
        $this->disciplinaIdsCache = null;
        $this->questoesDevolvidasCache = null;

        return $this;
    }
}
