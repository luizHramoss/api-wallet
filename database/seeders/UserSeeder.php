<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Usuário administrador / demo ───────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@wallet.com'],
            [
                'name'     => 'Admin Demo',
                'password' => bcrypt('password'),
            ]
        );

        $adminWallet = Wallet::firstOrCreate(
            ['user_id' => $admin->id],
            ['balance' => 1500.00]
        );

        // Seed de transações para o admin
        $this->seedTransactions($adminWallet);

        // ─── Usuário comum de teste ──────────────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'user@wallet.com'],
            [
                'name'     => 'Usuário Teste',
                'password' => bcrypt('password'),
            ]
        );

        $userWallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 250.75]
        );

        $this->seedTransactions($userWallet);

        // ─── Usuários aleatórios ─────────────────────────────────────────────
        User::factory(5)->create()->each(function (User $u) {
            $wallet = Wallet::factory()->withBalance(fake()->randomFloat(2, 100, 3000))->create([
                'user_id' => $u->id,
            ]);
            $this->seedTransactions($wallet);
        });

        $this->command->info('✅  Seed concluído. Credenciais demo:');
        $this->command->line('   admin@wallet.com / password');
        $this->command->line('   user@wallet.com  / password');
    }

    private function seedTransactions(Wallet $wallet): void
    {
        $transactions = [
            ['type' => 'credit', 'amount' => 500.00,  'days_ago' => 30],
            ['type' => 'credit', 'amount' => 200.50,  'days_ago' => 25],
            ['type' => 'debit',  'amount' => 75.25,   'days_ago' => 20],
            ['type' => 'credit', 'amount' => 1000.00, 'days_ago' => 15],
            ['type' => 'debit',  'amount' => 300.00,  'days_ago' => 10],
            ['type' => 'credit', 'amount' => 150.00,  'days_ago' => 5],
            ['type' => 'debit',  'amount' => 50.00,   'days_ago' => 2],
        ];

        $runningBalance = 0.00;

        foreach ($transactions as $tx) {
            if ($tx['type'] === 'credit') {
                $runningBalance = bcadd((string) $runningBalance, (string) $tx['amount'], 2);
            } else {
                $runningBalance = bcsub((string) $runningBalance, (string) $tx['amount'], 2);
                if ($runningBalance < 0) {
                    $runningBalance = 0.00;
                }
            }

            Transaction::create([
                'wallet_id'    => $wallet->id,
                'type'         => $tx['type'],
                'amount'       => $tx['amount'],
                'balance_after' => $runningBalance,
                'description'  => $tx['type'] === 'credit' ? 'Depósito' : 'Saque',
                'created_at'   => now()->subDays($tx['days_ago']),
                'updated_at'   => now()->subDays($tx['days_ago']),
            ]);
        }
    }
}
