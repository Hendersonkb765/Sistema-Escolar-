<?php

namespace App\Actions\Calendario;

use App\Exceptions\RegraDeNegocioException;
use App\Models\EventoEscolar;
use App\Models\User;
use App\Support\PlanilhaDeEscopo;
use App\Support\PlanilhaDeEventos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Traz os eventos e feriados de uma planilha para o calendário da escola.
 *
 * Em dois passos, como as outras importações: {@see conferir()} não grava
 * nada e diz o que vai acontecer — quantos entram, quais já estavam no
 * calendário, quais linhas não puderam ser lidas e por quê. Só depois se
 * confirma.
 *
 * Importar **acrescenta**, não substitui: os eventos cadastrados à mão
 * continuam lá. O que já existe — mesmo título no mesmo período — é
 * deixado de fora, para que reenviar a mesma planilha não duplique o ano.
 */
class ImportarEventosAction
{
    /**
     * @return array{
     *     entram: array<int, array{linha: int, titulo: string, tipo: string, periodo: string, sem_aula: bool}>,
     *     repetidos: array<int, array{linha: int, titulo: string, periodo: string}>,
     *     recusadas: array<int, array{linha: int, titulo: ?string, erro: string}>,
     * }
     */
    public function conferir(string $caminho, User $autor): array
    {
        Gate::forUser($autor)->authorize('gerenciar', EventoEscolar::class);

        $relatorio = ['entram' => [], 'repetidos' => [], 'recusadas' => []];

        foreach ($this->classificar($caminho) as [$destino, $linha]) {
            $relatorio[$destino][] = match ($destino) {
                'recusadas' => ['linha' => $linha['linha'], 'titulo' => $linha['titulo'], 'erro' => $linha['erro']],
                'repetidos' => ['linha' => $linha['linha'], 'titulo' => $linha['titulo'], 'periodo' => $this->periodo($linha)],
                'entram' => [
                    'linha' => $linha['linha'],
                    'titulo' => $linha['titulo'],
                    'tipo' => $linha['tipo']->rotulo(),
                    'periodo' => $this->periodo($linha),
                    'sem_aula' => $linha['tipo']->semAula(),
                ],
            };
        }

        return $relatorio;
    }

    /** @return int quantos eventos entraram */
    public function executar(string $caminho, User $autor): int
    {
        Gate::forUser($autor)->authorize('gerenciar', EventoEscolar::class);

        $novos = collect($this->classificar($caminho))
            ->filter(fn (array $par) => $par[0] === 'entram')
            ->map(fn (array $par) => $par[1]);

        if ($novos->isEmpty()) {
            throw RegraDeNegocioException::porque(
                'Nenhum evento novo para importar: as linhas ou já estão no calendário, ou não puderam ser lidas.'
            );
        }

        DB::transaction(function () use ($novos) {
            foreach ($novos as $linha) {
                EventoEscolar::query()->create([
                    'titulo' => $linha['titulo'],
                    'tipo' => $linha['tipo'],
                    'inicio' => $linha['inicio'],
                    'fim' => $linha['fim'],
                    'descricao' => $linha['descricao'],
                ]);
            }
        });

        return $novos->count();
    }

    /**
     * Cada linha e para onde ela vai: entra, repetida ou recusada.
     *
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function classificar(string $caminho): array
    {
        $linhas = PlanilhaDeEventos::ler($caminho)->linhas;

        $jaCadastrados = EventoEscolar::query()
            ->get(['titulo', 'inicio', 'fim'])
            ->map(fn (EventoEscolar $e) => $this->chave($e->titulo, $e->inicio->toDateString(), $e->fim->toDateString()))
            ->flip();

        $vistos = [];
        $classificadas = [];

        foreach ($linhas as $linha) {
            if ($linha['erro'] !== null) {
                $classificadas[] = ['recusadas', $linha];

                continue;
            }

            $chave = $this->chave($linha['titulo'], $linha['inicio']->toDateString(), $linha['fim']->toDateString());

            if ($jaCadastrados->has($chave) || isset($vistos[$chave])) {
                $classificadas[] = ['repetidos', $linha];

                continue;
            }

            $vistos[$chave] = true;
            $classificadas[] = ['entram', $linha];
        }

        return $classificadas;
    }

    /** Título sem caixa nem acento, e o período: "Natal" e "NATAL" no mesmo dia são o mesmo evento. */
    private function chave(string $titulo, string $inicio, string $fim): string
    {
        return PlanilhaDeEscopo::normalizar($titulo)."|{$inicio}|{$fim}";
    }

    /** @param  array<string, mixed>  $linha */
    private function periodo(array $linha): string
    {
        return $linha['inicio']->isSameDay($linha['fim'])
            ? $linha['inicio']->format('d/m/Y')
            : "{$linha['inicio']->format('d/m/Y')} a {$linha['fim']->format('d/m/Y')}";
    }
}
