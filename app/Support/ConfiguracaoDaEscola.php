<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * A configuração da escola que o PAEET Admin altera pela tela.
 *
 * O valor gravado sobrepõe o de config/instituicao.php na inicialização,
 * e o resto do sistema continua lendo `config('instituicao.*')` sem saber
 * de onde veio. O arquivo vira o valor inicial: vale até alguém salvar a
 * tela pela primeira vez.
 */
final class ConfiguracaoDaEscola
{
    /** O que a tela altera. Outra chave em `instituicao.*` segue só no arquivo. */
    public const CHAVES = ['nome', 'aulas_por_dia', 'intervalos'];

    /** Lê o banco e põe por cima da configuração. Chamado no boot e depois de salvar. */
    public static function aplicar(): void
    {
        try {
            $gravadas = DB::table('configuracoes')->whereIn('chave', self::CHAVES)->pluck('valor', 'chave');
        } catch (QueryException) {
            // Antes da migration — instalação nova, `migrate` rodando. Vale o arquivo.
            return;
        }

        foreach ($gravadas as $chave => $valor) {
            config(["instituicao.{$chave}" => json_decode($valor, true)]);
        }
    }

    /** @param  array<string, mixed>  $valores  chave => valor, só as de CHAVES */
    public static function gravar(array $valores): void
    {
        foreach (array_intersect_key($valores, array_flip(self::CHAVES)) as $chave => $valor) {
            DB::table('configuracoes')->updateOrInsert(
                ['chave' => $chave],
                ['valor' => json_encode($valor, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        self::aplicar();
    }

    /** O valor em uso agora, gravado ou do arquivo. */
    public static function valor(string $chave): mixed
    {
        return config("instituicao.{$chave}");
    }
}
