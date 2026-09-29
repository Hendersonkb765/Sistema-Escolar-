{{--
    A tabela que `{{ lista_de_alunos }}` vira no documento coletivo.

    Traz uma coluna de assinatura porque é para isso que a lista costuma
    circular. Quem não precisar dela ignora a coluna; quem precisar não
    teria como acrescentá-la sozinho.
--}}
<table class="alunos">
    <thead>
        <tr>
            <th class="numero">Nº</th>
            <th>Aluno</th>
            <th class="ra">RA</th>
            <th class="assinar">Assinatura</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($alunos as $indice => $aluno)
            <tr>
                <td class="numero">{{ $indice + 1 }}</td>
                <td>{{ $aluno->nome }}</td>
                <td class="ra">{{ $aluno->ra }}</td>
                <td class="assinar"></td>
            </tr>
        @endforeach
    </tbody>
</table>
