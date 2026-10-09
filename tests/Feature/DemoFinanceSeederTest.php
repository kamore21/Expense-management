<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFinanceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_portfolio_is_account_scoped_and_idempotent(): void
    {
        $demoUser = User::factory()->create(['email' => 'admin@ledgerflow.test']);
        $otherUser = User::factory()->create();

        $this->seed(DemoFinanceSeeder::class);
        $expenseCount = $demoUser->expenses()->count();
        $invoiceCount = $demoUser->invoices()->count();
        $budgetCount = $demoUser->budgets()->count();

        $this->seed(DemoFinanceSeeder::class);

        $this->assertGreaterThan(0, $expenseCount);
        $this->assertGreaterThan(0, $invoiceCount);
        $this->assertGreaterThan(0, $budgetCount);
        $this->assertSame($expenseCount, $demoUser->expenses()->count());
        $this->assertSame($invoiceCount, $demoUser->invoices()->count());
        $this->assertSame($budgetCount, $demoUser->budgets()->count());
        $this->assertSame(0, $otherUser->expenses()->count());
        $this->assertSame(0, $otherUser->invoices()->count());
        $this->assertSame(0, $otherUser->budgets()->count());
    }

    public function test_local_database_seeder_provisions_a_verified_demo_account_and_portfolio(): void
    {
        $this->seed(DatabaseSeeder::class);
        $demoUser = User::query()->where('email', 'admin@ledgerflow.test')->firstOrFail();
        $expenseCount = $demoUser->expenses()->count();
        $invoiceCount = $demoUser->invoices()->count();
        $budgetCount = $demoUser->budgets()->count();

        $this->seed(DatabaseSeeder::class);

        $this->assertNotNull($demoUser->email_verified_at);
        $this->assertGreaterThan(0, $expenseCount);
        $this->assertGreaterThan(0, $invoiceCount);
        $this->assertGreaterThan(0, $budgetCount);
        $this->assertSame($expenseCount, $demoUser->expenses()->count());
        $this->assertSame($invoiceCount, $demoUser->invoices()->count());
        $this->assertSame($budgetCount, $demoUser->budgets()->count());
    }
}
