<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_only_the_signed_in_users_records_for_the_selected_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00:00'));
        $user = User::factory()->create(['currency_code' => 'USD']);
        $otherUser = User::factory()->create();

        $user->expenses()->create([
            'title' => 'Current software expense',
            'category' => 'Software',
            'amount' => 75,
            'date' => '2026-10-08',
            'status' => 'paid',
        ]);
        $otherUser->expenses()->create([
            'title' => 'Private client expense',
            'category' => 'Private',
            'amount' => 900,
            'date' => '2026-10-08',
            'status' => 'paid',
        ]);
        $user->invoices()->create([
            'invoice_number' => 'INV-OWN-1',
            'client' => 'Local client',
            'amount' => 300,
            'due_date' => '2026-10-10',
            'status' => 'pending',
        ]);
        $user->invoices()->create([
            'invoice_number' => 'INV-OLD-1',
            'client' => 'Late client',
            'amount' => 50,
            'due_date' => '2026-09-20',
            'status' => 'pending',
        ]);
        $user->expenses()->create([
            'title' => 'September software expense',
            'category' => 'Software',
            'amount' => 400,
            'date' => '2026-09-30',
            'status' => 'paid',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', ['view' => 'activity']));

        $response->assertOk()
            ->assertSee('Current software expense')
            ->assertDontSee('Private client expense')
            ->assertDontSee('September software expense')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['totalExpenses'] === 75.0
                && $summary['totalInvoices'] === 300.0
                && $summary['netBalance'] === 225.0
                && $summary['outstandingInvoices'] === 350.0
                && $summary['overdueBills'] === 1
                && $summary['pendingInvoices'] === 2
            );
    }

    public function test_expense_creation_and_editing_preserve_the_authenticated_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('expenses.store'), [
                'title' => 'Cloud hosting',
                'category' => 'Infrastructure',
                'amount' => 48.50,
                'date' => '2026-10-08',
                'status' => 'pending',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'title' => 'Cloud hosting',
            'amount' => 48.50,
        ]);

        $expense = $user->expenses()->where('title', 'Cloud hosting')->firstOrFail();
        $this->actingAs($otherUser)
            ->patch(route('expenses.update', $expense), [
                'title' => 'Stolen edit',
                'category' => 'Other',
                'amount' => 1,
                'date' => '2026-10-08',
                'status' => 'paid',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'user_id' => $user->id,
            'title' => 'Cloud hosting',
            'amount' => 48.50,
        ]);
    }

    public function test_csv_export_is_normalized_and_contains_only_the_signed_in_users_records(): void
    {
        $user = User::factory()->create(['currency_code' => 'USD']);
        $otherUser = User::factory()->create();
        $user->expenses()->create([
            'title' => 'Office supplies',
            'category' => 'Operations',
            'amount' => 35.75,
            'date' => '2026-10-08',
            'status' => 'pending',
        ]);
        $user->invoices()->create([
            'invoice_number' => 'INV-OWN-1',
            'client' => 'Local client',
            'amount' => 90,
            'due_date' => '2026-10-12',
            'status' => 'pending',
        ]);
        $otherUser->expenses()->create([
            'title' => 'Private expense',
            'category' => 'Private',
            'amount' => 999,
            'date' => '2026-10-08',
            'status' => 'paid',
        ]);

        $response = $this->actingAs($user)->get(route('csv.export', ['type' => 'all']));

        $response->assertDownload('all-export.csv')
            ->assertStreamedContent(
                "type,title,category,invoice_number,client,amount,currency,date,due_date,status\n"
                ."expense,\"Office supplies\",Operations,,,35.75,USD,2026-10-08,,pending\n"
                ."invoice,,,INV-OWN-1,\"Local client\",90.00,USD,,2026-10-12,pending\n"
            );
    }

    public function test_invoice_creation_and_editing_are_bound_to_the_signed_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('invoices.store'), [
                'invoice_number' => 'INV-NEW-1',
                'client' => 'Northstar Studio',
                'amount' => 640,
                'due_date' => '2026-10-22',
                'status' => 'pending',
            ])
            ->assertRedirect(route('dashboard'));

        $invoice = $user->invoices()->where('invoice_number', 'INV-NEW-1')->firstOrFail();
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'user_id' => $user->id,
            'amount' => 640,
        ]);

        $this->patch(route('invoices.update', $invoice), [
            'invoice_number' => 'INV-NEW-1',
            'client' => 'Northstar Studio',
            'amount' => 640,
            'due_date' => '2026-10-22',
            'status' => 'paid',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'user_id' => $user->id,
            'status' => 'paid',
        ]);
    }

    public function test_csv_import_creates_valid_records_for_the_signed_in_user_and_skips_invalid_rows(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'expenses.csv',
            "title,category,amount,currency,date,status\nCloud storage,Operations,19.95,USD,2026-10-08,pending\nBroken row,Operations,not-a-number,USD,2026-10-08,pending\nForeign charge,Operations,50,EUR,2026-10-08,pending\n",
        );

        $response = $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('csv.import'), [
                'type' => 'expenses',
                'csv_file' => $file,
            ]);

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Imported 1 records. Skipped 2 invalid rows.');
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'title' => 'Cloud storage',
            'amount' => 19.95,
        ]);
        $this->assertDatabaseMissing('expenses', ['title' => 'Broken row']);
        $this->assertDatabaseMissing('expenses', ['title' => 'Foreign charge']);
    }

    public function test_dashboard_builds_decision_signals_from_owned_finance_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00:00'));
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->expenses()->createMany([
            ['title' => 'Core software', 'category' => 'Operations', 'amount' => 100, 'date' => '2026-10-08', 'status' => 'paid'],
            ['title' => 'Team tools', 'category' => 'Operations', 'amount' => 40, 'date' => '2026-10-10', 'status' => 'pending'],
            ['title' => 'Campaign', 'category' => 'Marketing', 'amount' => 50, 'date' => '2026-10-08', 'status' => 'paid'],
        ]);
        $otherUser->expenses()->create([
            'title' => 'Private software',
            'category' => 'Operations',
            'amount' => 900,
            'date' => '2026-10-08',
            'status' => 'pending',
        ]);

        $user->invoices()->createMany([
            ['invoice_number' => 'INV-CURRENT', 'client' => 'Client A', 'amount' => 300, 'due_date' => '2026-10-18', 'status' => 'pending'],
            ['invoice_number' => 'INV-RECENT-OVERDUE', 'client' => 'Client B', 'amount' => 100, 'due_date' => '2026-09-28', 'status' => 'pending'],
            ['invoice_number' => 'INV-MID-OVERDUE', 'client' => 'Client B', 'amount' => 80, 'due_date' => '2026-08-20', 'status' => 'pending'],
            ['invoice_number' => 'INV-OLD-OVERDUE', 'client' => 'Client B', 'amount' => 60, 'due_date' => '2026-06-01', 'status' => 'pending'],
            ['invoice_number' => 'INV-PAID', 'client' => 'Client C', 'amount' => 200, 'due_date' => '2026-10-12', 'status' => 'paid'],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertViewHas('decisionBrief', function (array $brief): bool {
            return $brief['receivables']['outstanding'] === 540.0
                && $brief['receivables']['dueSoon']['amount'] === 300.0
                && $brief['receivables']['aging']['current']['amount'] === 300.0
                && $brief['receivables']['aging']['days_1_30']['amount'] === 100.0
                && $brief['receivables']['aging']['days_31_60']['amount'] === 80.0
                && $brief['receivables']['aging']['over_60']['amount'] === 60.0
                && $brief['approvals']['count'] === 1
                && $brief['approvals']['amount'] === 40.0
                && $brief['spend']['topCategory']['name'] === 'Operations'
                && $brief['spend']['topCategory']['amount'] === 140.0
                && $brief['clients']['top']['name'] === 'Client A'
                && $brief['clients']['top']['share'] === 55.6;
        });
    }

    public function test_monthly_budget_saves_update_per_user_without_duplicate_categories(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('budgets.store'), [
                'category' => 'Operations',
                'monthly_limit' => 1000,
            ])
            ->assertRedirect(route('dashboard'));

        $this->post(route('budgets.store'), [
            'category' => 'Operations',
            'monthly_limit' => 1500,
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($otherUser)
            ->post(route('budgets.store'), [
                'category' => 'Operations',
                'monthly_limit' => 600,
            ])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('budgets.store'), [
                'category' => 'Invalid cap',
                'monthly_limit' => 0,
            ])
            ->assertInvalid(['monthly_limit']);

        $this->assertDatabaseCount('budgets', 2);
        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category' => 'Operations',
            'monthly_limit' => 1500,
        ]);
        $this->assertDatabaseHas('budgets', [
            'user_id' => $otherUser->id,
            'category' => 'Operations',
            'monthly_limit' => 600,
        ]);
    }

    public function test_dashboard_calculates_monthly_budget_utilization_and_overrun(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00:00'));
        $user = User::factory()->create();
        $user->expenses()->create([
            'title' => 'Logistics contract',
            'category' => 'Operations',
            'amount' => 1125,
            'date' => '2026-10-04',
            'status' => 'paid',
        ]);
        $user->budgets()->create([
            'category' => 'Operations',
            'monthly_limit' => 1000,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertViewHas('budgetRows', fn ($budgetRows): bool => $budgetRows->contains(fn (array $budget): bool => $budget['category'] === 'Operations'
                && $budget['limit'] === 1000.0
                && $budget['actual'] === 1125.0
                && $budget['percent'] === 112.5
                && $budget['state'] === 'over'
        )
        );
    }

    public function test_dashboard_navigation_selects_the_requested_view(): void
    {
        $user = User::factory()->create();
        $user->expenses()->create([
            'title' => 'Activity only expense',
            'category' => 'Operations',
            'amount' => 25,
            'date' => today()->toDateString(),
            'status' => 'paid',
        ]);
        $user->invoices()->create([
            'invoice_number' => 'NAV-INV-1',
            'client' => 'Navigation client',
            'amount' => 75,
            'due_date' => today()->toDateString(),
            'status' => 'pending',
        ]);

        $overview = $this->actingAs($user)->get(route('dashboard'));
        $overview->assertOk()
            ->assertSee(route('dashboard', ['view' => 'invoices'], false), false)
            ->assertSee(route('dashboard', ['view' => 'reports'], false), false);

        $intelligence = $this->actingAs($user)->get(route('dashboard', ['view' => 'intelligence']));
        $intelligence->assertOk()
            ->assertSee('Decision brief')
            ->assertDontSee('Budget control')
            ->assertSee(route('dashboard', ['view' => 'intelligence'], false), false)
            ->assertViewHas('view', 'intelligence');

        $budgets = $this->get(route('dashboard', ['view' => 'budgets']));
        $budgets->assertOk()
            ->assertSee('Budget control')
            ->assertDontSee('Decision brief')
            ->assertViewHas('view', 'budgets');

        $reports = $this->get(route('dashboard', ['view' => 'reports']));
        $reports->assertOk()
            ->assertSee('Cashflow rhythm')
            ->assertDontSee('Budget control')
            ->assertViewHas('view', 'reports');

        $activity = $this->get(route('dashboard', ['view' => 'activity']));
        $activity->assertOk()
            ->assertSee('Activity only expense')
            ->assertSee('NAV-INV-1')
            ->assertViewHas('view', 'activity');

        $invoices = $this->get(route('dashboard', ['view' => 'invoices']));
        $invoices->assertOk()
            ->assertSee('Invoice management')
            ->assertSee('NAV-INV-1')
            ->assertDontSee('Activity only expense')
            ->assertViewHas('view', 'invoices');
    }
}
