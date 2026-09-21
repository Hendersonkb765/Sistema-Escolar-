<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Busca, ordenação e paginação compartilhadas pelas listagens.
 * O estado vai para a query string, então um filtro pode ser copiado
 * e compartilhado como link.
 */
trait ComTabela
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'ordem', except: '')]
    public string $ordenarPor = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $direcao = 'asc';

    #[Url(as: 'por', except: 15)]
    public int $porPagina = 15;

    /** @return array<int, string> colunas que aceitam ordenação */
    abstract protected function colunasOrdenaveis(): array;

    protected function colunaPadrao(): string
    {
        return 'id';
    }

    public function ordenar(string $coluna): void
    {
        if (! in_array($coluna, $this->colunasOrdenaveis(), true)) {
            return;
        }

        if ($this->ordenarPor === $coluna) {
            $this->direcao = $this->direcao === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $coluna;
            $this->direcao = 'asc';
        }

        $this->resetPage();
    }

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function updatedPorPagina(): void
    {
        $this->resetPage();
    }

    public function limparFiltros(): void
    {
        $this->reset(['busca', 'ordenarPor', 'direcao']);
        $this->resetPage();
    }

    /** Aplica a ordenação validada contra a lista branca de colunas. */
    protected function aplicarOrdenacao(Builder $consulta): Builder
    {
        $coluna = in_array($this->ordenarPor, $this->colunasOrdenaveis(), true)
            ? $this->ordenarPor
            : $this->colunaPadrao();

        $direcao = $this->direcao === 'desc' ? 'desc' : 'asc';

        return $consulta->orderBy($coluna, $direcao);
    }

    public function setaDaColuna(string $coluna): string
    {
        if ($this->ordenarPor !== $coluna) {
            return '';
        }

        return $this->direcao === 'asc' ? '↑' : '↓';
    }
}
