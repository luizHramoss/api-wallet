<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Usuário administrador / demo ───────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@wallet.com'],
            [
                'name' => 'Admin Demo',
                'password' => bcrypt('password'),
            ]
        );

        $adminAccount = Account::firstOrCreate(
            ['user_id' => $admin->id, 'name' => 'Conta Principal'],
            ['type' => Account::TYPE_CHECKING, 'balance' => 1500.00]
        );

        $adminCategories = $this->defaultCategories($admin);
        $this->seedTransactions($adminAccount, $adminCategories);
        $this->seedRecurringBills($admin, $adminAccount, $adminCategories);

        // ─── Usuário comum de teste ──────────────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'user@wallet.com'],
            [
                'name' => 'Usuário Teste',
                'password' => bcrypt('password'),
            ]
        );

        $userAccount = Account::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Conta Principal'],
            ['type' => Account::TYPE_CHECKING, 'balance' => 250.75]
        );

        $this->seedTransactions($userAccount, $this->defaultCategories($user));

        // ─── Usuários aleatórios ─────────────────────────────────────────────
        User::factory(5)->create()->each(function (User $u) {
            $account = Account::factory()->checking()->withBalance(fake()->randomFloat(2, 100, 3000))->create([
                'user_id' => $u->id,
            ]);
            $this->seedTransactions($account, $this->defaultCategories($u));
        });

        $this->command->info('✅  Seed concluído. Credenciais demo:');
        $this->command->line('   admin@wallet.com / password');
        $this->command->line('   user@wallet.com  / password');
    }

    /**
     * @return array{income: Category, expense: Category}
     */
    private function defaultCategories(User $user): array
    {
        return [
            'income' => Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => 'Salário', 'type' => Category::TYPE_INCOME]
            ),
            'expense' => Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => 'Geral', 'type' => Category::TYPE_EXPENSE]
            ),
        ];
    }

    /**
     * @param  array{income: Category, expense: Category}  $categories
     */
    private function seedTransactions(Account $account, array $categories): void
    {
        $transactions = [
            ['type' => Transaction::TYPE_INCOME, 'amount' => 500.00, 'days_ago' => 30],
            ['type' => Transaction::TYPE_INCOME, 'amount' => 200.50, 'days_ago' => 25],
            ['type' => Transaction::TYPE_EXPENSE, 'amount' => 75.25, 'days_ago' => 20],
            ['type' => Transaction::TYPE_INCOME, 'amount' => 1000.00, 'days_ago' => 15],
            ['type' => Transaction::TYPE_EXPENSE, 'amount' => 300.00, 'days_ago' => 10],
            ['type' => Transaction::TYPE_INCOME, 'amount' => 150.00, 'days_ago' => 5],
            ['type' => Transaction::TYPE_EXPENSE, 'amount' => 50.00, 'days_ago' => 2],
        ];

        foreach ($transactions as $tx) {
            Transaction::create([
                'account_id' => $account->id,
                'category_id' => $tx['type'] === Transaction::TYPE_INCOME
                    ? $categories['income']->id
                    : $categories['expense']->id,
                'type' => $tx['type'],
                'status' => Transaction::STATUS_REALIZED,
                'amount' => $tx['amount'],
                'description' => $tx['type'] === Transaction::TYPE_INCOME ? 'Depósito' : 'Saque',
                'occurred_at' => now()->subDays($tx['days_ago'])->toDateString(),
                'created_at' => now()->subDays($tx['days_ago']),
                'updated_at' => now()->subDays($tx['days_ago']),
            ]);
        }
    }

    /**
     * @param  array{income: Category, expense: Category}  $categories
     */
    private function seedRecurringBills(User $user, Account $account, array $categories): void
    {
        $bills = [
            ['name' => 'Aluguel', 'amount' => 1200.00, 'day_of_month' => 5],
            ['name' => 'Internet', 'amount' => 99.90, 'day_of_month' => 10],
            ['name' => 'Academia', 'amount' => 89.90, 'day_of_month' => 15],
        ];

        foreach ($bills as $bill) {
            RecurringBill::firstOrCreate(
                ['user_id' => $user->id, 'name' => $bill['name']],
                [
                    'account_id' => $account->id,
                    'category_id' => $categories['expense']->id,
                    'type' => Transaction::TYPE_EXPENSE,
                    'amount' => $bill['amount'],
                    'day_of_month' => $bill['day_of_month'],
                    'start_date' => now()->subMonths(3)->startOfMonth()->toDateString(),
                    'status' => RecurringBill::STATUS_ACTIVE,
                ]
            );
        }
    }
}
