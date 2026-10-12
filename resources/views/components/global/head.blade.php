@props(['title' => null])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title === null ? __('app.meta.default_title') : $title.' — '.__('app.name') }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{ $slot }}
