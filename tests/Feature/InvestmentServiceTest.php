<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InsufficientQuantityException;
use App\Models\Account;
use App\Models\Investment;
use App\Models\InvestmentMovement;
use App\Models\User;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvestmentService $service;

    private User $user;

    private Account $account;

    private Investment $investment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InvestmentService::class);
        $this->user = User::factory()->create();
        $this->account = Account::factory()->checking()->withBalance(10000)->create(['user_id' => $this->user->id]);
        $this->investment = Investment::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
    }

    public function test_buy_increases_quantity_and_sets_average_price(): void
    {
        $this->service->recordMovement($this->investment, [
            'type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20,
        ]);

        $fresh = $this->investment->fresh();
        $this->assertEquals('10.00000000', $fresh->quantity);
        $this->assertEquals('20.0000', $fresh->average_price);
    }

    public function test_buy_debits_the_linked_account(): void
    {
        $this->service->recordMovement($this->investment, [
            'type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20,
        ]);

        // 10000 - (10 * 20) = 9800
        $this->assertEquals('9800.00', $this->account->fresh()->balance);
    }

    public function test_second_buy_recalculates_weighted_average_price(): void
    {
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20]);
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 40]);

        // (10*20 + 10*40) / 20 = 30
        $fresh = $this->investment->fresh();
        $this->assertEquals('20.00000000', $fresh->quantity);
        $this->assertEquals('30.0000', $fresh->average_price);
    }

    public function test_sell_reduces_quantity_without_changing_average_price(): void
    {
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20]);
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_SELL, 'quantity' => 4, 'price' => 25]);

        $fresh = $this->investment->fresh();
        $this->assertEquals('6.00000000', $fresh->quantity);
        $this->assertEquals('20.0000', $fresh->average_price);
    }

    public function test_sell_credits_the_linked_account(): void
    {
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20]);
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_SELL, 'quantity' => 4, 'price' => 25]);

        // 9800 + (4 * 25) = 9900
        $this->assertEquals('9900.00', $this->account->fresh()->balance);
    }

    public function test_selling_more_than_owned_throws_exception(): void
    {
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20]);

        $this->expectException(InsufficientQuantityException::class);

        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_SELL, 'quantity' => 11, 'price' => 25]);
    }

    public function test_buy_fails_when_account_balance_is_insufficient(): void
    {
        $this->expectException(InsufficientBalanceException::class);

        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 1000, 'price' => 20]);
    }

    public function test_dividend_credits_account_and_does_not_change_position(): void
    {
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20]);
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_DIVIDEND, 'amount' => 50]);

        $fresh = $this->investment->fresh();
        $this->assertEquals('10.00000000', $fresh->quantity);
        $this->assertEquals('9850.00', $this->account->fresh()->balance);
    }

    public function test_portfolio_summary_totals_invested_and_current_value(): void
    {
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20]);
        $this->investment->fresh()->update(['current_price' => 25]);

        $summary = $this->service->portfolioSummary($this->user);

        $this->assertEquals(200.0, $summary['total_invested']);
        $this->assertEquals(250.0, $summary['total_current_value']);
        $this->assertEquals(25.0, $summary['rentability_percent']);
    }

    public function test_portfolio_summary_invested_this_month_only_counts_current_month(): void
    {
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20]);

        InvestmentMovement::factory()->buy()->create([
            'investment_id' => $this->investment->id,
            'quantity' => 5,
            'price' => 10,
            'amount' => 50,
            'occurred_at' => Carbon::now()->subMonths(2),
        ]);

        $summary = $this->service->portfolioSummary($this->user);

        $this->assertEquals(200.0, $summary['invested_this_month']);
    }

    public function test_portfolio_summary_dividends_this_month(): void
    {
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_BUY, 'quantity' => 10, 'price' => 20]);
        $this->service->recordMovement($this->investment, ['type' => InvestmentMovement::TYPE_DIVIDEND, 'amount' => 15]);

        $summary = $this->service->portfolioSummary($this->user);

        $this->assertEquals(15.0, $summary['dividends_this_month']);
    }
}
