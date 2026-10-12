@props(['code', 'title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $title }}</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen flex-col">
    <header class="border-b border-rule">
        <div class="mx-auto max-w-7xl px-5 py-4 md:px-14 md:py-5">
            <a href="{{ route('home') }}" class="font-heading text-2xl text-red italic md:text-[30px]">{{ __('app.name') }}</a>
        </div>
    </header>

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>
</body>
</html>
