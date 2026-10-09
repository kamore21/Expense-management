<nav class="ledger-topbar" aria-label="Main navigation">
    <div class="ledger-topbar-inner">
        <a class="ledger-brand" href="{{ route('dashboard', absolute: false) }}">
            <span class="ledger-brand-mark" aria-hidden="true">L</span>
            <span>Ledgerflow</span>
        </a>

        @php($activeView = request()->query('view', 'overview'))
        <div class="ledger-nav-links" role="navigation" aria-label="Dashboard views">
            <a href="{{ route('dashboard', ['view' => 'overview'], false) }}" @class(['ledger-nav-link', 'is-active' => $activeView === 'overview']) @if ($activeView === 'overview') aria-current="page" @endif>Overview</a>
            <a href="{{ route('dashboard', ['view' => 'intelligence'], false) }}" @class(['ledger-nav-link', 'is-active' => $activeView === 'intelligence']) @if ($activeView === 'intelligence') aria-current="page" @endif>Intelligence</a>
            <a href="{{ route('dashboard', ['view' => 'budgets'], false) }}" @class(['ledger-nav-link', 'is-active' => $activeView === 'budgets']) @if ($activeView === 'budgets') aria-current="page" @endif>Budgets</a>
            <a href="{{ route('dashboard', ['view' => 'activity'], false) }}" @class(['ledger-nav-link', 'is-active' => $activeView === 'activity']) @if ($activeView === 'activity') aria-current="page" @endif>Activity</a>
            <a href="{{ route('dashboard', ['view' => 'invoices'], false) }}" @class(['ledger-nav-link', 'is-active' => $activeView === 'invoices']) @if ($activeView === 'invoices') aria-current="page" @endif>Invoices</a>
            <a href="{{ route('dashboard', ['view' => 'reports'], false) }}" @class(['ledger-nav-link', 'is-active' => $activeView === 'reports']) @if ($activeView === 'reports') aria-current="page" @endif>Reports</a>
        </div>

        <div class="ledger-user-tools">
            <details class="ledger-user-menu">
                <summary><span class="ledger-avatar">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span><span>{{ Auth::user()->name }}</span><span class="ledger-chevron" aria-hidden="true"></span></summary>
                <div class="ledger-menu-popover">
                    <a href="{{ route('profile.edit', absolute: false) }}">Profile settings</a>
                    <form method="POST" action="{{ route('logout', absolute: false) }}">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                </div>
            </details>
            <details class="ledger-mobile-menu">
                <summary aria-label="Open navigation"><span aria-hidden="true">Menu</span></summary>
                <div class="ledger-menu-popover">
                    <a href="{{ route('dashboard', ['view' => 'overview'], false) }}" @if ($activeView === 'overview') aria-current="page" @endif>Overview</a>
                    <a href="{{ route('dashboard', ['view' => 'intelligence'], false) }}" @if ($activeView === 'intelligence') aria-current="page" @endif>Intelligence</a>
                    <a href="{{ route('dashboard', ['view' => 'budgets'], false) }}" @if ($activeView === 'budgets') aria-current="page" @endif>Budgets</a>
                    <a href="{{ route('dashboard', ['view' => 'activity'], false) }}" @if ($activeView === 'activity') aria-current="page" @endif>Activity</a>
                    <a href="{{ route('dashboard', ['view' => 'invoices'], false) }}" @if ($activeView === 'invoices') aria-current="page" @endif>Invoices</a>
                    <a href="{{ route('dashboard', ['view' => 'reports'], false) }}" @if ($activeView === 'reports') aria-current="page" @endif>Reports</a>
                    <a href="{{ route('profile.edit', absolute: false) }}">Profile settings</a>
                    <form method="POST" action="{{ route('logout', absolute: false) }}">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</nav>
