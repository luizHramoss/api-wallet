<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_does_not_require_authentication(): void
    {
        $this->getJson('/api/metrics')->assertStatus(200);
    }

    public function test_metrics_endpoint_returns_prometheus_text_format(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->checking()->withBalance(100)->create(['user_id' => $user->id]);
        Transaction::factory()->credit()->create(['account_id' => $account->id, 'amount' => 100]);

        $response = $this->get('/api/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $body = $response->getContent();
        $this->assertStringContainsString('wallet_users_total 1', $body);
        $this->assertStringContainsString('wallet_accounts_total 1', $body);
        $this->assertStringContainsString('wallet_balance_total 100', $body);
        $this->assertStringContainsString('wallet_recurring_bills_active_total 0', $body);
        $this->assertStringContainsString('wallet_transactions_total{type="income"} 1', $body);
        $this->assertStringContainsString('wallet_transactions_total{type="expense"} 0', $body);
        $this->assertStringContainsString('wallet_transactions_total{type="transfer"} 0', $body);
    }
}
