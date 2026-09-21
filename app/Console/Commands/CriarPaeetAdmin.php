<?php

namespace App\Console\Commands;

use App\Enums\PerfilUsuario;
use App\Models\Eixo;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Único caminho para criar a primeira conta do sistema. Como não existe
 * cadastro público, o PAEET Admin inicial nasce aqui, pelo terminal, por
 * quem já tem acesso ao servidor.
 */
class CriarPaeetAdmin extends Command
{
    protected $signature = 'usuario:criar-admin
                            {--nome= : Nome completo}
                            {--email= : E-mail de acesso}
                            {--senha= : Senha inicial}
                            {--eixo=* : IDs de Eixos a vincular}';

    protected $description = 'Cria uma conta PAEET Admin (não há cadastro público no sistema)';

    public function handle(): int
    {
        $nome = $this->option('nome') ?: text('Nome completo', required: true);
        $email = $this->option('email') ?: text('E-mail de acesso', required: true);
        $senha = $this->option('senha') ?: password('Senha inicial', required: true);

        $validador = Validator::make(
            ['nome' => $nome, 'email' => $email, 'senha' => $senha],
            [
                'nome' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:usuarios,email'],
                'senha' => ['required', Password::min(8)->letters()->numbers()],
            ],
            [],
            ['nome' => 'nome', 'email' => 'e-mail', 'senha' => 'senha']
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $eixoIds = array_map('intval', (array) $this->option('eixo'));

        if ($eixoIds === [] && Eixo::query()->exists() && $this->input->isInteractive()) {
            $eixoIds = multiselect(
                label: 'Vincular a quais Eixos?',
                options: Eixo::query()->orderBy('nome')->pluck('nome', 'id')->all(),
                hint: 'Sem vínculo, a conta não enxerga nenhum curso ou turma.'
            );
        }

        $usuario = DB::transaction(function () use ($nome, $email, $senha, $eixoIds) {
            $usuario = User::create([
                'nome' => $nome,
                'email' => $email,
                'password' => Hash::make($senha),
                'perfil' => PerfilUsuario::PaeetAdmin,
                'ativo' => true,
            ]);

            if ($eixoIds !== []) {
                $usuario->eixos()->sync($eixoIds);
            }

            return $usuario;
        });

        $this->components->info("PAEET Admin criado: {$usuario->email}");

        if ($eixoIds === []) {
            $this->components->warn('Conta sem Eixo vinculado — crie um Eixo e vincule-a para liberar o escopo.');
        }

        return self::SUCCESS;
    }
}
