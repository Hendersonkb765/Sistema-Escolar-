<?php

namespace App\Support;

use App\Enums\NormaDaFolha;
use App\Models\ModeloProva;

/**
 * O layout já resolvido da folha: o que a montagem do HTML e do Word
 * perguntam em vez de vasculhar o JSON do modelo.
 *
 * Sob a ABNT os valores tipográficos são da norma e não do modelo — por
 * isso eles são impostos aqui, num lugar só, e não repetidos em cada
 * view.
 */
final class LayoutDaFolha
{
    /** Fontes que a ABNT admite. A norma pede Arial ou Times New Roman. */
    public const FONTES = [
        'sans' => 'Arial',
        'serif' => 'Times New Roman',
    ];

    /**
     * @param  'sans'|'serif'  $fonte
     * @param  array{superior: float, inferior: float, esquerda: float, direita: float}  $margens  em milímetros
     */
    private function __construct(
        public readonly NormaDaFolha $norma,
        public readonly string $fonte,
        public readonly int $tamanho,
        public readonly float $espacamento,
        public readonly array $margens,
        public readonly ?int $tamanhoDaInstituicaoNoModelo = null,
    ) {}

    public static function doModelo(?ModeloProva $modelo): self
    {
        return self::de(is_array($modelo?->layout) ? $modelo->layout : []);
    }

    /** @param  array<string, mixed>  $layout */
    public static function de(array $layout): self
    {
        $norma = NormaDaFolha::tryFrom((string) ($layout['norma'] ?? '')) ?? NormaDaFolha::Abnt;

        $fonte = in_array($layout['fonte'] ?? null, array_keys(self::FONTES), true)
            ? $layout['fonte']
            : 'sans';

        // O tamanho do nome não é matéria da norma: ela fala do corpo
        // do texto, não do timbre.
        $daInstituicao = isset($layout['tamanho_instituicao'])
            ? (int) $layout['tamanho_instituicao']
            : null;

        if ($norma->fixaAFormatacao()) {
            return new self($norma, $fonte, 12, 1.5, self::margensDaAbnt(), $daInstituicao);
        }

        return new self(
            norma: $norma,
            fonte: $fonte,
            tamanho: (int) ($layout['tamanho'] ?? 11),
            espacamento: (float) ($layout['espacamento'] ?? 1.15),
            margens: [
                'superior' => (float) ($layout['margens']['superior'] ?? 18),
                'inferior' => (float) ($layout['margens']['inferior'] ?? 16),
                'esquerda' => (float) ($layout['margens']['esquerda'] ?? 14),
                'direita' => (float) ($layout['margens']['direita'] ?? 14),
            ],
            tamanhoDaInstituicaoNoModelo: $daInstituicao,
        );
    }

    /** @return array{superior: float, inferior: float, esquerda: float, direita: float} */
    public static function margensDaAbnt(): array
    {
        return ['superior' => 30.0, 'inferior' => 20.0, 'esquerda' => 30.0, 'direita' => 20.0];
    }

    public function nomeDaFonte(): string
    {
        return self::FONTES[$this->fonte];
    }

    /**
     * As DejaVu vêm depois como rede: elas cobrem o acento sem
     * depender do que está instalado na máquina que gera o PDF, e as
     * famílias da norma vêm antes para serem as escolhidas quando
     * existem.
     */
    public function familiaCss(): string
    {
        return $this->fonte === 'serif'
            ? "'Times New Roman', 'DejaVu Serif', Georgia, serif"
            : "Arial, 'DejaVu Sans', Helvetica, sans-serif";
    }

    /**
     * Citação longa, legenda e trecho de código: 10 pt e espaçamento
     * simples é o que a norma pede para eles.
     */
    /** Limites do corpo do nome da instituição, em pontos. */
    public const TAMANHO_MINIMO_DA_INSTITUICAO = 8;

    public const TAMANHO_MAXIMO_DA_INSTITUICAO = 28;

    /**
     * O corpo do nome da instituição.
     *
     * Um ponto acima do texto por padrão — o que serve a um nome curto.
     * Nomes longos precisam descer, e é por isso que o modelo e a prova
     * podem dizer outro valor.
     */
    public function tamanhoDaInstituicao(?int $escolhido = null): int
    {
        $valor = $escolhido ?? $this->tamanhoDaInstituicaoNoModelo ?? ($this->tamanho + 1);

        return max(
            self::TAMANHO_MINIMO_DA_INSTITUICAO,
            min(self::TAMANHO_MAXIMO_DA_INSTITUICAO, $valor),
        );
    }

    public function tamanhoSecundario(): int
    {
        return $this->norma->fixaAFormatacao() ? 10 : max(8, $this->tamanho - 2);
    }

    public function espacamentoSecundario(): float
    {
        return $this->norma->fixaAFormatacao() ? 1.0 : $this->espacamento;
    }

    /** `margin` do `@page`, na ordem do CSS. */
    public function margemCss(): string
    {
        return sprintf(
            '%smm %smm %smm %smm',
            $this->margens['superior'],
            $this->margens['direita'],
            $this->margens['inferior'],
            $this->margens['esquerda'],
        );
    }

    /** @return array<string, mixed> Como o layout é gravado no modelo. */
    public function paraJson(): array
    {
        return [
            'norma' => $this->norma->value,
            'fonte' => $this->fonte,
            'tamanho' => $this->tamanho,
            'espacamento' => $this->espacamento,
            'margens' => $this->margens,
            'tamanho_instituicao' => $this->tamanhoDaInstituicaoNoModelo,
        ];
    }
}
