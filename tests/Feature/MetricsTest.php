<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
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
        $wallet = Wallet::factory()->withBalance(100)->create(['user_id' => $user->id]);
        Transaction::factory()->credit()->create(['wallet_id' => $wallet->id, 'amount' => 100, 'balance_after' => 100]);

        $response = $this->get('/api/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $body = $response->getContent();
        $this->assertStringContainsString('wallet_users_total 1', $body);
        $this->assertStringContainsString('wallet_wallets_total 1', $body);
        $this->assertStringContainsString('wallet_balance_total 100', $body);
        $this->assertStringContainsString('wallet_transactions_total{type="credit"} 1', $body);
        $this->assertStringContainsString('wallet_transactions_total{type="debit"} 0', $body);
    }
}
