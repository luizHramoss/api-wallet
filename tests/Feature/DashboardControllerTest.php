<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->checking()->empty()->create(['user_id' => $this->user->id]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_dashboard_returns_correct_structure(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'balance',
                    'last_transactions',
                    'monthly_summary' => [
                        'total_income',
                        'total_expense',
                        'period' => ['from', 'to'],
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_view_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertStatus(401);
    }

    public function test_dashboard_monthly_totals_and_balance_aggregate_all_accounts(): void
    {
        $secondAccount = Account::factory()->checking()->empty()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)->postJson('/api/transactions', [
            'account_id' => $this->account->id, 'type' => 'income', 'amount' => 500.00,
        ]);
        $this->withToken($this->token)->postJson('/api/transactions', [
            'account_id' => $secondAccount->id, 'type' => 'income', 'amount' => 300.00,
        ]);
        $this->withToken($this->token)->postJson('/api/transactions', [
            'account_id' => $this->account->id, 'type' => 'expense', 'amount' => 200.00,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/dashboard')->assertStatus(200);

        $this->assertEquals(800.00, $response->json('data.monthly_summary.total_income'));
        $this->assertEquals(200.00, $response->json('data.monthly_summary.total_expense'));
        $this->assertEquals(600.00, $response->json('data.balance'));
    }

    public function test_dashboard_shows_at_most_five_transactions(): void
    {
        $this->withToken($this->token)->postJson('/api/transactions', [
            'account_id' => $this->account->id, 'type' => 'income', 'amount' => 1000.00,
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->withToken($this->token)->postJson('/api/transactions', [
                'account_id' => $this->account->id, 'type' => 'expense', 'amount' => 10.00,
            ]);
        }

        $response = $this->withToken($this->token)->getJson('/api/dashboard');
        $this->assertCount(5, $response->json('data.last_transactions'));
    }
}
