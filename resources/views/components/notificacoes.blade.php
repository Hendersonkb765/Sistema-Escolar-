{{--
    Avisos flutuantes no canto da tela.

    Atende às duas origens: o `session()->flash()` que sobrevive a um
    redirecionamento, e o evento `notificar` que o Livewire dispara sem
    recarregar a página — é o caso de salvar um rascunho.
--}}
<div x-data="{
        avisos: [],
        proximoId: 1,
        adicionar(tipo, mensagem, titulo = null) {
            if (! mensagem) return;

            const id = this.proximoId++;
            this.avisos.push({ id, tipo, mensagem, titulo });

            setTimeout(() => this.remover(id), tipo === 'erro' ? 8000 : 4000);
        },
        remover(id) {
            this.avisos = this.avisos.filter((aviso) => aviso.id !== id);
        },
     }"
     x-on:notificar.window="adicionar($event.detail.tipo, $event.detail.mensagem, $event.detail.titulo)"
     x-init="
        @if (session('sucesso')) adicionar('sucesso', @js(session('sucesso'))); @endif
        @if (session('erro')) adicionar('erro', @js(session('erro'))); @endif
        @if (session('status')) adicionar('info', @js(session('status'))); @endif
     "
     class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-4 sm:items-end"
     role="status" aria-live="polite">

    <template x-for="aviso in avisos" :key="aviso.id">
        <div x-show="true"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl border shadow-lg"
             :class="{
                'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-950': aviso.tipo === 'sucesso',
                'border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-950': aviso.tipo === 'erro',
                'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-950': aviso.tipo === 'atencao',
                'border-sky-200 bg-sky-50 dark:border-sky-500/30 dark:bg-sky-950': aviso.tipo === 'info',
             }">
            <div class="flex items-start gap-3 p-3">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white"
                      :class="{
                        'bg-emerald-500': aviso.tipo === 'sucesso',
                        'bg-rose-500': aviso.tipo === 'erro',
                        'bg-amber-500': aviso.tipo === 'atencao',
                        'bg-sky-500': aviso.tipo === 'info',
                      }">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                        <path x-show="aviso.tipo === 'sucesso'" stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        <path x-show="aviso.tipo !== 'sucesso'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <p x-show="aviso.titulo" x-text="aviso.titulo"
                       class="text-sm font-semibold text-slate-900 dark:text-slate-100"></p>
                    <p x-text="aviso.mensagem" class="text-sm text-slate-700 dark:text-slate-200"></p>
                </div>

                <button type="button" x-on:click="remover(aviso.id)" aria-label="Fechar aviso"
                        class="shrink-0 rounded p-1 text-slate-400 transition hover:bg-black/5 hover:text-slate-600 dark:hover:bg-white/10">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </template>
</div>
