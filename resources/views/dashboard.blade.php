<x-app-layout>
    @php
        $chartMaximum = max(1, collect($monthly)->max(fn ($entry) => max($entry['invoices'], $entry['expenses'])));
        $aging = $decisionBrief['receivables']['aging'];
        $agingTotal = max(1, $decisionBrief['receivables']['outstanding']);
        $overdueCount = $aging['days_1_30']['count'] + $aging['days_31_60']['count'] + $aging['over_60']['count'];
        $overdueAmount = $aging['days_1_30']['amount'] + $aging['days_31_60']['amount'] + $aging['over_60']['amount'];
        $hasDecisionSignals = $overdueCount > 0 || $decisionBrief['approvals']['count'] > 0 || ($decisionBrief['clients']['top']['share'] ?? 0) >= 50;
        $viewHeadings = [
            'intelligence' => 'Finance intelligence',
            'budgets' => 'Budget control',
            'activity' => 'Activity ledger',
            'invoices' => 'Invoice management',
            'reports' => 'Financial reports',
        ];
        $viewDescriptions = [
            'intelligence' => 'Signals, exceptions, and decisions from your live portfolio.',
            'budgets' => 'Track monthly plans against actual category spend.',
            'activity' => 'Search, filter, and manage expense activity.',
            'invoices' => 'Review receivables, due dates, and invoice status.',
            'reports' => 'Explore trends across the last twelve months.',
        ];
        $comparisonLabel = match ($period) {
            'month' => 'previous month',
            'quarter' => 'previous quarter',
            'year' => 'previous year',
            default => 'previous period',
        };
    @endphp

    <div class="ledger-dashboard" id="overview">
        <header class="ledger-page-heading">
            <div>
                <p class="ledger-eyebrow">YOUR MONEY, IN ONE PLACE</p>
                @if ($view === 'overview')
                    <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }}.</h1>
                    <p class="ledger-heading-note">A clear view of what is moving, what is due, and what comes next.</p>
                @else
                    <h1>{{ $viewHeadings[$view] }}</h1>
                    <p class="ledger-heading-note">{{ $viewDescriptions[$view] }}</p>
                @endif
            </div>
            <div class="ledger-primary-actions">
                <button class="ledger-button ledger-button-primary" type="button" data-open-dialog="expense-dialog"><span aria-hidden="true">+</span> Add expense</button>
                <button class="ledger-button ledger-button-secondary" type="button" data-open-dialog="invoice-dialog">New invoice</button>
                <details class="ledger-export-menu">
                    <summary aria-label="Open export options">Export</summary>
                    <div class="ledger-menu-popover">
                        <a href="{{ route('csv.export', ['type' => 'all'], false) }}">All finance data</a>
                        <a href="{{ route('csv.export', ['type' => 'expenses'], false) }}">Expenses only</a>
                        <a href="{{ route('csv.export', ['type' => 'invoices'], false) }}">Invoices only</a>
                        <button type="button" data-open-dialog="import-dialog">Import CSV</button>
                    </div>
                </details>
            </div>
        </header>

        @if (app()->environment('local') && auth()->user()->email === 'admin@ledgerflow.test')
            <div class="ledger-demo-banner"><span>DEMO PORTFOLIO</span><p>Fictional sample transactions are loaded for evaluation. Replace them with your own records at any time.</p></div>
        @endif

        @if (session('status'))
            <div class="ledger-notice ledger-notice-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="ledger-notice ledger-notice-error" role="alert">
                <strong>Some details need attention.</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @if (in_array($view, ['activity', 'invoices'], true))
        <form class="ledger-filter-bar" method="GET" action="{{ route('dashboard', absolute: false) }}">
            <input type="hidden" name="view" value="{{ $view }}">
            <label class="ledger-search-field">
                <span class="ledger-sr-only">Search transactions</span>
                <input type="search" name="search" value="{{ $search }}" placeholder="Search transactions, clients, categories...">
            </label>
            <label>
                <span class="ledger-sr-only">Transaction type</span>
                <select name="type">
                    <option value="all" @selected($type === 'all')>All activity</option>
                    <option value="expense" @selected($type === 'expense')>Expenses</option>
                    <option value="invoice" @selected($type === 'invoice')>Invoices</option>
                </select>
            </label>
            <label>
                <span class="ledger-sr-only">Status</span>
                <select name="status">
                    <option value="all" @selected($status === 'all')>Any status</option>
                    <option value="open" @selected($status === 'open')>Open</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="approved" @selected($status === 'approved')>Approved</option>
                    <option value="paid" @selected($status === 'paid')>Paid</option>
                    <option value="overdue" @selected($status === 'overdue')>Overdue</option>
                </select>
            </label>
            <label>
                <span class="ledger-sr-only">Reporting period</span>
                <select name="period">
                    <option value="month" @selected($period === 'month')>This month</option>
                    <option value="quarter" @selected($period === 'quarter')>This quarter</option>
                    <option value="year" @selected($period === 'year')>This year</option>
                    <option value="all" @selected($period === 'all')>All time</option>
                </select>
            </label>
            <button class="ledger-filter-submit" type="submit">Apply</button>
            @if ($search !== '' || $type !== 'all' || $status !== 'all' || $period !== 'month')
                <a class="ledger-clear-filter" href="{{ route('dashboard', ['view' => $view], false) }}">Clear</a>
            @endif
        </form>
        @endif

        @if (in_array($view, ['overview', 'intelligence'], true))
        <section class="ledger-metrics" aria-label="Finance summary">
            <a class="ledger-metric ledger-metric-inflow ledger-metric-link" href="{{ route('dashboard', ['view' => 'invoices'], false) }}" aria-label="Open invoice management">
                <div class="ledger-metric-top"><span>INVOICES DUE THIS PERIOD</span><span class="ledger-metric-mark" aria-hidden="true">+</span></div>
                <p class="ledger-metric-value"><x-money :amount="$summary['totalInvoices']" /></p>
                <p class="ledger-metric-note"><x-money :amount="$summary['outstandingInvoices']" /> open across all time</p>
                @if ($summary['invoiceTrend'] !== null)
                    <p class="ledger-metric-trend {{ $summary['invoiceTrend'] >= 0 ? 'ledger-trend-up' : 'ledger-trend-down' }}">{{ $summary['invoiceTrend'] > 0 ? '+' : '' }}{{ number_format($summary['invoiceTrend'], 1) }}% vs {{ $comparisonLabel }}</p>
                @endif
            </a>
            <a class="ledger-metric ledger-metric-outflow ledger-metric-link" href="{{ route('dashboard', ['view' => 'activity', 'type' => 'expense'], false) }}" aria-label="Open expense activity">
                <div class="ledger-metric-top"><span>EXPENSES</span><span class="ledger-metric-mark" aria-hidden="true">-</span></div>
                <p class="ledger-metric-value"><x-money :amount="$summary['totalExpenses']" /></p>
                <p class="ledger-metric-note">Logged in this period</p>
                @if ($summary['expenseTrend'] !== null)
                    <p class="ledger-metric-trend {{ $summary['expenseTrend'] <= 0 ? 'ledger-trend-up' : 'ledger-trend-down' }}">{{ $summary['expenseTrend'] > 0 ? '+' : '' }}{{ number_format($summary['expenseTrend'], 1) }}% vs {{ $comparisonLabel }}</p>
                @endif
            </a>
            <a class="ledger-metric ledger-metric-balance ledger-metric-link" href="{{ route('dashboard', ['view' => 'reports'], false) }}" aria-label="Open financial reports">
                <div class="ledger-metric-top"><span>NET BILLINGS</span><span class="ledger-metric-mark" aria-hidden="true">=</span></div>
                <p class="ledger-metric-value"><x-money :amount="$summary['netBalance']" /></p>
                <p class="ledger-metric-note">Invoices minus expenses</p>
            </a>
            <a class="ledger-metric ledger-metric-alert ledger-metric-link {{ $summary['overdueBills'] > 0 ? 'ledger-metric-alert-active' : '' }}" href="{{ route('dashboard', ['view' => 'invoices', 'status' => 'overdue', 'period' => 'all'], false) }}" aria-label="Review overdue invoices">
                <div class="ledger-metric-top"><span>NEEDS ATTENTION</span><span class="ledger-metric-mark" aria-hidden="true">!</span></div>
                <p class="ledger-metric-value">{{ $summary['overdueBills'] }} <small>overdue</small></p>
                <p class="ledger-metric-note">{{ $summary['pendingInvoices'] }} open invoices</p>
            </a>
        </section>
        @endif

        @if ($view === 'intelligence')
        <section class="ledger-decision-section" id="intelligence">
            <div class="ledger-decision-heading">
                <div>
                    <p class="ledger-eyebrow">FINANCE INTELLIGENCE</p>
                    <h2>Decision brief</h2>
                </div>
                <span>Signals from your live portfolio</span>
            </div>

            <div class="ledger-decision-grid">
                <article class="ledger-decision-card ledger-receivables-card">
                    <div class="ledger-decision-card-heading"><span class="ledger-intel-index">01</span><h3>Receivables exposure</h3><span class="ledger-intel-tag">OPEN AR</span></div>
                    <div class="ledger-decision-primary-value"><x-money :amount="$decisionBrief['receivables']['outstanding']" /></div>
                    <div class="ledger-decision-support"><x-money :amount="$decisionBrief['receivables']['dueSoon']['amount']" /> due in the next 30 days <span>· {{ $decisionBrief['receivables']['dueSoon']['count'] }} invoices</span></div>
                    <div class="ledger-aging-stack" role="img" aria-label="Open receivables by age">
                        @foreach ([['key' => 'current', 'class' => 'current'], ['key' => 'days_1_30', 'class' => 'days-1-30'], ['key' => 'days_31_60', 'class' => 'days-31-60'], ['key' => 'over_60', 'class' => 'over-60']] as $bucket)
                            @if ($aging[$bucket['key']]['amount'] > 0)
                                <span class="ledger-aging-segment ledger-aging-{{ $bucket['class'] }}" style="width: {{ ($aging[$bucket['key']]['amount'] / $agingTotal) * 100 }}%"></span>
                            @endif
                        @endforeach
                    </div>
                    <div class="ledger-aging-list">
                        @foreach ([['key' => 'current', 'label' => 'Not yet due'], ['key' => 'days_1_30', 'label' => '1–30 days late'], ['key' => 'days_31_60', 'label' => '31–60 days late'], ['key' => 'over_60', 'label' => '60+ days late']] as $bucket)
                            <a href="{{ route('dashboard', ['view' => 'invoices', 'status' => $bucket['key'] === 'current' ? 'open' : 'overdue', 'period' => 'all'], false) }}" aria-label="Review {{ $bucket['label'] }} invoices"><span><i class="ledger-aging-swatch ledger-aging-{{ str_replace('_', '-', $bucket['key']) }}"></i>{{ $bucket['label'] }}</span><strong><x-money :amount="$aging[$bucket['key']]['amount']" /></strong></a>
                        @endforeach
                    </div>
                </article>

                <article class="ledger-decision-card">
                    <div class="ledger-decision-card-heading"><span class="ledger-intel-index">02</span><h3>Spend governance</h3><span class="ledger-intel-tag">REVIEW QUEUE</span></div>
                    <div class="ledger-decision-primary-value"><x-money :amount="$decisionBrief['approvals']['amount']" /></div>
                    <div class="ledger-decision-support">{{ $decisionBrief['approvals']['count'] }} expenses awaiting review in this period</div>
                    @if ($decisionBrief['approvals']['count'] > 0)
                        <a class="ledger-decision-action" href="{{ route('dashboard', ['view' => 'activity', 'type' => 'expense', 'status' => 'pending', 'period' => $period], false) }}">Review pending expenses <span aria-hidden="true">→</span></a>
                    @else
                        <p class="ledger-decision-empty">No expenses are waiting for review.</p>
                    @endif
                </article>

                <article class="ledger-decision-card">
                    <div class="ledger-decision-card-heading"><span class="ledger-intel-index">03</span><h3>Spend mix</h3><span class="ledger-intel-tag">{{ $period === 'month' ? 'THIS MONTH' : strtoupper($period) }}</span></div>
                    @if ($decisionBrief['spend']['topCategory'])
                        <div class="ledger-decision-primary-value ledger-decision-category">{{ $decisionBrief['spend']['topCategory']['name'] }}</div>
                        <div class="ledger-decision-support">Largest category · <x-money :amount="$decisionBrief['spend']['topCategory']['amount']" /> · {{ number_format($decisionBrief['spend']['topCategory']['share'], 1) }}% of spend</div>
                        <div class="ledger-category-list">
                            @foreach ($decisionBrief['spend']['categories'] as $category)
                                <a class="ledger-category-row" href="{{ route('dashboard', ['view' => 'activity', 'type' => 'expense', 'period' => $period, 'search' => $category['name']], false) }}"><span>{{ $category['name'] }}</span><span class="ledger-category-track"><i style="width: {{ min(100, $category['share']) }}%"></i></span><strong><x-money :amount="$category['amount']" /></strong></a>
                            @endforeach
                        </div>
                    @else
                        <div class="ledger-decision-empty">Category analysis will appear when expenses are recorded.</div>
                    @endif
                </article>

                <article class="ledger-decision-card">
                    <div class="ledger-decision-card-heading"><span class="ledger-intel-index">04</span><h3>Customer concentration</h3><span class="ledger-intel-tag">OPEN AR</span></div>
                    @if ($decisionBrief['clients']['top'])
                        <div class="ledger-decision-primary-value">{{ number_format($decisionBrief['clients']['top']['share'], 1) }}<small>%</small></div>
                        <div class="ledger-decision-support">of open receivables are due from <strong>{{ $decisionBrief['clients']['top']['name'] }}</strong></div>
                        <div class="ledger-concentration-track"><i class="{{ $decisionBrief['clients']['top']['share'] >= 50 ? 'is-concentrated' : '' }}" style="width: {{ min(100, $decisionBrief['clients']['top']['share']) }}%"></i></div>
                        <div class="ledger-decision-support">{{ $decisionBrief['clients']['count'] }} clients · <x-money :amount="$decisionBrief['clients']['top']['amount']" /> open with largest client</div>
                        <a class="ledger-decision-action" href="{{ route('dashboard', ['view' => 'invoices', 'status' => 'open', 'period' => 'all', 'search' => $decisionBrief['clients']['top']['name']], false) }}">Review this account <span aria-hidden="true">→</span></a>
                    @else
                        <div class="ledger-decision-empty">Customer exposure will appear when invoices are created.</div>
                    @endif
                </article>
            </div>

            <div class="ledger-priority-strip">
                <div class="ledger-priority-title"><span>PRIORITY MOVES</span><strong>{{ $hasDecisionSignals ? 'Actionable items from your records' : 'Portfolio clear' }}</strong></div>
                @if ($overdueCount > 0)
                    <a class="ledger-priority-item ledger-priority-risk" href="{{ route('dashboard', ['view' => 'invoices', 'status' => 'overdue', 'period' => 'all'], false) }}"><span class="ledger-priority-marker">!</span><span><strong>Recover overdue receivables</strong><small>{{ $overdueCount }} invoices · <x-money :amount="$overdueAmount" /> past due</small></span><span class="ledger-priority-arrow" aria-hidden="true">→</span></a>
                @endif
                @if ($decisionBrief['approvals']['count'] > 0)
                    <a class="ledger-priority-item" href="{{ route('dashboard', ['view' => 'activity', 'type' => 'expense', 'status' => 'pending', 'period' => $period], false) }}"><span class="ledger-priority-marker">$</span><span><strong>Review pending spend</strong><small>{{ $decisionBrief['approvals']['count'] }} expenses · <x-money :amount="$decisionBrief['approvals']['amount']" /></small></span><span class="ledger-priority-arrow" aria-hidden="true">→</span></a>
                @endif
                @if (($decisionBrief['clients']['top']['share'] ?? 0) >= 50)
                    <a class="ledger-priority-item" href="{{ route('dashboard', ['view' => 'invoices', 'status' => 'open', 'period' => 'all', 'search' => $decisionBrief['clients']['top']['name']], false) }}"><span class="ledger-priority-marker">%</span><span><strong>Monitor customer concentration</strong><small>{{ number_format($decisionBrief['clients']['top']['share'], 1) }}% of open AR is tied to {{ $decisionBrief['clients']['top']['name'] }}</small></span><span class="ledger-priority-arrow" aria-hidden="true">→</span></a>
                @endif
                @unless ($hasDecisionSignals)
                    <p class="ledger-priority-clear">No overdue invoices, pending expense reviews, or single-client exposure above 50%.</p>
                @endunless
            </div>
        </section>
        @endif

        @if ($view === 'budgets')
        <section class="ledger-budget-section" id="budgets">
            <div class="ledger-budget-heading">
                <div>
                    <p class="ledger-eyebrow">PLAN AGAINST ACTUAL</p>
                    <h2>Budget control</h2>
                    <p>Monthly category limits make overruns visible before close.</p>
                </div>
                <button class="ledger-button ledger-button-secondary" type="button" data-open-dialog="budget-dialog"><span aria-hidden="true">+</span> Set monthly limit</button>
            </div>

            @if ($budgetRows->isNotEmpty())
                <div class="ledger-budget-summary">
                    <div><span>PLANNED</span><strong><x-money :amount="$budgetSummary['limit']" /></strong></div>
                    <div><span>ACTUAL</span><strong><x-money :amount="$budgetSummary['actual']" /></strong></div>
                    <div><span>REMAINING</span><strong><x-money :amount="$budgetSummary['remaining']" /></strong></div>
                    <div><span>NEEDS ATTENTION</span><strong>{{ $budgetSummary['over_count'] }} over · {{ $budgetSummary['watch_count'] }} near</strong></div>
                </div>
                <div class="ledger-budget-list">
                    @foreach ($budgetRows as $budget)
                        <a class="ledger-budget-row" href="{{ route('dashboard', ['view' => 'activity', 'type' => 'expense', 'period' => 'month', 'search' => $budget['category']], false) }}" aria-label="Review {{ $budget['category'] }} expenses">
                            <div class="ledger-budget-category"><strong>{{ $budget['category'] }}</strong><span class="ledger-budget-state ledger-budget-state-{{ $budget['state'] }}">{{ $budget['state'] === 'on-track' ? 'On track' : ($budget['state'] === 'watch' ? 'Near limit' : 'Over budget') }}</span></div>
                            <div class="ledger-budget-meter" role="img" aria-label="{{ $budget['category'] }} has used {{ number_format($budget['percent'], 1) }} percent of its monthly limit">
                                <span class="ledger-budget-meter-fill ledger-budget-meter-{{ $budget['state'] }}" style="width: {{ min(100, $budget['percent']) }}%"></span>
                            </div>
                            <div class="ledger-budget-values"><strong><x-money :amount="$budget['actual']" /></strong><span>of <x-money :amount="$budget['limit']" /></span></div>
                            <div class="ledger-budget-remaining {{ $budget['state'] === 'over' ? 'is-over' : '' }}">{{ $budget['state'] === 'over' ? 'Over by' : 'Left' }} <strong><x-money :amount="$budget['state'] === 'over' ? $budget['over_by'] : $budget['remaining']" /></strong></div>
                        </a>
                    @endforeach
                </div>
                <div class="ledger-unbudgeted-note">Unbudgeted spend this month <strong><x-money :amount="$budgetSummary['unbudgeted_spend']" /></strong></div>
            @else
                <div class="ledger-budget-empty"><strong>No monthly limits yet.</strong><span>Set category guardrails to see actual spend, remaining headroom, and overruns here.</span><button class="ledger-text-action" type="button" data-open-dialog="budget-dialog">Create the first limit</button></div>
            @endif
        </section>
        @endif

        @if ($view === 'reports')
        <section class="ledger-insights-grid" id="reports">
            <article class="ledger-panel ledger-cashflow-panel">
                <div class="ledger-panel-heading">
                    <div>
                        <p class="ledger-eyebrow">12-MONTH VIEW</p>
                        <h2>Cashflow rhythm</h2>
                    </div>
                    <div class="ledger-chart-legend"><span><i class="legend-invoices"></i> Invoices</span><span><i class="legend-expenses"></i> Expenses</span></div>
                </div>
                <div class="ledger-chart-scroll">
                    <div class="ledger-chart" role="img" aria-label="Monthly invoice and expense totals for the last twelve months">
                        @foreach ($monthly as $entry)
                            <div class="ledger-chart-month" title="{{ $entry['label'] }}: {{ auth()->user()->currency_code }} {{ number_format($entry['invoices'], 2) }} invoiced, {{ auth()->user()->currency_code }} {{ number_format($entry['expenses'], 2) }} spent">
                                <div class="ledger-chart-bars">
                                    <span class="ledger-chart-bar ledger-chart-bar-invoice" style="height: {{ max(3, ($entry['invoices'] / $chartMaximum) * 100) }}%"></span>
                                    <span class="ledger-chart-bar ledger-chart-bar-expense" style="height: {{ max(3, ($entry['expenses'] / $chartMaximum) * 100) }}%"></span>
                                </div>
                                <span class="ledger-chart-month-label">{{ $entry['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="ledger-chart-summary">
                    <span>Trailing-year net</span>
                    <strong><x-money :amount="collect($monthly)->sum('net')" /></strong>
                </div>
            </article>

            <aside class="ledger-panel ledger-attention-panel">
                <div class="ledger-panel-heading">
                    <div>
                        <p class="ledger-eyebrow">YOUR NEXT MOVES</p>
                        <h2>On the radar</h2>
                    </div>
                </div>
                <div class="ledger-attention-line">
                    <span class="ledger-attention-icon ledger-attention-icon-coral" aria-hidden="true">!</span>
                    <div><strong>{{ $summary['overdueBills'] }} overdue invoices</strong><span>Past due and still unpaid</span></div>
                    <a href="{{ route('dashboard', ['view' => 'invoices', 'status' => 'overdue', 'period' => 'all'], false) }}" aria-label="View overdue invoices">View</a>
                </div>
                <div class="ledger-attention-line">
                    <span class="ledger-attention-icon ledger-attention-icon-green" aria-hidden="true">$</span>
                    <div><strong><x-money :amount="$summary['outstandingInvoices']" /> receivable</strong><span>Across {{ $summary['pendingInvoices'] }} open invoices</span></div>
                    <a href="{{ route('dashboard', ['view' => 'invoices', 'status' => 'open', 'period' => 'all'], false) }}" aria-label="View open invoices">View</a>
                </div>
                <div class="ledger-attention-footer">
                    <div><span>Paid this period</span><small>For invoices due this period</small></div>
                    <strong><x-money :amount="$summary['paidInvoices']" /></strong>
                </div>
            </aside>
        </section>
        @endif

        @if (in_array($view, ['activity', 'invoices'], true))
        <section class="ledger-panel ledger-activity-panel" id="activity">
            <div class="ledger-panel-heading ledger-activity-heading">
                <div>
                    <p class="ledger-eyebrow">THE DETAIL</p>
                    <h2>Recent activity</h2>
                </div>
                <span class="ledger-result-count">{{ $transactions->count() }} shown</span>
            </div>
            <div class="ledger-table-scroll">
                <table class="ledger-table">
                    <thead>
                        <tr><th scope="col">Activity</th><th scope="col">Type</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col" class="ledger-number-cell">Amount</th><th scope="col"><span class="ledger-sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            @php $record = $transaction['record']; @endphp
                            <tr>
                                <td>
                                    <div class="ledger-activity-name"><span class="ledger-activity-dot {{ $transaction['kind'] === 'invoice' ? 'ledger-dot-invoice' : 'ledger-dot-expense' }}"></span><span><strong>{{ $transaction['title'] }}</strong><small>{{ $transaction['detail'] }}</small></span></div>
                                </td>
                                <td><span class="ledger-type-label">{{ $transaction['kind'] }}</span></td>
                                <td class="ledger-date-cell"><x-regional-date :value="$transaction['date']" date-only /></td>
                                <td>
                                    <span class="ledger-status ledger-status-{{ \Illuminate\Support\Str::slug($transaction['status']) }}">{{ $transaction['status'] }}</span>
                                    @if ($transaction['kind'] === 'invoice' && $record->paid_at)
                                        <small class="ledger-payment-date">Paid <x-regional-date :value="$record->paid_at" date-only /></small>
                                    @endif
                                </td>
                                <td class="ledger-number-cell {{ $transaction['kind'] === 'expense' ? 'ledger-amount-expense' : 'ledger-amount-invoice' }}">{{ $transaction['kind'] === 'expense' ? '-' : '+' }}<x-money :amount="$transaction['amount']" /></td>
                                <td>
                                    <div class="ledger-row-actions">
                                        <button type="button" class="ledger-text-action" data-edit-transaction
                                            data-kind="{{ $transaction['kind'] }}"
                                            data-update-url="{{ $transaction['kind'] === 'expense' ? route('expenses.update', ['expense' => $record->id], false) : route('invoices.update', ['invoice' => $record->id], false) }}"
                                            data-title="{{ $transaction['kind'] === 'expense' ? $record->title : $record->invoice_number }}"
                                            data-detail="{{ $transaction['kind'] === 'expense' ? $record->category : $record->client }}"
                                            data-amount="{{ $record->amount }}"
                                            data-date="{{ $transaction['date'] }}"
                                            data-status="{{ $record->status }}">Edit</button>
                                        @if ($transaction['kind'] === 'invoice')
                                            @if ($transaction['status'] !== 'paid')
                                                <form method="POST" action="{{ route('invoices.mark-paid', ['invoice' => $record->id], false) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="ledger-text-action ledger-pay-action" type="submit">Mark paid</button>
                                                </form>
                                            @endif
                                            <a class="ledger-text-action" href="{{ route('invoices.pdf', ['invoice' => $record->id], false) }}" target="_blank" rel="noopener">PDF</a>
                                            <form method="POST" action="{{ route('invoices.destroy', ['invoice' => $record->id], false) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="ledger-text-action ledger-delete-action" type="submit">Delete</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('expenses.destroy', ['expense' => $record->id], false) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="ledger-text-action ledger-delete-action" type="submit">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="ledger-empty-state"><strong>No activity matches this view.</strong><span>Try another period or add your first expense or invoice.</span><button type="button" class="ledger-text-action" data-open-dialog="expense-dialog">Add an expense</button></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="ledger-table-footnote">Showing up to 10 matching entries for the selected period.</p>
        </section>
        @endif
    </div>

    <dialog class="ledger-dialog" id="expense-dialog" aria-labelledby="expense-dialog-title">
        <form method="POST" action="{{ route('expenses.store', absolute: false) }}" class="ledger-dialog-form">
            @csrf
            <div class="ledger-dialog-heading"><div><p class="ledger-eyebrow">OUTGOING</p><h2 id="expense-dialog-title">Add expense</h2></div><button type="button" class="ledger-dialog-close" data-close-dialog>Close</button></div>
            <label>Expense name<input name="title" required maxlength="255" placeholder="e.g. Studio rent"></label>
            <div class="ledger-form-grid"><label>Category<input name="category" required maxlength="255" placeholder="e.g. Operations"></label><label>Amount<input name="amount" type="number" min="0" step="0.01" required placeholder="0.00"></label></div>
            <div class="ledger-form-grid"><label>Date<input name="date" type="date" value="{{ $regionalToday->toDateString() }}" required></label><label>Status<select name="status"><option value="pending">Pending</option><option value="approved">Approved</option><option value="paid">Paid</option></select></label></div>
            <div class="ledger-dialog-actions"><button type="button" class="ledger-button ledger-button-secondary" data-close-dialog>Cancel</button><button type="submit" class="ledger-button ledger-button-primary">Save expense</button></div>
        </form>
    </dialog>

    <dialog class="ledger-dialog" id="invoice-dialog" aria-labelledby="invoice-dialog-title">
        <form method="POST" action="{{ route('invoices.store', absolute: false) }}" class="ledger-dialog-form">
            @csrf
            <div class="ledger-dialog-heading"><div><p class="ledger-eyebrow">INCOMING</p><h2 id="invoice-dialog-title">Create invoice</h2></div><button type="button" class="ledger-dialog-close" data-close-dialog>Close</button></div>
            <div class="ledger-form-grid"><label>Invoice number<input name="invoice_number" required maxlength="255" placeholder="INV-1042"></label><label>Client<input name="client" required maxlength="255" placeholder="Client name"></label></div>
            <div class="ledger-form-grid"><label>Amount<input name="amount" type="number" min="0" step="0.01" required placeholder="0.00"></label><label>Due date<input name="due_date" type="date" value="{{ $regionalToday->addDays(14)->toDateString() }}" required></label></div>
            <label>Status<select name="status"><option value="pending">Pending</option><option value="paid">Paid</option><option value="overdue">Overdue</option></select></label>
            <div class="ledger-dialog-actions"><button type="button" class="ledger-button ledger-button-secondary" data-close-dialog>Cancel</button><button type="submit" class="ledger-button ledger-button-primary">Create invoice</button></div>
        </form>
    </dialog>

    <dialog class="ledger-dialog" id="edit-dialog" aria-labelledby="edit-dialog-title">
        <form method="POST" action="#" id="edit-transaction-form" class="ledger-dialog-form">
            @csrf
            @method('PATCH')
            <div class="ledger-dialog-heading"><div><p class="ledger-eyebrow">UPDATE RECORD</p><h2 id="edit-dialog-title">Edit activity</h2></div><button type="button" class="ledger-dialog-close" data-close-dialog>Close</button></div>
            <div data-edit-expense-fields>
                <label>Expense name<input id="edit-expense-title" name="title" required maxlength="255"></label>
                <div class="ledger-form-grid"><label>Category<input id="edit-expense-detail" name="category" required maxlength="255"></label><label>Amount<input id="edit-expense-amount" name="amount" type="number" min="0" step="0.01" required></label></div>
                <div class="ledger-form-grid"><label>Date<input id="edit-expense-date" name="date" type="date" required></label><label>Status<select id="edit-expense-status" name="status"><option value="pending">Pending</option><option value="approved">Approved</option><option value="paid">Paid</option></select></label></div>
            </div>
            <div data-edit-invoice-fields hidden>
                <div class="ledger-form-grid"><label>Invoice number<input id="edit-invoice-title" name="invoice_number" required maxlength="255"></label><label>Client<input id="edit-invoice-detail" name="client" required maxlength="255"></label></div>
                <div class="ledger-form-grid"><label>Amount<input id="edit-invoice-amount" name="amount" type="number" min="0" step="0.01" required></label><label>Due date<input id="edit-invoice-date" name="due_date" type="date" required></label></div>
                <label>Status<select id="edit-invoice-status" name="status"><option value="pending">Pending</option><option value="paid">Paid</option><option value="overdue">Overdue</option></select></label>
            </div>
            <div class="ledger-dialog-actions"><button type="button" class="ledger-button ledger-button-secondary" data-close-dialog>Cancel</button><button type="submit" class="ledger-button ledger-button-primary">Save changes</button></div>
        </form>
    </dialog>

    <dialog class="ledger-dialog" id="import-dialog" aria-labelledby="import-dialog-title">
        <form method="POST" action="{{ route('csv.import', absolute: false) }}" enctype="multipart/form-data" class="ledger-dialog-form">
            @csrf
            <div class="ledger-dialog-heading"><div><p class="ledger-eyebrow">BULK UPDATE</p><h2 id="import-dialog-title">Import CSV</h2></div><button type="button" class="ledger-dialog-close" data-close-dialog>Close</button></div>
            <label>Record type<select name="type"><option value="expenses">Expenses</option><option value="invoices">Invoices</option></select></label>
            <label>CSV file<input type="file" name="csv_file" accept=".csv,text/csv" required></label>
            <p class="ledger-dialog-help">Rows with missing or invalid values are skipped and reported after import.</p>
            <div class="ledger-dialog-actions"><button type="button" class="ledger-button ledger-button-secondary" data-close-dialog>Cancel</button><button type="submit" class="ledger-button ledger-button-primary">Import records</button></div>
        </form>
    </dialog>

    <dialog class="ledger-dialog" id="budget-dialog" aria-labelledby="budget-dialog-title">
        <form method="POST" action="{{ route('budgets.store', absolute: false) }}" class="ledger-dialog-form">
            @csrf
            <div class="ledger-dialog-heading"><div><p class="ledger-eyebrow">MONTHLY PLAN</p><h2 id="budget-dialog-title">Set category limit</h2></div><button type="button" class="ledger-dialog-close" data-close-dialog>Close</button></div>
            <label>Expense category<input name="category" required maxlength="255" list="budget-category-options" placeholder="e.g. Operations"></label>
            <datalist id="budget-category-options">
                @foreach ($budgetRows as $budget)
                    <option value="{{ $budget['category'] }}"></option>
                @endforeach
                @foreach ($decisionBrief['spend']['categories'] as $category)
                    <option value="{{ $category['name'] }}"></option>
                @endforeach
            </datalist>
            <label>Monthly limit<input name="monthly_limit" type="number" min="0.01" step="0.01" required placeholder="0.00"></label>
            <p class="ledger-dialog-help">Saving an existing category replaces its current monthly limit.</p>
            <div class="ledger-dialog-actions"><button type="button" class="ledger-button ledger-button-secondary" data-close-dialog>Cancel</button><button type="submit" class="ledger-button ledger-button-primary">Save limit</button></div>
        </form>
    </dialog>
</x-app-layout>
