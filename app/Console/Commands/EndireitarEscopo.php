<?php

namespace App\Console\Commands;

use App\Exceptions\RegraDeNegocioException;
use App\Support\EndireitaColunasDoEscopo;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Desfaz o giro de colunas na planilha do escopo-sequência.
 *
 * O arquivo chega pronto da rede, e o que veio torto não se conserta
 * no banco: conserta-se no arquivo, antes de importar. Este comando
 * escreve uma cópia endireitada e diz exatamente o que mudou — o
 * original fica intacto, e quem confere abre os dois lado a lado.
 */
class EndireitarEscopo extends Command
{
    protected $signature = 'escopo:endireitar
                            {arquivo : A planilha do escopo-sequência}
                            {--saida= : Onde gravar a cópia (padrão: ao lado, com sufixo -endireitado)}';

    protected $description = 'Endireita as colunas giradas na planilha do escopo-sequência';

    public function handle(): int
    {
        $origem = $this->argument('arquivo');
        $destino = $this->option('saida') ?: $this->aoLado($origem);

        try {
            $resultado = EndireitaColunasDoEscopo::em($origem, $destino);
        } catch (RegraDeNegocioException $excecao) {
            $this->error($excecao->getMessage());

            return self::FAILURE;
        }

        if ($resultado->corrigidos === [] && $resultado->naoResolvidos === []) {
            $this->info('Nada a endireitar: cada código tem um nome só. Pode importar o arquivo como está.');

            return self::SUCCESS;
        }

        foreach ($resultado->corrigidos as $codigo => $dados) {
            $this->line("  <fg=green>{$codigo}</> — {$dados['linhas']} linha(s)");
            $this->line('     nome estava: '.Str::limit($dados['de'], 60));
            $this->line('     nome passa a ser: '.$dados['para']);
        }

        foreach ($resultado->naoResolvidos as $codigo => $quantos) {
            $this->warn(
                "  {$codigo} — {$quantos} nomes, e o giro das três colunas não resolve. "
                .'Estas linhas ficaram como estavam: confira a planilha à mão.'
            );
        }

        if ($resultado->linhasTocadas === 0) {
            $this->error('Nenhuma linha pôde ser endireitada. O arquivo não foi copiado.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("{$resultado->linhasTocadas} linha(s) endireitada(s). Cópia gravada em:");
        $this->line('  '.$destino);
        $this->newLine();
        $this->comment('O original não foi alterado. Confira a cópia antes de importar.');

        return self::SUCCESS;
    }

    private function aoLado(string $origem): string
    {
        $pasta = dirname($origem);
        $nome = pathinfo($origem, PATHINFO_FILENAME);

        // Sempre XLSX: é o que o escritor produz, qualquer que seja a entrada.
        return $pasta.DIRECTORY_SEPARATOR.$nome.'-endireitado.xlsx';
    }
}
