<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Testbed') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site">
        <div class="wrap">
            <a class="brand" href="{{ route('products.index') }}">{{ config('app.name') }}</a>
            <nav>
                <a href="{{ route('products.index') }}">Products</a>
                <a href="{{ route('cart.index') }}">Cart</a>
                <a href="{{ route('research.debug') }}">Debug</a>
            </nav>
        </div>
    </header>

    <main class="wrap">
        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
