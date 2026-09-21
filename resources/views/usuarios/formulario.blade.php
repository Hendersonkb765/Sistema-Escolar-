<div class="mx-auto max-w-3xl space-y-4">
    <form wire:submit="salvar" class="space-y-4">
        <x-cartao titulo="Dados da conta"
                  descricao="A conta passa a existir apenas com este cadastro — não há autocadastro no sistema.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-campo rotulo="Nome completo" para="nome" obrigatorio :erro="$errors->first('nome')">
                    <x-input id="nome" wire:model="nome" required aria-invalid="{{ $errors->has('nome') ? 'true' : 'false' }}"/>
                </x-campo>

                <x-campo rotulo="E-mail" para="email" obrigatorio :erro="$errors->first('email')"
                         ajuda="Será o login do usuário.">
                    <x-input tipo="email" id="email" wire:model="email" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"/>
                </x-campo>

                <x-campo rotulo="Telefone" para="telefone" :erro="$errors->first('telefone')">
                    <x-input id="telefone" wire:model="telefone"/>
                </x-campo>

                <x-campo rotulo="Perfil" para="perfil" obrigatorio :erro="$errors->first('perfil')">
                    <x-select id="perfil" wire:model.live="perfil" required>
                        @foreach ($perfisDisponiveis as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-select>
                </x-campo>
            </div>

            <label class="mt-4 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" wire:model="ativo"
                       class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                Conta ativa (uma conta inativa não consegue entrar no sistema)
            </label>
        </x-cartao>

        <x-cartao titulo="Senha"
                  :descricao="$usuario === null
                      ? 'Deixe automático para gerar uma senha provisória exibida depois de salvar.'
                      : 'Preencha apenas se quiser redefinir a senha deste usuário.'">
            @if ($usuario === null)
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model.live="definirSenhaManualmente"
                           class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                    Definir a senha manualmente
                </label>
            @endif

            @if ($usuario !== null || $definirSenhaManualmente)
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-campo rotulo="Senha" para="senha" :erro="$errors->first('senha')"
                             ajuda="Mínimo de 8 caracteres, com letras e números.">
                        <x-input tipo="password" id="senha" wire:model="senha" autocomplete="new-password"/>
                    </x-campo>

                    <x-campo rotulo="Confirmar senha" para="senha_confirmation">
                        <x-input tipo="password" id="senha_confirmation" wire:model="senha_confirmation" autocomplete="new-password"/>
                    </x-campo>
                </div>
            @endif
        </x-cartao>

        <x-cartao titulo="Escopo de Eixos"
                  descricao="Define tudo o que esta conta enxerga: cursos, turmas, alunos, solicitações, provas e resultados.">
            @if ($eixosDisponiveis->isEmpty())
                <x-alerta tipo="atencao">Você não tem Eixos no seu escopo para vincular.</x-alerta>
            @else
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($eixosDisponiveis as $eixo)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                            <input type="checkbox" value="{{ $eixo->id }}" wire:model="eixosSelecionados"
                                   class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                            <span class="text-slate-700 dark:text-slate-200">{{ $eixo->nome }}</span>
                            <x-badge cor="cinza" class="ml-auto">{{ $eixo->codigo }}</x-badge>
                        </label>
                    @endforeach
                </div>
                @error('eixosSelecionados.*')
                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            @endif
        </x-cartao>

        <x-cartao titulo="Vínculo docente"
                  descricao="Qualquer perfil pode lecionar. Um PAEET que dá aula usa a mesma conta — basta marcar as disciplinas.">
            @if ($disciplinasDisponiveis->isEmpty())
                <x-vazio titulo="Nenhuma disciplina cadastrada"
                         descricao="Cadastre disciplinas na estrutura acadêmica para poder vincular docentes."/>
            @else
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($disciplinasDisponiveis as $disciplina)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                            <input type="checkbox" value="{{ $disciplina->id }}" wire:model="disciplinasSelecionadas"
                                   class="rounded border-slate-300 text-marca-600 focus:ring-marca-500 dark:border-slate-700 dark:bg-slate-950">
                            <span class="min-w-0 truncate text-slate-700 dark:text-slate-200">{{ $disciplina->nome }}</span>
                            <span class="ml-auto shrink-0 text-xs text-slate-400">
                                {{ $disciplina->curso->nome }} · {{ $disciplina->periodo }}º
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('disciplinasSelecionadas.*')
                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            @endif
        </x-cartao>

        <div class="flex items-center justify-end gap-2">
            <x-botao variante="secundario" href="{{ route('usuarios.index') }}" wire:navigate>Cancelar</x-botao>
            <x-botao tipo="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="salvar">Salvar</span>
                <span wire:loading wire:target="salvar">Salvando…</span>
            </x-botao>
        </div>
    </form>
</div>
