<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\RecurringBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringBillControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_user_can_list_own_recurring_bills(): void
    {
        RecurringBill::factory()->count(2)->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        RecurringBill::factory()->create(); // outro usuário

        $response = $this->withToken($this->token)
            ->getJson('/api/recurring-bills')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'account_id', 'category_id', 'name', 'type', 'amount', 'day_of_month', 'start_date', 'end_date', 'status', 'last_generated_at']],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_create_recurring_bill(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/recurring-bills', [
                'account_id' => $this->account->id,
                'name' => 'Aluguel',
                'type' => 'expense',
                'amount' => 1500.00,
                'day_of_month' => 5,
                'start_date' => now()->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Aluguel')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('recurring_bills', ['user_id' => $this->user->id, 'name' => 'Aluguel']);
    }

    public function test_create_requires_day_of_month_between_1_and_31(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/recurring-bills', [
                'account_id' => $this->account->id,
                'name' => 'X',
                'type' => 'expense',
                'amount' => 10,
                'day_of_month' => 32,
                'start_date' => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_user_can_update_own_recurring_bill(): void
    {
        $bill = RecurringBill::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);

        $this->withToken($this->token)
            ->patchJson("/api/recurring-bills/{$bill->id}", ['status' => 'paused'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paused');
    }

    public function test_user_cannot_update_another_users_recurring_bill(): void
    {
        $other = RecurringBill::factory()->create();

        $this->withToken($this->token)
            ->patchJson("/api/recurring-bills/{$other->id}", ['status' => 'paused'])
            ->assertStatus(404);
    }

    public function test_user_can_delete_own_recurring_bill(): void
    {
        $bill = RecurringBill::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/recurring-bills/{$bill->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('recurring_bills', ['id' => $bill->id]);
    }
}
