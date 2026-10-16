<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 — {{ __('app.errors.500.title') }}</title>
    <x-global.robots-meta :status="500" />
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; background: #efe7d6; color: #201f1d; font-family: Georgia, 'Times New Roman', serif; }
        a:focus-visible { outline: 2px solid #b68235; outline-offset: 2px; }
        .bar { border-bottom: 1px solid rgb(32 31 29 / .16); padding: 16px 20px; }
        .wordmark { font-style: italic; font-size: 24px; color: #c22d24; text-decoration: none; }
        main { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 20px; padding: 48px 20px 64px; text-align: center; }
        .stamp { width: 150px; height: 180px; border: 2px dashed #c22d24; padding: 6px; transform: rotate(-3deg); }
        .stamp-inner { width: 100%; height: 100%; border: 1px solid #c22d24; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #c22d24; }
        .stamp-label { font-size: 11px; letter-spacing: .2em; text-transform: uppercase; }
        .stamp-code { font-size: 64px; line-height: 1; font-variant-numeric: tabular-nums; }
        h1 { margin: 12px 0 0; font-size: 38px; font-weight: 400; }
        p { margin: 0; max-width: 520px; font-size: 16px; color: #444141; }
        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 6px; }
        .btn { display: inline-flex; align-items: center; min-height: 48px; padding: 12px 24px; border: 1px solid rgb(32 31 29 / .16); border-radius: 2px; color: #201f1d; font-size: 16px; text-decoration: none; transition: background-color 150ms; }
        .btn:hover { background: rgb(32 31 29 / .07); }
        .btn-primary { border-color: #c22d24; color: #c22d24; }
        .btn-primary:hover { background: rgb(198 47 38 / .12); }
        @media (min-width: 768px) {
            .bar { padding: 20px 56px; }
            .wordmark { font-size: 30px; }
            main { padding: 80px 56px 96px; }
            h1 { font-size: 52px; }
        }
    </style>
</head>
<body>
    <header class="bar">
        <a href="{{ route('home') }}" class="wordmark">{{ __('app.name') }}</a>
    </header>
    <main>
        <div class="stamp" lang="es" aria-hidden="true">
            <div class="stamp-inner">
                <span class="stamp-label">{{ __('app.errors.500.stamp_top') }}</span>
                <span class="stamp-code">500</span>
                <span class="stamp-label">{{ __('app.errors.500.stamp_bottom') }}</span>
            </div>
        </div>
        <h1>{{ __('app.errors.500.title') }}</h1>
        <p>{{ __('app.errors.500.message') }}</p>
        <div class="actions">
            <a href="{{ url()->current() }}" class="btn btn-primary">{{ __('app.errors.500.retry') }}</a>
            <a href="{{ route('home') }}" class="btn">{{ __('app.errors.home') }}</a>
        </div>
    </main>
</body>
</html>
