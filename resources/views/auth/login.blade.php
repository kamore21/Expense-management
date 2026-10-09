<x-guest-layout>
    <div class="auth-heading">
        <p class="auth-kicker auth-kicker-light">ACCOUNT ACCESS</p>
        <h2>Welcome back</h2>
        <p>Sign in to continue to your finance workspace.</p>
    </div>

    @if (session('status'))
        <div class="auth-status" role="status">{{ session('status') }}</div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('login', absolute: false) }}">
        @csrf

        <div class="auth-field">
            <label class="auth-label" for="email">Email address</label>
            <input id="email" class="auth-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.com">
            @error('email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label class="auth-label" for="password">Password</label>
            <input id="password" class="auth-input" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
            @error('password')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-form-options">
            <label class="auth-remember" for="remember_me">
                <input id="remember_me" type="checkbox" name="remember">
                <span>Remember me</span>
            </label>
            @if (Route::has('password.request'))
                <a class="auth-inline-link" href="{{ route('password.request', absolute: false) }}">
                    Forgot password?
                </a>
            @endif
        </div>

        <button class="auth-submit" type="submit">
            <span>Sign in</span>
            <span aria-hidden="true">&#8594;</span>
        </button>
    </form>

    @if (Route::has('register'))
        <p class="auth-switch">New to Ledgerflow? <a href="{{ route('register', absolute: false) }}">Create an account</a></p>
    @endif
</x-guest-layout>
