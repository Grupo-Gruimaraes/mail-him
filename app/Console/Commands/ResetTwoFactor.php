<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Console\Command;

class ResetTwoFactor extends Command
{
    protected $signature = 'two-factor:reset {email : E-mail do usuário}';

    protected $description = 'Desativa a verificação em duas etapas de um usuário que perdeu o acesso; ele cadastra outra no próximo login';

    public function handle(TwoFactor $twoFactor): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Usuário não encontrado.');

            return self::FAILURE;
        }

        $twoFactor->disable($user);
        $user->forceFill(['remember_token' => null])->save();
        $this->info("2FA de {$user->email} redefinido. No próximo login ele cadastra um novo método.");

        return self::SUCCESS;
    }
}
