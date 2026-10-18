<?php

namespace App\Livewire\Escopo;

use App\Actions\Academico\ImportarEscopoAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Curso;
use App\Models\EscopoAula;
use App\Support\PlanilhaDeEscopo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Importação do escopo-sequência, em dois passos.
 *
 * O primeiro não grava nada: diz quantas aulas entram, quantas linhas
 * serão substituídas e quais componentes não casaram com disciplina
 * nenhuma. Confirmar troca o período inteiro, e isso não tem desfazer
 * fácil — por isso o número aparece antes.
 */
class ImportarEscopo extends Component
{
    use AuthorizesRequests;
    use Notifica;
    use WithFileUploads;

    public ?int $curso_id = null;

    public ?int $periodo = null;

    public ?TemporaryUploadedFile $planilha = null;

    /** O relatório da conferência, enquanto ela não é confirmada nem descartada. */
    public ?array $relatorio = null;

    public function mount(): void
    {
        $this->authorize('importar', EscopoAula::class);

        $this->curso_id = (int) request()->query('curso') ?: null;
        $this->periodo = (int) request()->query('periodo') ?: null;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'curso_id' => ['required', Rule::in($this->cursosDisponiveis()->pluck('id')->all())],
            'periodo' => ['required', 'integer', 'min:1', 'max:'.$this->maximoDePeriodos()],
            'planilha' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['curso_id' => 'curso', 'periodo' => 'período', 'planilha' => 'planilha'];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'curso_id.required' => 'Escolha o curso a que este escopo pertence.',
            'periodo.required' => 'Escolha o período do curso que a planilha cobre.',
            'planilha.mimes' => 'Envie o escopo-sequência em XLSX, XLS ou CSV.',
            'planilha.max' => 'O arquivo passa de 10 MB. Envie só a aba do escopo.',
        ];
    }

    /** Trocar de curso invalida a conferência: ela era de outro percurso. */
    public function updatedCursoId(): void
    {
        $this->relatorio = null;
    }

    public function updatedPeriodo(): void
    {
        $this->relatorio = null;
    }

    public function conferir(ImportarEscopoAction $action): void
    {
        $this->authorize('importar', EscopoAula::class);

        $this->validate();

        try {
            $this->relatorio = $action->conferir(
                caminho: $this->planilha->getRealPath(),
                curso: $this->cursoEscolhido(),
                periodo: (int) $this->periodo,
                autor: auth()->user(),
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->relatorio = null;
            $this->notificarErro($excecao->getMessage());
        }
    }

    public function descartar(): void
    {
        $this->reset('relatorio', 'planilha');

        $this->notificarSucesso('Conferência descartada. Nada tinha sido gravado.');
    }

    public function confirmar(ImportarEscopoAction $action): void
    {
        $this->authorize('importar', EscopoAula::class);

        if ($this->relatorio === null) {
            $this->notificarErro('Confira a planilha antes de confirmar.');

            return;
        }

        $this->validate();

        $curso = $this->cursoEscolhido();
        $periodo = (int) $this->periodo;

        try {
            $resultado = $action->executar(
                caminho: $this->planilha->getRealPath(),
                curso: $curso,
                periodo: $periodo,
                autor: auth()->user(),
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $substituidas = $resultado['substituidas'] > 0
            ? " {$resultado['substituidas']} aula(s) anteriores foram substituídas."
            : '';

        $this->flashSucesso(
            "Escopo do {$periodo}º período de {$curso->nome} importado: "
            ."{$resultado['aulas']} aula(s).".$substituidas
        );

        $this->redirectRoute('escopo.index', [
            'curso' => $curso->getKey(),
            'periodo' => $periodo,
        ], navigate: true);
    }

    /** @return Collection<int, Curso> */
    public function cursosDisponiveis(): Collection
    {
        return Curso::query()
            ->visivelPara(auth()->user())
            ->with('eixo:id,nome')
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo', 'duracao_anos', 'eixo_id']);
    }

    protected function cursoEscolhido(): Curso
    {
        return $this->cursosDisponiveis()->firstWhere('id', $this->curso_id)
            ?? abort(404);
    }

    /** O maior número de períodos entre os cursos à vista. */
    protected function maximoDePeriodos(): int
    {
        $curso = $this->cursosDisponiveis()->firstWhere('id', $this->curso_id);

        return (int) ($curso?->duracao_anos ?? 3);
    }

    public function render(): View
    {
        $curso = $this->curso_id === null
            ? null
            : $this->cursosDisponiveis()->firstWhere('id', $this->curso_id);

        return view('escopo.importar', [
            'cursos' => $this->cursosDisponiveis(),
            'cursoEscolhido' => $curso,
            'periodos' => $curso === null ? [] : range(1, $curso->duracao_anos),
            // Como estão escritas no arquivo, não como o sistema as
            // normaliza: quem confere a planilha procura o que está lá.
            'colunas' => PlanilhaDeEscopo::ROTULOS,
        ])->layout('components.layouts.app', [
            'titulo' => 'Importar escopo-sequência',
            'subtitulo' => 'Confira antes de gravar: o primeiro passo não altera nada',
        ]);
    }
}
