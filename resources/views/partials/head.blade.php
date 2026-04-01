<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    $merchantThemeColor = ($merchantBranding ?? [])['theme_color'] ?? null;
@endphp
@if (is_string($merchantThemeColor) && $merchantThemeColor !== '')
    <meta name="theme-color" content="{{ $merchantThemeColor }}" />
@endif

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}?v=gh2">
<link rel="icon" href="{{ asset('favicon.ico') }}?v=gh2" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=gh2">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
