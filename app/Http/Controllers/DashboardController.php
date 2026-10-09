<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $view = in_array($request->query('view'), ['overview', 'intelligence', 'budgets', 'activity', 'invoices', 'reports'], true)
            ? $request->query('view')
            : 'overview';
        $period = in_array($request->query('period'), ['month', 'quarter', 'year', 'all'], true)
            ? $request->query('period')
            : 'month';
        $type = $view === 'invoices'
            ? 'invoice'
            : (in_array($request->query('type'), ['all', 'expense', 'invoice'], true)
                ? $request->query('type')
                : 'all');
        $status = in_array($request->query('status'), ['all', 'open', 'pending', 'approved', 'paid', 'overdue'], true)
            ? $request->query('status')
            : 'all';
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);

        $today = CarbonImmutable::today($user->timezone);
        $regionalToday = $today;
        $periodStart = match ($period) {
            'month' => $today->startOfMonth(),
            'quarter' => $today->startOfQuarter(),
            'year' => $today->startOfYear(),
            default => null,
        };
        $periodEnd = match ($period) {
            'month' => $today->endOfMonth(),
            'quarter' => $today->endOfQuarter(),
            'year' => $today->endOfYear(),
            default => null,
        };
        $previousStart = match ($period) {
            'month' => $periodStart?->subMonth()->startOfMonth(),
            'quarter' => $periodStart?->subQuarter()->startOfQuarter(),
            'year' => $periodStart?->subYear()->startOfYear(),
            default => null,
        };
        $previousEnd = $periodStart?->subDay()->endOfDay();

        $expenseQuery = $this->applyDateRange(
            Expense::query()->whereBelongsTo($user),
            'date',
            $periodStart,
            $periodEnd,
        );
        $invoiceQuery = $this->applyDateRange(
            Invoice::query()->whereBelongsTo($user),
            'due_date',
            $periodStart,
            $periodEnd,
        );
        $openInvoiceQuery = Invoice::query()->whereBelongsTo($user)->where('status', '!=', 'paid');

        $summary = [
            'totalExpenses' => (float) (clone $expenseQuery)->sum('amount'),
            'totalInvoices' => (float) (clone $invoiceQuery)->sum('amount'),
            'paidInvoices' => (float) (clone $invoiceQuery)->where('status', 'paid')->sum('amount'),
            'pendingInvoices' => (clone $openInvoiceQuery)->count(),
            'overdueBills' => (clone $openInvoiceQuery)
                ->whereDate('due_date', '<', $today->toDateString())
                ->count(),
            'outstandingInvoices' => (float) (clone $openInvoiceQuery)->sum('amount'),
        ];
        $summary['netBalance'] = $summary['totalInvoices'] - $summary['totalExpenses'];

        $previousExpenseQuery = $previousStart
            ? $this->applyDateRange(Expense::query()->whereBelongsTo($user), 'date', $previousStart, $previousEnd)
            : null;
        $previousInvoiceQuery = $previousStart
            ? $this->applyDateRange(Invoice::query()->whereBelongsTo($user), 'due_date', $previousStart, $previousEnd)
            : null;
        $summary['expenseTrend'] = $previousExpenseQuery
            ? $this->percentageChange($summary['totalExpenses'], (float) (clone $previousExpenseQuery)->sum('amount'))
            : null;
        $summary['invoiceTrend'] = $previousInvoiceQuery
            ? $this->percentageChange($summary['totalInvoices'], (float) (clone $previousInvoiceQuery)->sum('amount'))
            : null;

        $budgetActuals = $user->expenses()
            ->whereBetween('date', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()])
            ->select('category')
            ->selectRaw('SUM(amount) as actual_amount')
            ->groupBy('category')
            ->pluck('actual_amount', 'category');
        $budgetRows = $user->budgets()
            ->orderBy('category')
            ->get()
            ->map(function (Budget $budget) use ($budgetActuals): array {
                $limit = (float) $budget->monthly_limit;
                $actual = (float) ($budgetActuals->get($budget->category) ?? 0);
                $percent = $limit > 0 ? round(($actual / $limit) * 100, 1) : 0.0;

                return [
                    'id' => $budget->id,
                    'category' => $budget->category,
                    'limit' => $limit,
                    'actual' => $actual,
                    'remaining' => max(0, $limit - $actual),
                    'over_by' => max(0, $actual - $limit),
                    'percent' => $percent,
                    'state' => $actual > $limit ? 'over' : ($percent >= 80 ? 'watch' : 'on-track'),
                ];
            });
        $budgetedCategories = $budgetRows->pluck('category')->all();
        $budgetSummary = [
            'limit' => $budgetRows->sum('limit'),
            'actual' => $budgetRows->sum('actual'),
            'remaining' => $budgetRows->sum('remaining'),
            'over_count' => $budgetRows->where('state', 'over')->count(),
            'watch_count' => $budgetRows->where('state', 'watch')->count(),
            'unbudgeted_spend' => $budgetActuals
                ->reject(fn ($amount, $category) => in_array($category, $budgetedCategories, true))
                ->sum(),
        ];

        $openInvoices = Invoice::query()->whereBelongsTo($user)->where('status', '!=', 'paid');
        $aging = [
            'current' => $this->aggregateInvoices(clone $openInvoices, $today, null),
            'days_1_30' => $this->aggregateInvoices(clone $openInvoices, $today->subDays(30), $today->subDay()),
            'days_31_60' => $this->aggregateInvoices(clone $openInvoices, $today->subDays(60), $today->subDays(31)),
            'over_60' => $this->aggregateInvoices(clone $openInvoices, null, $today->subDays(61)),
        ];
        $approvalQuery = clone $expenseQuery;
        $decisionApprovals = $this->aggregateExpenses($approvalQuery->where('status', 'pending'));
        $categoryTotals = (clone $expenseQuery)
            ->select('category')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();
        $spendCategories = $categoryTotals->map(fn ($category): array => [
            'name' => $category->category,
            'amount' => (float) $category->total_amount,
            'share' => $summary['totalExpenses'] > 0
                ? round(((float) $category->total_amount / $summary['totalExpenses']) * 100, 1)
                : 0.0,
        ])->take(4)->values();
        $topCategory = $spendCategories->first();
        $topClients = $user->invoices()
            ->where('status', '!=', 'paid')
            ->select('client')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('client')
            ->orderByDesc('total_amount')
            ->orderBy('client')
            ->get();
        $topClient = $topClients->first();
        $dueSoon = $this->aggregateInvoices(
            clone $openInvoices,
            $today,
            $today->addDays(30),
        );
        $decisionBrief = [
            'receivables' => [
                'outstanding' => $summary['outstandingInvoices'],
                'dueSoon' => $dueSoon,
                'aging' => $aging,
            ],
            'approvals' => $decisionApprovals,
            'spend' => [
                'total' => $summary['totalExpenses'],
                'topCategory' => $topCategory,
                'categories' => $spendCategories,
            ],
            'clients' => [
                'total' => $summary['outstandingInvoices'],
                'count' => $topClients->count(),
                'top' => $topClient ? [
                    'name' => $topClient->client,
                    'amount' => (float) $topClient->total_amount,
                    'share' => $summary['outstandingInvoices'] > 0
                        ? round(((float) $topClient->total_amount / $summary['outstandingInvoices']) * 100, 1)
                        : 0.0,
                ] : null,
            ],
        ];

        $expenseRecords = null;
        if ($type !== 'invoice' && $status !== 'overdue') {
            $expenseRecords = clone $expenseQuery;
            if ($status !== 'all') {
                $expenseRecords->where('status', $status);
            }
            $this->applySearch($expenseRecords, ['title', 'category'], $search);
            $expenseRecords = $expenseRecords->orderByDesc('date')->orderByDesc('id')->limit(10)->get();
        }

        $invoiceRecords = null;
        if ($type !== 'expense') {
            $invoiceRecords = clone $invoiceQuery;
            if ($status === 'overdue') {
                $invoiceRecords->where('status', '!=', 'paid')->whereDate('due_date', '<', $today->toDateString());
            } elseif ($status === 'open') {
                $invoiceRecords->where('status', '!=', 'paid');
            } elseif ($status !== 'all') {
                $invoiceRecords->where('status', $status);
            }
            $this->applySearch($invoiceRecords, ['invoice_number', 'client'], $search);
            $invoiceRecords = $invoiceRecords->orderByDesc('due_date')->orderByDesc('id')->limit(10)->get();
        }

        $transactions = collect();
        foreach ($expenseRecords ?? [] as $expense) {
            $transactions->push([
                'kind' => 'expense',
                'title' => $expense->title,
                'detail' => $expense->category,
                'amount' => (float) $expense->amount,
                'date' => $expense->date,
                'status' => $expense->status,
                'record' => $expense,
            ]);
        }
        foreach ($invoiceRecords ?? [] as $invoice) {
            $invoiceStatus = $invoice->status !== 'paid' && $invoice->due_date < $today->toDateString()
                ? 'overdue'
                : $invoice->status;
            $transactions->push([
                'kind' => 'invoice',
                'title' => $invoice->invoice_number,
                'detail' => $invoice->client,
                'amount' => (float) $invoice->amount,
                'date' => $invoice->due_date,
                'status' => $invoiceStatus,
                'record' => $invoice,
            ]);
        }
        $transactions = $transactions->sortByDesc('date')->take(10)->values();

        $chartStart = $today->subMonths(11)->startOfMonth();
        $chartEnd = $today->endOfMonth();
        $chartExpenses = $user->expenses()
            ->whereBetween('date', [$chartStart->toDateString(), $chartEnd->toDateString()])
            ->get(['date', 'amount']);
        $chartInvoices = $user->invoices()
            ->whereBetween('due_date', [$chartStart->toDateString(), $chartEnd->toDateString()])
            ->get(['due_date', 'amount']);
        $monthly = $this->buildMonthlyReport($chartExpenses, $chartInvoices, $today);

        return view('dashboard', compact('budgetRows', 'budgetSummary', 'decisionBrief', 'monthly', 'period', 'regionalToday', 'search', 'status', 'summary', 'transactions', 'type', 'view'));
    }

    public function storeBudget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'monthly_limit' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
        ]);
        $category = Str::squish($validated['category']);

        $request->user()->budgets()->updateOrCreate(
            ['category' => $category],
            ['monthly_limit' => $validated['monthly_limit']],
        );

        return back()->with('status', 'Monthly budget saved for '.$category.'.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:pending,approved,paid'],
        ]);

        $request->user()->expenses()->create($validated);

        return back()->with('status', 'Expense saved successfully.');
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:pending,approved,paid'],
        ]);

        $ownedExpense = $request->user()->expenses()->findOrFail($expense->getKey());
        $ownedExpense->update($validated);

        return back()->with('status', 'Expense updated successfully.');
    }

    public function destroyExpense(Request $request, Expense $expense): RedirectResponse
    {
        $request->user()->expenses()->findOrFail($expense->getKey())->delete();

        return back()->with('status', 'Expense deleted.');
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255'],
            'client' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'status' => ['required', 'in:pending,paid,overdue'],
        ]);

        $invoice = $request->user()->invoices()->create($validated);
        if ($validated['status'] === 'paid') {
            $invoice->markAsPaid();
        }

        return back()->with('status', 'Invoice created successfully.');
    }

    public function updateInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255'],
            'client' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'status' => ['required', 'in:pending,paid,overdue'],
        ]);

        $ownedInvoice = $request->user()->invoices()->findOrFail($invoice->getKey());
        $ownedInvoice->update($validated);
        if ($validated['status'] === 'paid') {
            $ownedInvoice->markAsPaid();
        } elseif ($ownedInvoice->paid_at !== null) {
            $ownedInvoice->forceFill(['paid_at' => null])->save();
        }

        return back()->with('status', 'Invoice updated successfully.');
    }

    public function markInvoicePaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $ownedInvoice = $request->user()->invoices()->findOrFail($invoice->getKey());
        $ownedInvoice->markAsPaid();

        return back()->with('status', 'Payment recorded for '.$ownedInvoice->invoice_number.'.');
    }

    public function destroyInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->user()->invoices()->findOrFail($invoice->getKey())->delete();

        return back()->with('status', 'Invoice deleted.');
    }

    public function exportCsv(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['all', 'expenses', 'invoices'], true), 404);

        $user = $request->user();

        return response()->streamDownload(function () use ($user, $type): void {
            $stream = fopen('php://output', 'w');
            $headers = match ($type) {
                'expenses' => ['title', 'category', 'amount', 'currency', 'date', 'status'],
                'invoices' => ['invoice_number', 'client', 'amount', 'currency', 'due_date', 'status'],
                default => ['type', 'title', 'category', 'invoice_number', 'client', 'amount', 'currency', 'date', 'due_date', 'status'],
            };
            fputcsv($stream, $headers);

            if ($type !== 'invoices') {
                foreach ($user->expenses()->lazyById() as $expense) {
                    $row = [
                        'type' => 'expense',
                        'title' => $expense->title,
                        'category' => $expense->category,
                        'invoice_number' => '',
                        'client' => '',
                        'amount' => number_format((float) $expense->amount, 2, '.', ''),
                        'currency' => $user->currency_code,
                        'date' => $expense->date,
                        'due_date' => '',
                        'status' => $expense->status,
                    ];
                    fputcsv($stream, array_intersect_key($row, array_flip($headers)));
                }
            }

            if ($type !== 'expenses') {
                foreach ($user->invoices()->lazyById() as $invoice) {
                    $row = [
                        'type' => 'invoice',
                        'title' => '',
                        'category' => '',
                        'invoice_number' => $invoice->invoice_number,
                        'client' => $invoice->client,
                        'amount' => number_format((float) $invoice->amount, 2, '.', ''),
                        'currency' => $user->currency_code,
                        'date' => '',
                        'due_date' => $invoice->due_date,
                        'status' => $invoice->status,
                    ];
                    fputcsv($stream, array_intersect_key($row, array_flip($headers)));
                }
            }

            fclose($stream);
        }, $type.'-export.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt,text/plain', 'max:10240'],
            'type' => ['required', 'in:expenses,invoices'],
        ]);

        $file = fopen($request->file('csv_file')->getRealPath(), 'r');
        $header = fgetcsv($file);
        if ($header === false) {
            fclose($file);

            return back()->withErrors(['csv_file' => 'The CSV file is empty.']);
        }
        $header = array_map(fn ($value) => mb_strtolower(trim((string) $value)), $header);
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) !== count($header)) {
                $skipped++;

                continue;
            }

            $values = array_combine($header, $row);
            if (isset($values['currency']) && mb_strtoupper(trim($values['currency'])) !== $request->user()->currency_code) {
                $skipped++;

                continue;
            }

            if ($request->type === 'expenses') {
                $record = [
                    'title' => $values['title'] ?? $values['name'] ?? '',
                    'category' => $values['category'] ?? '',
                    'amount' => $values['amount'] ?? '',
                    'date' => $values['date'] ?? '',
                    'status' => $values['status'] ?? 'pending',
                ];
                $validator = Validator::make($record, [
                    'title' => ['required', 'string', 'max:255'],
                    'category' => ['required', 'string', 'max:255'],
                    'amount' => ['required', 'numeric', 'min:0'],
                    'date' => ['required', 'date'],
                    'status' => ['required', 'in:pending,approved,paid'],
                ]);
                if ($validator->fails()) {
                    $skipped++;

                    continue;
                }
                $request->user()->expenses()->create($validator->validated());
            } else {
                $record = [
                    'invoice_number' => $values['invoice_number'] ?? '',
                    'client' => $values['client'] ?? '',
                    'amount' => $values['amount'] ?? '',
                    'due_date' => $values['due_date'] ?? $values['date'] ?? '',
                    'status' => $values['status'] ?? 'pending',
                ];
                $validator = Validator::make($record, [
                    'invoice_number' => ['required', 'string', 'max:255'],
                    'client' => ['required', 'string', 'max:255'],
                    'amount' => ['required', 'numeric', 'min:0'],
                    'due_date' => ['required', 'date'],
                    'status' => ['required', 'in:pending,paid,overdue'],
                ]);
                if ($validator->fails()) {
                    $skipped++;

                    continue;
                }
                $request->user()->invoices()->create($validator->validated());
            }

            $imported++;
        }
        fclose($file);

        $message = 'Imported '.$imported.' records.';
        if ($skipped > 0) {
            $message .= ' Skipped '.$skipped.' invalid rows.';
        }

        return back()->with('status', $message);
    }

    public function invoicePdf(Request $request, Invoice $invoice)
    {
        $invoice = $request->user()->invoices()->findOrFail($invoice->getKey());

        return Pdf::loadView('invoice-pdf', ['invoice' => $invoice, 'currencyCode' => $request->user()->currency_code])
            ->download($invoice->invoice_number.'.pdf');
    }

    private function applyDateRange(Builder $query, string $column, ?CarbonImmutable $start, ?CarbonImmutable $end): Builder
    {
        if ($end !== null) {
            $query->whereDate($column, '<=', $end->toDateString());
        }
        if ($start !== null) {
            $query->whereDate($column, '>=', $start->toDateString());
        }

        return $query;
    }

    private function applySearch(Builder $query, array $columns, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($columns, $search): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    private function percentageChange(float $current, float $previous): ?float
    {
        return $previous === 0.0 ? null : round((($current - $previous) / $previous) * 100, 1);
    }

    private function aggregateInvoices(Builder $query, ?CarbonImmutable $start, ?CarbonImmutable $end): array
    {
        if ($start !== null) {
            $query->whereDate('due_date', '>=', $start->toDateString());
        }
        if ($end !== null) {
            $query->whereDate('due_date', '<=', $end->toDateString());
        }

        $aggregate = $query->selectRaw('COUNT(*) as record_count, COALESCE(SUM(amount), 0) as total_amount')->first();

        return [
            'count' => (int) $aggregate->record_count,
            'amount' => (float) $aggregate->total_amount,
        ];
    }

    private function aggregateExpenses(Builder $query): array
    {
        $aggregate = $query->selectRaw('COUNT(*) as record_count, COALESCE(SUM(amount), 0) as total_amount')->first();

        return [
            'count' => (int) $aggregate->record_count,
            'amount' => (float) $aggregate->total_amount,
        ];
    }

    private function buildMonthlyReport(Collection $expenses, Collection $invoices, CarbonImmutable $today): array
    {
        $data = [];
        for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
            $date = $today->subMonths($monthsAgo)->startOfMonth();
            $key = $date->format('Y-m');
            $data[$key] = [
                'month' => $key,
                'label' => $date->format('M'),
                'expenses' => 0.0,
                'invoices' => 0.0,
                'net' => 0.0,
            ];
        }

        foreach ($expenses as $expense) {
            $month = CarbonImmutable::parse($expense->date)->format('Y-m');
            if (isset($data[$month])) {
                $data[$month]['expenses'] += (float) $expense->amount;
            }
        }

        foreach ($invoices as $invoice) {
            $month = CarbonImmutable::parse($invoice->due_date)->format('Y-m');
            if (isset($data[$month])) {
                $data[$month]['invoices'] += (float) $invoice->amount;
            }
        }

        foreach ($data as &$entry) {
            $entry['net'] = $entry['invoices'] - $entry['expenses'];
        }
        unset($entry);

        return array_values($data);
    }
}
