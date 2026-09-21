<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Escopo lógico multi-tenant por Eixo.
 *
 * Cada model declara em {@see caminhoDoEixo()} o caminho relacional até o
 * Eixo (ex.: 'curso.eixo'). A partir disso o trait sabe filtrar qualquer
 * consulta pelos Eixos aos quais o usuário autenticado está vinculado.
 *
 * Opção deliberada: NÃO usamos Global Scope. Um global scope faria o Route
 * Model Binding devolver 404 para um recurso de outro Eixo, enquanto o
 * critério de aceite exige 403. A autorização individual fica nas Policies
 * (403) e o filtro de coleção fica em `visivelPara()` (listagens, selects e
 * validação de Form Requests).
 */
trait AplicaEscopoDeEixo
{
    /**
     * Caminho relacional até o Eixo. String vazia quando o próprio model
     * é o Eixo; caso contrário algo como 'eixo', 'curso.eixo' ou
     * 'turma.curso.eixo'.
     */
    abstract public static function caminhoDoEixo(): string;

    /**
     * Filtra a consulta pelos Eixos visíveis ao usuário.
     */
    public function scopeVisivelPara(Builder $query, ?User $usuario): Builder
    {
        if (! $usuario instanceof User || ! $usuario->ativo) {
            return $query->whereRaw('1 = 0');
        }

        if ($usuario->ehGestao()) {
            return $query->where(
                fn (Builder $escopo) => static::restringirAosEixos($escopo, $usuario->eixoIds())
            );
        }

        return $query->where(
            fn (Builder $escopo) => $this->aplicarEscopoDeProfessor($escopo, $usuario)
        );
    }

    /**
     * Escopo dos usuários sem perfil de gestão (professores). Por padrão
     * nada é visível; cada model que o professor pode enxergar sobrescreve
     * este método.
     */
    protected function aplicarEscopoDeProfessor(Builder $query, User $usuario): Builder
    {
        return $query->whereRaw('1 = 0');
    }

    /**
     * Aplica `whereHas` encadeado seguindo o caminho até o Eixo.
     *
     * @param  array<int, int>  $eixoIds
     */
    protected static function restringirAosEixos(Builder $query, array $eixoIds): Builder
    {
        if ($eixoIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $caminho = static::caminhoDoEixo();

        if ($caminho === '') {
            return $query->whereIn($query->getModel()->getQualifiedKeyName(), $eixoIds);
        }

        return $query->whereHas(
            $caminho,
            fn (Builder $relacao) => $relacao->whereIn(
                $relacao->getModel()->getQualifiedKeyName(),
                $eixoIds
            )
        );
    }

    /**
     * Id do Eixo a que este registro pertence, resolvido pelo caminho
     * declarado. Usado pelas Policies para decidir entre permitir e 403.
     */
    public function eixoId(): ?int
    {
        $caminho = static::caminhoDoEixo();

        if ($caminho === '') {
            return $this->getKey();
        }

        $atual = $this;

        foreach (explode('.', $caminho) as $relacao) {
            $atual = $atual?->{$relacao};

            if ($atual === null) {
                return null;
            }
        }

        return $atual->getKey();
    }

    /**
     * Verificação individual usada pelas Policies.
     */
    public function dentroDoEscopoDe(?User $usuario): bool
    {
        if (! $usuario instanceof User || ! $usuario->ativo) {
            return false;
        }

        if (! $usuario->ehGestao()) {
            return static::query()
                ->whereKey($this->getKey())
                ->visivelPara($usuario)
                ->exists();
        }

        $eixoId = $this->eixoId();

        return $eixoId !== null && $usuario->temAcessoAoEixo($eixoId);
    }
}
