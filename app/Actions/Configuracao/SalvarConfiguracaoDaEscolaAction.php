<?php

namespace App\Actions\Configuracao;

use App\Exceptions\RegraDeNegocioException;
use App\Models\Turma;
use App\Models\User;
use App\Support\ConfiguracaoDaEscola;
use App\Support\IntervaloDoDia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Grava a configuração da escola: nome, aulas por dia e intervalos.
 *
 * O formulário garante o formato. Aqui fica o que depende do conjunto, e
 * o registro na auditoria de quem mudou o quê — uma mudança aqui vale
 * para todas as turmas de uma vez.
 *
 * Cada intervalo diz a quem se aplica. Dois intervalos só se atrapalham
 * quando valem para alguma turma em comum: o das 19h para todas menos a
 * 3B e o das 19h30 só para a 3B convivem, e é para isso que existem.
 */
class SalvarConfiguracaoDaEscolaAction
{
    /**
     * @param  array<int, array{nome: string, inicio: string, fim: string, alcance: string, turmas: array<int, int>}>  $intervalos
     */
    public function executar(User $autor, string $nome, int $aulasPorDia, array $intervalos): void
    {
        if ($intervalos === []) {
            throw RegraDeNegocioException::porque('Informe ao menos um intervalo.');
        }

        $intervalos = array_map(fn (array $i) => $this->normalizar($i), $intervalos);
        usort($intervalos, fn (array $a, array $b) => strcmp($a['inicio'], $b['inicio']));

        $this->conferirSobreposicao($intervalos);

        $antes = array_combine(ConfiguracaoDaEscola::CHAVES, array_map(
            fn (string $chave) => ConfiguracaoDaEscola::valor($chave), ConfiguracaoDaEscola::CHAVES,
        ));

        $depois = [
            'nome' => trim($nome),
            'aulas_por_dia' => $aulasPorDia,
            // Guardado como no arquivo: início e duração.
            'intervalos' => array_map(fn (array $i) => [
                'nome' => $i['nome'],
                'inicio' => $i['inicio'],
                'minutos' => (int) Carbon::createFromFormat('H:i', $i['inicio'])->diffInMinutes(Carbon::createFromFormat('H:i', $i['fim'])),
                'alcance' => $i['alcance'],
                'turmas' => $i['turmas'],
            ], $intervalos),
        ];

        DB::transaction(function () use ($autor, $antes, $depois) {
            ConfiguracaoDaEscola::gravar($depois);

            activity('configuracao')
                ->causedBy($autor)
                ->withProperties(['old' => $antes, 'attributes' => $depois])
                ->log('Configuração da escola alterada');
        });
    }

    /**
     * "Todas, exceto nenhuma" é "todas"; "só as marcadas" sem marcada
     * nenhuma não vale para ninguém, e é recusado em vez de gravado.
     *
     * @param  array{nome: string, inicio: string, fim: string, alcance: string, turmas: array<int, int>}  $intervalo
     */
    private function normalizar(array $intervalo): array
    {
        $turmas = array_values(array_unique(array_map('intval', $intervalo['turmas'] ?? [])));
        sort($turmas);

        $existentes = Turma::query()->whereKey($turmas)->pluck('id')->all();

        if (count($existentes) !== count($turmas)) {
            throw RegraDeNegocioException::porque('Uma das turmas marcadas não existe mais. Recarregue a página.');
        }

        $alcance = $intervalo['alcance'];

        if ($alcance === IntervaloDoDia::EXCETO && $turmas === []) {
            $alcance = IntervaloDoDia::TODAS;
        }

        if ($alcance === IntervaloDoDia::SOMENTE && $turmas === []) {
            throw RegraDeNegocioException::porque(
                "{$intervalo['nome']} ({$intervalo['inicio']}–{$intervalo['fim']}): marque ao menos uma turma, ou aplique a todas."
            );
        }

        return [
            'nome' => trim($intervalo['nome']),
            'inicio' => $intervalo['inicio'],
            'fim' => $intervalo['fim'],
            'alcance' => $alcance,
            'turmas' => $alcance === IntervaloDoDia::TODAS ? [] : $turmas,
        ];
    }

    /** @param  array<int, array{nome: string, inicio: string, fim: string, alcance: string, turmas: array<int, int>}>  $intervalos */
    private function conferirSobreposicao(array $intervalos): void
    {
        foreach ($intervalos as $i => $a) {
            foreach (array_slice($intervalos, $i + 1) as $b) {
                if ($b['inicio'] >= $a['fim']) {
                    continue;
                }

                $onde = $this->turmaEmComum($a, $b);

                if ($onde === false) {
                    continue;
                }

                throw RegraDeNegocioException::porque(
                    "{$b['nome']} ({$b['inicio']}–{$b['fim']}) se sobrepõe a {$a['nome']} ({$a['inicio']}–{$a['fim']})"
                    .($onde === null ? '.' : " na turma {$onde}.")
                );
            }
        }
    }

    /**
     * Uma turma a que os dois se aplicam: o nome dela, null quando os dois
     * valem para "todas" (inclusive as que ainda vão ser criadas), ou
     * false quando não há turma em comum.
     */
    private function turmaEmComum(array $a, array $b): string|false|null
    {
        if ($a['alcance'] !== IntervaloDoDia::SOMENTE && $b['alcance'] !== IntervaloDoDia::SOMENTE) {
            return null;
        }

        $intervaloA = new IntervaloDoDia($a['nome'], $a['inicio'], $a['fim'], $a['alcance'], $a['turmas']);
        $intervaloB = new IntervaloDoDia($b['nome'], $b['inicio'], $b['fim'], $b['alcance'], $b['turmas']);

        // Quem é "somente" limita as candidatas.
        $candidatas = $a['alcance'] === IntervaloDoDia::SOMENTE ? $a['turmas'] : $b['turmas'];

        $comum = collect($candidatas)->first(fn (int $id) => $intervaloA->aplicaA($id) && $intervaloB->aplicaA($id));

        return $comum === null ? false : Turma::query()->whereKey($comum)->value('nome');
    }
}
