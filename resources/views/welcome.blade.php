<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Flash Sale Platform</title>

    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
</head>

<body class="welcome-page">
    <main class="welcome-panel">
        <h1 class="welcome-title">
            Flash Sale
            <span>Platform</span>
        </h1>
        <p class="welcome-copy">
            A production-minded Laravel API for flash-sale ordering, stock protection,
            authenticated checkout, and queue-backed operational workflows.
        </p>
        <a class="welcome-status" href="{{ route('readme') }}">README</a>
    </main>
</body>

</html>
