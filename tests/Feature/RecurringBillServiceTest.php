<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringBillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringBillServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecurringBillService $service;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RecurringBillService::class);
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);
    }

    private function makeBill(array $overrides = []): RecurringBill
    {
        return RecurringBill::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'day_of_month' => 10,
            'start_date' => Carbon::today()->subMonths(6)->startOfMonth(),
        ], $overrides));
    }

    public function test_generates_one_planned_occurrence_for_the_current_month(): void
    {
        $bill = $this->makeBill();

        $generated = $this->service->generateDueOccurrences();

        $this->assertGreaterThan(0, $generated);
        $this->assertDatabaseHas('transactions', [
            'recurring_bill_id' => $bill->id,
            'status' => Transaction::STATUS_PLANNED,
            'amount' => $bill->amount,
        ]);
    }

    public function test_does_not_backfill_months_before_today_for_an_old_start_date(): void
    {
        // start_date é 6 meses atrás, mas nunca foi gerado - não deve criar
        // 6 transações retroativas, só a do mês atual (+ eventualmente a do
        // próximo, dentro do horizonte de 1 mês).
        $this->makeBill();

        $this->service->generateDueOccurrences();

        $this->assertLessThanOrEqual(2, Transaction::count());
    }

    public function test_running_twice_does_not_duplicate_occurrences(): void
    {
        $this->makeBill();

        $first = $this->service->generateDueOccurrences();
        $second = $this->service->generateDueOccurrences();

        $this->assertGreaterThan(0, $first);
        $this->assertEquals(0, $second);
    }

    public function test_clamps_day_of_month_to_the_last_day_of_shorter_months(): void
    {
        $bill = $this->makeBill(['day_of_month' => 31]);

        $this->service->generateDueOccurrences();

        $transaction = Transaction::where('recurring_bill_id', $bill->id)
            ->orderBy('occurred_at')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertLessThanOrEqual(
            Carbon::parse($transaction->occurred_at)->daysInMonth,
            Carbon::parse($transaction->occurred_at)->day
        );
    }

    public function test_paused_bills_are_not_generated(): void
    {
        $this->makeBill(['status' => RecurringBill::STATUS_PAUSED]);

        $generated = $this->service->generateDueOccurrences();

        $this->assertEquals(0, $generated);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_stops_generating_after_end_date(): void
    {
        $bill = $this->makeBill([
            'start_date' => Carbon::today()->subMonths(3)->startOfMonth(),
            'end_date' => Carbon::today()->subMonths(2)->endOfMonth(),
        ]);

        $this->service->generateDueOccurrences();

        $this->assertDatabaseMissing('transactions', ['recurring_bill_id' => $bill->id]);
    }

    public function test_updates_last_generated_at_after_generating(): void
    {
        $bill = $this->makeBill();

        $this->service->generateDueOccurrences();

        $this->assertNotNull($bill->fresh()->last_generated_at);
    }

    public function test_income_type_bill_generates_income_transaction(): void
    {
        $bill = $this->makeBill(['type' => 'income', 'name' => 'Salário']);

        $this->service->generateDueOccurrences();

        $this->assertDatabaseHas('transactions', [
            'recurring_bill_id' => $bill->id,
            'type' => 'income',
        ]);
    }
}
