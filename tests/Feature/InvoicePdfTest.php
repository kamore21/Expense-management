<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_download_an_invoice_as_a_pdf(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $invoice = $user->invoices()->create([
            'invoice_number' => 'INV-1001',
            'client' => 'Acme Ltd',
            'amount' => 125.50,
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

        $response->assertDownload('INV-1001.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_user_cannot_download_another_users_invoice(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $invoice = $owner->invoices()->create([
            'invoice_number' => 'INV-PRIVATE',
            'client' => 'Private client',
            'amount' => 500,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

        $response->assertNotFound();
    }

    public function test_marking_an_invoice_paid_records_one_owner_scoped_payment_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 14:35:00'));
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $strangerUser = User::factory()->create();
        $invoice = $user->invoices()->create([
            'invoice_number' => 'INV-PAYMENT-1',
            'client' => 'Acme Ltd',
            'amount' => 125.50,
            'due_date' => '2026-10-15',
            'status' => 'pending',
        ]);
        $otherInvoice = $otherUser->invoices()->create([
            'invoice_number' => 'INV-OTHER-1',
            'client' => 'Other client',
            'amount' => 75,
            'due_date' => '2026-10-15',
            'status' => 'pending',
        ]);

        $invoiceView = $this->actingAs($user)->get(route('dashboard', ['view' => 'invoices']));
        $invoiceView->assertOk()
            ->assertSee('Mark paid')
            ->assertSee(route('invoices.mark-paid', ['invoice' => $invoice->id], false), false);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->patch(route('invoices.mark-paid', $invoice))
            ->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'user_id' => $user->id,
            'status' => 'paid',
            'paid_at' => '2026-10-09 14:35:00',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00'));
        $this->from(route('dashboard'))
            ->patch(route('invoices.mark-paid', $invoice))
            ->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_at' => '2026-10-09 14:35:00',
        ]);

        $this->get(route('dashboard', ['view' => 'invoices']))
            ->assertSee('Paid Oct 9, 2026')
            ->assertDontSee('Mark paid');

        $this->actingAs($strangerUser)
            ->patch(route('invoices.mark-paid', $otherInvoice))
            ->assertNotFound();
        $this->assertDatabaseHas('invoices', [
            'id' => $otherInvoice->id,
            'user_id' => $otherUser->id,
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }
}
