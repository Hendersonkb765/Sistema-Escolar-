<?php

namespace App\Livewire\Resultados;

use App\Enums\Bimestre;
use App\Models\Prova;
use App\Models\ResultadoAluno;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * As notas de uma prova, questão a questão.
 *
 * Cada disciplina tem a sua nota de 0 a 10, calculada contra a soma dos
 * pesos dela — somar questões de disciplinas diferentes numa nota só
 * apagaria o que a escola quer ver.
 */
class ListaResultados extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'prova', except: '')]
    public string $prova_id = '';

    #[Url(as: 'disciplina', except: '')]
    public string $filtroDisciplina = '';

    #[Url(as: 'bimestre', except: '')]
    public string $filtroBimestre = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ResultadoAluno::class);

        /*
         * A tela abre já mostrando alguma coisa.
         *
         * Sem isto ela abria em "Escolha uma prova" com um recado dizendo
         * que as notas aparecem depois da importação — o contrário do que
         * estava acontecendo, porque havia notas e elas não apareciam.
         * Quem chega aqui quer ver a última prova; escolher outra é o
         * caso menos comum.
         *
         * Só quando ninguém escolheu: uma prova no endereço é decisão de
         * quem montou o link, mesmo que ela ainda não tenha resultado —
         * aí a tela diz isso, que é a informação certa.
         */
        if ($this->prova_id === '') {
            $this->prova_id = (string) ($this->provasDisponiveis()->first()?->id ?? '');
        }
    }

    /**
     * Filtrar por bimestre muda as opções do select.
     *
     * A escolha anterior pode ter ficado de fora, e uma escolha que
     * aponta para fora da lista deixa a tela em branco sem dizer por quê.
     * Nesse caso vale a primeira do novo recorte.
     */
    public function updatedFiltroBimestre(): void
    {
        $this->reset('filtroDisciplina');

        $disponiveis = $this->provasDisponiveis();

        if ($this->prova_id !== '' && $disponiveis->contains('id', (int) $this->prova_id)) {
            return;
        }

        $this->prova_id = (string) ($disponiveis->first()?->id ?? '');
    }

    public function provaSelecionada(): ?Prova
    {
        if ($this->prova_id === '') {
            return null;
        }

        return Prova::query()
            ->visivelPara(auth()->user())
            ->with(['turma:id,nome,curso_id', 'turma.curso:id,nome,eixo_id'])
            ->find((int) $this->prova_id);
    }

    /** @return Collection<int, Prova> */
    public function provasDisponiveis(): Collection
    {
        return Prova::query()
            ->visivelPara(auth()->user())
            ->with(['turma:id,nome,curso_id', 'turma.curso:id,nome,eixo_id'])
            ->whereHas('resultados')
            ->when($this->filtroBimestre !== '', fn ($q) => $q->where('bimestre', $this->filtroBimestre))
            ->orderByDesc('gerada_em')
            ->get();
    }

    public function render(): View
    {
        $prova = $this->provaSelecionada();

        $questoes = $prova === null
            ? collect()
            : $prova->questoes()->with('disciplina:id,nome')->orderBy('numero')->get();

        $resultados = $prova === null
            ? collect()
            : ResultadoAluno::query()
                ->where('prova_id', $prova->getKey())
                ->visivelPara(auth()->user())
                ->with([
                    'aluno:id,nome,ra,turma_id',
                    'respostas:id,resultado_aluno_id,prova_questao_id,acertou,peso',
                    'notas:id,resultado_aluno_id,disciplina_id,nota,soma_pesos_acertos,soma_pesos_total',
                    'notas.disciplina:id,nome',
                ])
                ->get()
                ->sortBy(fn (ResultadoAluno $resultado) => $resultado->aluno->nome)
                ->values();

        $disciplinas = $questoes->pluck('disciplina.nome', 'disciplina.id')->unique();

        return view('resultados.lista', [
            'provas' => $this->provasDisponiveis(),
            'bimestres' => Bimestre::opcoes(),
            'prova' => $prova,
            'questoes' => $this->filtroDisciplina === ''
                ? $questoes
                : $questoes->where('disciplina_id', (int) $this->filtroDisciplina)->values(),
            'disciplinas' => $disciplinas,
            'resultados' => $resultados,
            'bimestreEscolhido' => $this->filtroBimestre === ''
                ? null
                : Bimestre::tryFrom((int) $this->filtroBimestre)?->rotulo(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Resultados e notas',
            'subtitulo' => 'Uma nota por disciplina, e o acerto de cada questão',
        ]);
    }
}
