<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Ledgerflow') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-body">
        <div class="auth-shell">
            <aside class="auth-brand-panel">
                <a class="auth-wordmark" href="{{ url('/') }}">
                    <span class="auth-mark" aria-hidden="true">L</span>
                    <span>Ledgerflow</span>
                </a>

                <div class="auth-brand-copy">
                    <p class="auth-kicker">FINANCE, IN FOCUS</p>
                    <h1>See the whole picture.<br>Make the next move.</h1>
                    <p class="auth-brand-description">A clearer workspace for the money coming in and going out.</p>

                    <div class="auth-flow-graphic" aria-hidden="true">
                        <div class="auth-flow-heading">
                            <span>MONTHLY FLOW</span>
                            <span class="auth-flow-key"><i></i> In <i></i> Out</span>
                        </div>
                        <div class="auth-flow-bars">
                            <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                        </div>
                        <div class="auth-flow-axis"><span>JAN</span><span>APR</span><span>JUL</span><span>OCT</span></div>
                    </div>
                </div>

                <p class="auth-brand-foot">A little more clarity, every month.</p>
            </aside>

            <main class="auth-main-panel">
                <div class="auth-form-wrap">
                    <a class="auth-mobile-wordmark" href="{{ url('/') }}">Ledgerflow</a>
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
