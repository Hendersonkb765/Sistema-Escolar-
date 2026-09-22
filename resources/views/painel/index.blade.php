<div class="space-y-6">
    @if ($usuario->ehGestao() && count($usuario->eixoIds()) === 0)
        <x-alerta tipo="atencao" titulo="Sua conta ainda não tem Eixo vinculado">
            Sem vínculo de Eixo, nenhum curso, turma ou avaliação fica visível. Peça a um PAEET Admin
            que vincule sua conta aos Eixos sob sua responsabilidade.
        </x-alerta>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ($indicadores as $indicador)
            <x-indicador
                :rotulo="$indicador['rotulo']"
                :valor="$indicador['valor']"
                :cor="$indicador['cor']"
                :href="$indicador['href']"
                :detalhe="$indicador['detalhe'] ?? null"/>
        @endforeach
    </div>

    <x-cartao titulo="Bem-vindo, {{ Str::before($usuario->nome, ' ') }}"
              descricao="Perfil: {{ $usuario->perfil->rotulo() }}">
        <p class="text-sm text-slate-600 dark:text-slate-300">
            @if ($usuario->ehProfessor())
                Aqui você acompanha as solicitações de questões recebidas e seus prazos. Um prazo
                vencido não impede o envio — a solicitação apenas fica marcada como atrasada, e o
                envio continua sendo aceito.
            @else
                Use o menu lateral para gerenciar a estrutura acadêmica, solicitar questões aos
                professores, analisar o que foi enviado, montar provas e importar resultados.
            @endif
        </p>

        <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
            Os indicadores de provas e resultados entram junto com os milestones 5 a 7.
        </p>
    </x-cartao>
</div>
