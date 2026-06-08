<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Flash Sale Platform</title>

    <link rel="stylesheet" href="{{ route('welcome.styles') }}">
</head>

<body class="readme-page">
    <header class="readme-top">
        <nav class="readme-top-nav" aria-label="README navigation">
            <div class="readme-top-nav-brand">
                <a href="{{ url('/') }}">Flash Sale Platform</a>
            </div>

            <div class="readme-top-nav-links">
                <a href="{{ url('/') }}">Home</a>
                <a href="{{ url(config('horizon.path', 'horizon')) }}">Horizon</a>
                <a href="{{ url(config('telescope.path', 'telescope')) }}">Telescope</a>
            </div>
        </nav>

        <div class="readme-top-content">
            <p class="readme-kicker">Project documentation</p>
            <h1>Flash Sale Platform</h1>
            <p>
                Setup, architecture, database design, queue strategy, performance notes,
                code review findings, and test coverage in one readable project document.
            </p>
        </div>
    </header>

    <main class="readme-layout">
        <article class="readme-document">
            {!! $readmeHtml !!}
        </article>

        <aside class="readme-right-panel" aria-label="Table of contents">
            <div class="readme-contents">
                <p class="readme-panel-label">Contents</p>
                <nav>
                    @foreach ($toc as $item)
                        <a class="readme-contents-link readme-contents-level-{{ $item['level'] }}"
                            href="#{{ $item['slug'] }}">
                            {{ $item['title'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>
    </main>
</body>

</html>
