<?php

namespace App\Livewire\Importacoes;

use App\Actions\Resultado\ConferirImportacaoAction;
use App\Actions\Resultado\ConfirmarImportacaoAction;
use App\Enums\StatusImportacao;
use App\Enums\StatusProva;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Importacao;
use App\Models\Prova;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Importação de resultados em dois passos.
 *
 * O primeiro **não grava nada**: lê a planilha e mostra, linha a linha,
 * de qual aluno ela é e por qual critério foi reconhecida. Só depois de
 * ver isso é que se confirma — importar resultado no aluno errado não
 * tem desfazer fácil.
 */
class NovaImportacao extends Component
{
    use AuthorizesRequests;
    use Notifica;
    use WithFileUploads;

    public ?int $prova_id = null;

    public ?TemporaryUploadedFile $planilha = null;

    public ?int $importacao_id = null;

    public function mount(): void
    {
        $this->authorize('create', Importacao::class);

        $this->prova_id = (int) request()->query('prova') ?: null;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'prova_id' => ['required', Rule::in($this->provasDisponiveis()->pluck('id')->all())],
            'planilha' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['prova_id' => 'prova', 'planilha' => 'planilha'];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'prova_id.required' => 'Escolha a prova cujos resultados serão importados.',
            'planilha.mimes' => 'Envie o arquivo do leitor de folhas em XLSX, XLS ou CSV.',
        ];
    }

    /** Passo 1: lê e confere, sem gravar resultado nenhum. */
    public function conferir(ConferirImportacaoAction $action): void
    {
        $this->authorize('create', Importacao::class);

        $dados = $this->validate();

        $prova = Prova::query()->findOrFail($dados['prova_id']);

        $this->authorize('view', $prova);

        $caminho = $this->planilha->store('importacoes', 'local');

        $importacao = Importacao::create([
            'prova_id' => $prova->getKey(),
            'usuario_id' => auth()->id(),
            'arquivo' => $caminho,
            'nome_original' => $this->planilha->getClientOriginalName(),
            'hash' => hash_file('sha256', $this->planilha->getRealPath()),
            'status' => StatusImportacao::Validando,
        ]);

        try {
            $action->executar($importacao, auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $importacao->update(['status' => StatusImportacao::Falhou]);

            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->importacao_id = $importacao->getKey();
        $this->planilha = null;

        $this->notificarSucesso('Planilha conferida. Nada foi gravado ainda — veja o relatório abaixo.');
    }

    /** Passo 2: grava o que a conferência aprovou. */
    public function confirmar(ConfirmarImportacaoAction $action): void
    {
        $importacao = $this->importacaoEmConferencia();

        if ($importacao === null) {
            return;
        }

        $this->authorize('confirmar', $importacao);

        try {
            $action->executar($importacao, auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->flashSucesso('Resultados importados. As notas por disciplina já estão calculadas.');

        $this->redirectRoute('resultados.index', ['prova' => $importacao->prova_id], navigate: true);
    }

    public function descartar(): void
    {
        $importacao = $this->importacaoEmConferencia();

        if ($importacao !== null) {
            $importacao->update(['status' => StatusImportacao::Cancelada]);
        }

        $this->importacao_id = null;

        $this->notificarSucesso('Conferência descartada. Nada tinha sido gravado.');
    }

    public function importacaoEmConferencia(): ?Importacao
    {
        if ($this->importacao_id === null) {
            return null;
        }

        return Importacao::query()
            ->with('prova.turma')
            ->visivelPara(auth()->user())
            ->find($this->importacao_id);
    }

    /** @return Collection<int, Prova> */
    public function provasDisponiveis(): Collection
    {
        return Prova::query()
            ->visivelPara(auth()->user())
            ->with(['turma:id,nome,periodo,periodo_letivo,curso_id', 'turma.curso:id,nome,eixo_id'])
            ->whereIn('status', [StatusProva::Gerada, StatusProva::Aplicada])
            ->orderByDesc('gerada_em')
            ->get();
    }

    public function render(): View
    {
        $importacao = $this->importacaoEmConferencia();

        return view('importacoes.nova', [
            'provas' => $this->provasDisponiveis(),
            'importacao' => $importacao,
            'linhas' => collect($importacao?->relatorio['linhas'] ?? []),
        ])->layout('components.layouts.app', [
            'titulo' => 'Importar resultados',
            'subtitulo' => 'Confira antes de gravar: o primeiro passo não altera nada',
        ]);
    }
}
