<?php

namespace App\Livewire\Concerns;

use App\Livewire\Attributes\LembrarFiltro;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionProperty;
use TypeError;

/**
 * Os filtros da tela ficam como a pessoa os deixou até ela fechar o
 * navegador.
 *
 * Guardados num cookie de sessão — sem prazo, que o próprio navegador
 * apaga ao fechar —, um por tela e por usuário: quem entra depois no
 * mesmo navegador não herda os filtros de quem saiu. Nem cache do servidor
 * nem sessão do Laravel servem: um não sabe quando o navegador fecha, e a
 * outra, configurada para isso, deslogaria a pessoa junto.
 *
 * O filtro no endereço vence o guardado: um link para "turmas arquivadas"
 * abre as arquivadas.
 */
trait LembraFiltros
{
    /**
     * Antes do mount do componente, e só no carregamento da página: o
     * mount precisa já achar o filtro, e numa atualização restaurar
     * desfaria o que a pessoa acabou de mudar.
     */
    public function bootLembraFiltros(): void
    {
        if (Livewire::isLivewireRequest() || ! auth()->check()) {
            return;
        }

        $guardados = json_decode((string) request()->cookie($this->cookieDosFiltros()), true);

        if (! is_array($guardados)) {
            return;
        }

        foreach ($this->filtrosLembrados() as $nome => $propriedade) {
            if (! array_key_exists($nome, $guardados) || request()->query->has($this->nomeNoEndereco($propriedade))) {
                continue;
            }

            try {
                $this->{$nome} = $guardados[$nome];
            } catch (TypeError) {
                // Valor de outra versão da tela, de outro tipo: fica o padrão.
            }
        }
    }

    /** A cada resposta, o estado atual dos filtros vai para o cookie. */
    public function dehydrateLembraFiltros(): void
    {
        if (! auth()->check()) {
            return;
        }

        $valores = [];

        foreach (array_keys($this->filtrosLembrados()) as $nome) {
            $valores[$nome] = $this->{$nome};
        }

        // Prazo 0: cookie de sessão, que o navegador apaga ao fechar.
        Cookie::queue($this->cookieDosFiltros(), json_encode($valores), 0);
    }

    /** @return array<string, ReflectionProperty> nome => propriedade */
    protected function filtrosLembrados(): array
    {
        return collect((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC))
            ->filter(fn (ReflectionProperty $p) => $p->getAttributes(LembrarFiltro::class) !== [])
            ->keyBy(fn (ReflectionProperty $p) => $p->getName())
            ->all();
    }

    protected function cookieDosFiltros(): string
    {
        return 'filtros-'.Str::slug(class_basename($this)).'-'.auth()->id();
    }

    /** O nome no endereço: o `as` do #[Url], ou o da propriedade. */
    private function nomeNoEndereco(ReflectionProperty $propriedade): string
    {
        foreach ($propriedade->getAttributes() as $atributo) {
            if (is_a($atributo->getName(), Url::class, true)) {
                return $atributo->getArguments()['as'] ?? $propriedade->getName();
            }
        }

        return $propriedade->getName();
    }
}
