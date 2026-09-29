{{-- A tabela de alunos como ela aparece na pré-visualização, com nomes de exemplo. --}}
<table class="alunos w-full border-collapse text-xs">
    <thead>
        <tr class="border-b border-slate-300 text-left dark:border-slate-700">
            <th class="py-1 pr-2">Nº</th>
            <th class="py-1 pr-2">Aluno</th>
            <th class="py-1 pr-2">RA</th>
            <th class="py-1">Assinatura</th>
        </tr>
    </thead>
    <tbody>
        @foreach ([['Caio Prado', '20261002'], ['Marina Alves de Souza', '20261001'], ['Rita Souza', '20261003']] as $indice => [$nome, $ra])
            <tr class="border-b border-slate-100 dark:border-slate-800">
                <td class="py-1 pr-2 tabular-nums">{{ $indice + 1 }}</td>
                <td class="py-1 pr-2">{{ $nome }}</td>
                <td class="py-1 pr-2 tabular-nums">{{ $ra }}</td>
                <td class="py-1"></td>
            </tr>
        @endforeach
    </tbody>
</table>
