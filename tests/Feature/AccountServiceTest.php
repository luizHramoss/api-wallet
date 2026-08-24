<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AccountService::class);
    }

    public function test_create_default_for_user_sets_zero_balance(): void
    {
        $user = User::factory()->create();
        $account = $this->service->createDefaultForUser($user);

        $this->assertEquals('0.00', $account->balance);
        $this->assertEquals($user->id, $account->user_id);
        $this->assertEquals('checking', $account->type);
    }

    public function test_credit_increases_balance(): void
    {
        $user = User::factory()->create();
        $account = $this->service->createDefaultForUser($user);

        $this->service->credit($account, 150.50);

        $this->assertEquals('150.50', $account->fresh()->balance);
    }

    public function test_debit_decreases_balance(): void
    {
        $user = User::factory()->create();
        $account = $this->service->createDefaultForUser($user);
        $this->service->credit($account, 200.00);

        $this->service->debit($account, 80.00);

        $this->assertEquals('120.00', $account->fresh()->balance);
    }

    public function test_debit_throws_when_balance_is_insufficient(): void
    {
        $user = User::factory()->create();
        $account = $this->service->createDefaultForUser($user);

        $this->expectException(InsufficientBalanceException::class);

        $this->service->debit($account, 10.00);
    }
}
