<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_user_can_list_own_categories(): void
    {
        Category::factory()->count(2)->create(['user_id' => $this->user->id]);
        Category::factory()->create(); // outro usuário

        $response = $this->withToken($this->token)
            ->getJson('/api/categories')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'parent_id', 'name', 'type', 'icon', 'color']]]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_categories_by_type(): void
    {
        Category::factory()->income()->create(['user_id' => $this->user->id]);
        Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/categories?type=income')
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('income', $response->json('data.0.type'));
    }

    public function test_user_can_create_category(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/categories', ['name' => 'Alimentação', 'type' => 'expense'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Alimentação');

        $this->assertDatabaseHas('categories', ['user_id' => $this->user->id, 'name' => 'Alimentação']);
    }

    public function test_user_can_create_subcategory(): void
    {
        $parent = Category::factory()->expense()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
            ->postJson('/api/categories', ['name' => 'Restaurante', 'type' => 'expense', 'parent_id' => $parent->id])
            ->assertStatus(201)
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_cannot_use_another_users_category_as_parent(): void
    {
        $foreignParent = Category::factory()->expense()->create();

        $this->withToken($this->token)
            ->postJson('/api/categories', ['name' => 'X', 'type' => 'expense', 'parent_id' => $foreignParent->id])
            ->assertStatus(422);
    }

    public function test_user_can_update_own_category(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id, 'name' => 'Antigo']);

        $this->withToken($this->token)
            ->patchJson("/api/categories/{$category->id}", ['name' => 'Novo'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Novo');
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
            ->patchJson("/api/categories/{$category->id}", ['parent_id' => $category->id])
            ->assertStatus(422);
    }

    public function test_user_cannot_update_another_users_category(): void
    {
        $other = Category::factory()->create();

        $this->withToken($this->token)
            ->patchJson("/api/categories/{$other->id}", ['name' => 'Hackeado'])
            ->assertStatus(404);
    }

    public function test_deleting_category_nullifies_linked_transactions_instead_of_cascading(): void
    {
        $category = Category::factory()->expense()->create(['user_id' => $this->user->id]);
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $transaction = Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $category->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/categories/{$category->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'category_id' => null]);
    }
}
