<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::query()->where('email', 'admin@ledgerflow.test')->first();
        if ($user === null) {
            return;
        }

        $monthlyExpenses = [
            [11, 'Regional fulfilment', 'Operations', 16400, 'paid'],
            [10, 'Cloud platform', 'Technology', 4850, 'paid'],
            [9, 'Warehouse services', 'Operations', 18150, 'paid'],
            [8, 'Security and compliance', 'Professional services', 9200, 'paid'],
            [7, 'Regional fulfilment', 'Operations', 17300, 'paid'],
            [6, 'Cloud platform', 'Technology', 5120, 'paid'],
            [5, 'Customer implementation', 'Professional services', 11600, 'paid'],
            [4, 'Warehouse services', 'Operations', 19050, 'paid'],
            [3, 'Cloud platform', 'Technology', 5480, 'paid'],
            [2, 'Regional fulfilment', 'Operations', 18700, 'paid'],
            [1, 'Security and compliance', 'Professional services', 7800, 'paid'],
            [0, 'Cloud platform', 'Technology', 5890, 'paid'],
        ];

        foreach ($monthlyExpenses as [$monthsAgo, $title, $category, $amount, $status]) {
            $date = today()->startOfMonth()->subMonthsNoOverflow($monthsAgo)->addDays(7)->toDateString();
            $user->expenses()->firstOrCreate(
                ['title' => $title, 'date' => $date],
                ['category' => $category, 'amount' => $amount, 'status' => $status],
            );
        }

        $pendingExpenses = [
            ['title' => 'Quarterly security review', 'category' => 'Professional services', 'amount' => 12800, 'date' => today()->subDays(2)->toDateString()],
            ['title' => 'Regional equipment refresh', 'category' => 'Operations', 'amount' => 7400, 'date' => today()->subDay()->toDateString()],
        ];

        foreach ($pendingExpenses as $expense) {
            $user->expenses()->firstOrCreate(
                ['title' => $expense['title'], 'date' => $expense['date']],
                ['category' => $expense['category'], 'amount' => $expense['amount'], 'status' => 'pending'],
            );
        }

        $invoices = [
            ['invoice_number' => 'LF-DEMO-2601', 'client' => 'Asterion Systems', 'amount' => 56800, 'due_date' => today()->addDays(12)->toDateString(), 'status' => 'pending'],
            ['invoice_number' => 'LF-DEMO-2602', 'client' => 'Asterion Systems', 'amount' => 19400, 'due_date' => today()->subDays(11)->toDateString(), 'status' => 'pending'],
            ['invoice_number' => 'LF-DEMO-2603', 'client' => 'Cinder Logistics', 'amount' => 32500, 'due_date' => today()->subDays(42)->toDateString(), 'status' => 'pending'],
            ['invoice_number' => 'LF-DEMO-2604', 'client' => 'Cinder Logistics', 'amount' => 14200, 'due_date' => today()->subDays(78)->toDateString(), 'status' => 'overdue'],
            ['invoice_number' => 'LF-DEMO-2605', 'client' => 'Meridian Retail Group', 'amount' => 28750, 'due_date' => today()->subDays(20)->toDateString(), 'status' => 'paid'],
            ['invoice_number' => 'LF-DEMO-2606', 'client' => 'Northstar Manufacturing', 'amount' => 41100, 'due_date' => today()->subDays(45)->toDateString(), 'status' => 'paid'],
            ['invoice_number' => 'LF-DEMO-2607', 'client' => 'Asterion Systems', 'amount' => 17300, 'due_date' => today()->subDays(90)->toDateString(), 'status' => 'paid'],
            ['invoice_number' => 'LF-DEMO-2608', 'client' => 'Crescent Operations', 'amount' => 9600, 'due_date' => today()->addDays(28)->toDateString(), 'status' => 'pending'],
            ['invoice_number' => 'LF-DEMO-2609', 'client' => 'Crescent Operations', 'amount' => 12000, 'due_date' => today()->subDays(5)->toDateString(), 'status' => 'pending'],
        ];

        foreach ($invoices as $invoice) {
            $user->invoices()->firstOrCreate(
                ['invoice_number' => $invoice['invoice_number']],
                [
                    'client' => $invoice['client'],
                    'amount' => $invoice['amount'],
                    'due_date' => $invoice['due_date'],
                    'status' => $invoice['status'],
                ],
            );
        }

        $monthlyBudgets = [
            ['category' => 'Operations', 'monthly_limit' => 9000],
            ['category' => 'Technology', 'monthly_limit' => 7000],
            ['category' => 'Professional services', 'monthly_limit' => 10000],
        ];

        foreach ($monthlyBudgets as $budget) {
            $user->budgets()->firstOrCreate(
                ['category' => $budget['category']],
                ['monthly_limit' => $budget['monthly_limit']],
            );
        }
    }
}
