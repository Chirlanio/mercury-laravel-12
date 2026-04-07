<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\StoreGoal;
use App\Models\ConsultantGoal;
use App\Models\PercentageAward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StoreGoalControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected int $storeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->storeId = $this->createTestStore('Z424');

        // Seed percentage awards
        PercentageAward::firstOrCreate(['level' => 'Júnior'], [
            'no_goal_pct' => 1.00, 'goal_pct' => 2.00, 'super_goal_pct' => 2.50, 'hiper_goal_pct' => 3.00,
        ]);
        PercentageAward::firstOrCreate(['level' => 'Pleno'], [
            'no_goal_pct' => 1.50, 'goal_pct' => 2.50, 'super_goal_pct' => 3.00, 'hiper_goal_pct' => 3.50,
        ]);
        PercentageAward::firstOrCreate(['level' => 'Sênior'], [
            'no_goal_pct' => 2.00, 'goal_pct' => 3.00, 'super_goal_pct' => 3.50, 'hiper_goal_pct' => 4.00,
        ]);
    }

    private function createGoal(array $overrides = []): StoreGoal
    {
        return StoreGoal::create(array_merge([
            'store_id' => $this->storeId,
            'reference_month' => 4,
            'reference_year' => 2026,
            'goal_amount' => 100000.00,
            'super_goal' => 115000.00,
            'business_days' => 26,
            'non_working_days' => 0,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    // ============================
    // Index
    // ============================

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/store-goals');
        $response->assertRedirect('/login');
    }

    public function test_index_displays_for_admin(): void
    {
        $this->createGoal();

        $response = $this->actingAs($this->adminUser)->get('/store-goals?month=4&year=2026');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('StoreGoals/Index')
                ->has('goals', 1)
                ->has('stores')
                ->has('filters')
        );
    }

    public function test_index_blocked_for_regular_user(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/store-goals');
        $response->assertStatus(403);
    }

    public function test_index_filters_by_month_and_year(): void
    {
        $this->createGoal(['reference_month' => 4, 'reference_year' => 2026]);
        $this->createGoal(['reference_month' => 5, 'reference_year' => 2026]);

        $response = $this->actingAs($this->adminUser)->get('/store-goals?month=4&year=2026');
        $response->assertInertia(fn ($page) => $page->has('goals', 1));
    }

    // ============================
    // Store
    // ============================

    public function test_goal_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/store-goals', [
            'store_id' => $this->storeId,
            'reference_month' => 6,
            'reference_year' => 2026,
            'goal_amount' => 150000,
            'business_days' => 25,
            'non_working_days' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('store_goals', [
            'store_id' => $this->storeId,
            'reference_month' => 6,
            'reference_year' => 2026,
            'goal_amount' => 150000.00,
        ]);
    }

    public function test_super_goal_auto_calculated(): void
    {
        $this->actingAs($this->adminUser)->post('/store-goals', [
            'store_id' => $this->storeId,
            'reference_month' => 6,
            'reference_year' => 2026,
            'goal_amount' => 100000,
            'business_days' => 26,
        ]);

        $goal = StoreGoal::first();
        $this->assertEquals(115000.00, (float) $goal->super_goal);
    }

    public function test_duplicate_goal_rejected(): void
    {
        $this->createGoal(['reference_month' => 4, 'reference_year' => 2026]);

        $response = $this->actingAs($this->adminUser)->post('/store-goals', [
            'store_id' => $this->storeId,
            'reference_month' => 4,
            'reference_year' => 2026,
            'goal_amount' => 200000,
            'business_days' => 26,
        ]);

        $response->assertSessionHasErrors('store_id');
        $this->assertCount(1, StoreGoal::all());
    }

    public function test_create_blocked_for_support(): void
    {
        $response = $this->actingAs($this->supportUser)->post('/store-goals', [
            'store_id' => $this->storeId,
            'reference_month' => 6,
            'reference_year' => 2026,
            'goal_amount' => 100000,
            'business_days' => 26,
        ]);

        $response->assertStatus(403);
    }

    // ============================
    // Show
    // ============================

    public function test_show_returns_goal_data(): void
    {
        $goal = $this->createGoal();

        $response = $this->actingAs($this->adminUser)->getJson("/store-goals/{$goal->id}");
        $response->assertOk();
        $response->assertJsonStructure([
            'id', 'store_name', 'goal_amount', 'super_goal',
            'business_days', 'total_sales', 'achievement_pct', 'consultants',
        ]);
    }

    // ============================
    // Update
    // ============================

    public function test_goal_can_be_updated(): void
    {
        $goal = $this->createGoal();

        $response = $this->actingAs($this->adminUser)->put("/store-goals/{$goal->id}", [
            'goal_amount' => 200000,
            'business_days' => 24,
            'non_working_days' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $goal->refresh();
        $this->assertEquals(200000.00, (float) $goal->goal_amount);
        $this->assertEquals(230000.00, (float) $goal->super_goal);
        $this->assertEquals(24, $goal->business_days);
    }

    // ============================
    // Destroy
    // ============================

    public function test_goal_can_be_deleted(): void
    {
        $goal = $this->createGoal();

        $response = $this->actingAs($this->adminUser)->delete("/store-goals/{$goal->id}");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('store_goals', ['id' => $goal->id]);
    }

    public function test_delete_cascades_consultant_goals(): void
    {
        $goal = $this->createGoal();

        // Manually create a consultant goal
        ConsultantGoal::create([
            'store_goal_id' => $goal->id,
            'employee_id' => $this->createTestEmployee(),
            'reference_month' => 4,
            'reference_year' => 2026,
            'working_days' => 26,
            'business_days' => 26,
            'deducted_days' => 0,
            'individual_goal' => 50000,
            'super_goal' => 57500,
            'hiper_goal' => 66125,
            'level_snapshot' => 'Pleno',
            'weight' => 1.00,
        ]);

        $this->assertCount(1, ConsultantGoal::all());

        $this->actingAs($this->adminUser)->delete("/store-goals/{$goal->id}");

        $this->assertCount(0, ConsultantGoal::all());
    }

    public function test_delete_blocked_for_support(): void
    {
        $goal = $this->createGoal();

        $response = $this->actingAs($this->supportUser)->delete("/store-goals/{$goal->id}");
        $response->assertStatus(403);
    }

    // ============================
    // Statistics
    // ============================

    public function test_statistics_returns_json(): void
    {
        $this->createGoal();

        $response = $this->actingAs($this->adminUser)->getJson('/store-goals/statistics?month=4&year=2026');
        $response->assertOk();
        $response->assertJsonStructure([
            'total_goal_amount', 'total_sales', 'achievement_pct',
            'stores_with_goals', 'coverage_pct',
        ]);
    }

    // ============================
    // Validation
    // ============================

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/store-goals', []);

        $response->assertSessionHasErrors(['store_id', 'reference_month', 'reference_year', 'goal_amount', 'business_days']);
    }

    public function test_store_validates_goal_amount_positive(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/store-goals', [
            'store_id' => $this->storeId,
            'reference_month' => 6,
            'reference_year' => 2026,
            'goal_amount' => 0,
            'business_days' => 26,
        ]);

        $response->assertSessionHasErrors('goal_amount');
    }

    // ============================
    // Helpers
    // ============================

    private function createTestEmployee(array $overrides = []): int
    {
        static $counter = 0;
        $counter++;

        return DB::table('employees')->insertGetId(array_merge([
            'name' => "Test Employee {$counter}",
            'short_name' => "Test{$counter}",
            'cpf' => str_pad($counter, 11, '0', STR_PAD_LEFT),
            'gender_id' => 1,
            'education_level_id' => 1,
            'status_id' => 1,
            'position_id' => 1,
            'store_id' => 'Z424',
            'area_id' => 1,
            'level' => 'Pleno',
            'admission_date' => '2024-01-15',
            'birth_date' => '1995-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
