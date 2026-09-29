<?php

namespace App\Livewire\Documentos;

use App\Enums\TipoDeDocumento;
use App\Models\ModeloDocumento;
use App\Support\CamposDoDocumento;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * O editor do modelo de documento.
 *
 * O corpo é texto com campos entre chaves. A pré-visualização ao lado
 * usa dados de mentira, mas os mesmos campos: é onde se descobre que o
 * texto não cabe em três por página antes de imprimir noventa vias.
 */
class FormularioModeloDocumento extends Component
{
    use AuthorizesRequests;

    public ?ModeloDocumento $modelo = null;

    public ?int $eixo_id = null;

    public string $nome = '';

    public string $descricao = '';

    public string $tipo = 'individual';

    public string $corpo = '';

    public int $por_pagina = 1;

    public bool $ativo = true;

    public function mount(?ModeloDocumento $modelo = null): void
    {
        if ($modelo?->exists) {
            $this->authorize('update', $modelo);

            $this->modelo = $modelo;
            $this->eixo_id = $modelo->eixo_id;
            $this->nome = $modelo->nome;
            $this->descricao = (string) $modelo->descricao;
            $this->tipo = $modelo->tipo->value;
            $this->corpo = (string) $modelo->corpo;
            $this->por_pagina = $modelo->por_pagina;
            $this->ativo = $modelo->ativo;

            return;
        }

        $this->authorize('create', ModeloDocumento::class);

        $this->eixo_id = auth()->user()->eixoIds()[0] ?? null;
        $this->corpo = $this->exemplo();
    }

    public function tipoEscolhido(): TipoDeDocumento
    {
        return TipoDeDocumento::tryFrom($this->tipo) ?? TipoDeDocumento::Individual;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'eixo_id' => ['required', Rule::in(auth()->user()->eixoIds())],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(TipoDeDocumento::valores())],
            'corpo' => ['required', 'string', 'max:8000'],
            'por_pagina' => ['required', 'integer', Rule::in(array_keys(ModeloDocumento::POR_PAGINA))],
            'ativo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'eixo_id' => 'eixo',
            'descricao' => 'descrição',
            'tipo' => 'tipo de documento',
            'corpo' => 'texto do documento',
            'por_pagina' => 'vias por página',
        ];
    }

    public function salvar(): void
    {
        $this->modelo !== null
            ? $this->authorize('update', $this->modelo)
            : $this->authorize('create', ModeloDocumento::class);

        $this->validate();

        /*
         * Campo inexistente não é erro de digitação inofensivo: ele sai
         * impresso como está, em todas as vias. A tela diz qual é e por
         * quê, em vez de recusar com "texto inválido".
         */
        $invalidos = CamposDoDocumento::validar($this->corpo, $this->tipoEscolhido());

        if ($invalidos !== []) {
            foreach ($invalidos as $campo) {
                $this->addError('corpo', CamposDoDocumento::motivoDaRecusa($campo, $this->tipoEscolhido()));
            }

            return;
        }

        $dados = [
            'eixo_id' => $this->eixo_id,
            'nome' => $this->nome,
            'descricao' => $this->descricao ?: null,
            'tipo' => $this->tipo,
            'corpo' => $this->corpo,
            // Repetir a lista da turma na mesma folha não significa nada.
            'por_pagina' => $this->tipoEscolhido() === TipoDeDocumento::Individual ? $this->por_pagina : 1,
            'ativo' => $this->ativo,
        ];

        if ($this->modelo === null) {
            $dados['criado_por'] = auth()->id();

            ModeloDocumento::create($dados);
        } else {
            $this->modelo->update($dados);
        }

        session()->flash('sucesso', 'Modelo de documento salvo.');

        $this->redirectRoute('documentos.index', navigate: true);
    }

    /**
     * Acrescenta o campo ao fim do texto.
     *
     * Ao fim, e não onde está o cursor: o Livewire não sabe onde o cursor
     * está, e fingir que sabe erraria a posição de vez em quando — pior
     * do que sempre pôr no mesmo lugar previsível. O rótulo dos campos de
     * preencher já vem escrito, para a pessoa ver que existe e trocar.
     */
    public function inserirCampo(string $campo): void
    {
        $aceitos = array_keys(CamposDoDocumento::disponiveis($this->tipoEscolhido()));

        if (! in_array($campo, $aceitos, true)) {
            return;
        }

        $modelo = match ($campo) {
            'linha' => '{{ linha: rótulo }}',
            'assinatura' => '{{ assinatura: rótulo }}',
            'caixa' => '{{ caixa: rótulo }}',
            default => '{{ '.$campo.' }}',
        };

        $this->corpo = rtrim($this->corpo)."\n".$modelo;
    }

    /** A pré-visualização, com dados de mentira e os campos de verdade. */
    public function previa(): HtmlString
    {
        $contexto = CamposDoDocumento::contexto(
            aluno: null,
            numero: 7,
            turma: '1 A',
            periodo: 1,
            periodoLetivo: (string) now()->year,
            curso: 'Desenvolvimento de Sistemas',
            eixo: 'Tecnologia da Informação',
            instituicao: (string) config('instituicao.nome'),
            extra: [
                'aluno.nome' => 'Marina Alves de Souza',
                'aluno.ra' => '20261001',
                'lista_de_alunos' => view('documentos.previa-da-lista')->render(),
            ],
        );

        return CamposDoDocumento::render($this->corpo, $contexto);
    }

    protected function exemplo(): string
    {
        return 'Eu, {{ linha: nome do responsável }}, responsável pelo(a) aluno(a) '
            .'**{{ aluno.nome }}**, RA {{ aluno.ra }}, da turma {{ turma.nome }} do curso de '
            ."{{ curso.nome }}, declaro estar ciente e:\n\n"
            ."{{ caixa: Autorizo }}    {{ caixa: Não autorizo }}\n\n"
            ."a participação na atividade.\n\n"
            .'{{ assinatura: Assinatura do responsável }}';
    }

    public function render(): View
    {
        return view('documentos.formulario', [
            'campos' => CamposDoDocumento::disponiveis($this->tipoEscolhido()),
            'eixos' => auth()->user()->eixos()->orderBy('nome')->get(['eixos.id', 'eixos.nome']),
        ])->layout('components.layouts.app', [
            'titulo' => $this->modelo === null ? 'Novo modelo de documento' : 'Editar modelo',
            'subtitulo' => 'O texto sai igual em todas as vias; os campos entre chaves viram os dados de cada aluno',
        ]);
    }
}
