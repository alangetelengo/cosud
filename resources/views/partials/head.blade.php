<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light dark">

<title>@yield('title', config('app.name', 'COSUD'))</title>

<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.favicon')

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@stack('styles')
@stack('meta')
