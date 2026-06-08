<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>README - Flash Sale Platform</title>

    <link rel="stylesheet" href="{{ route('readme.styles') }}">
</head>

<body class="readme-page">
    <header class="readme-top">
        <nav class="readme-top-nav" aria-label="README navigation">
            <a href="{{ url('/') }}">Flash Sale Platform</a>
            <a href="{{ url('/') }}">Back Home</a>
        </nav>

        <div class="readme-top-content">
            <p class="readme-kicker">Project documentation</p>
            <h1>Flash Sale Platform README</h1>
            <p>
                Setup, architecture, database design, queue strategy, performance notes,
                code review findings, and test coverage in one readable project document.
            </p>
        </div>
    </header>

    <main class="readme-layout">
        <aside class="readme-left-panel" aria-label="Project summary">
            <div class="readme-panel-section">
                <p class="readme-panel-label">System</p>
                <h2>Flash-sale API</h2>
                <p>
                    Laravel 12 service for authenticated ordering, stock protection,
                    queue-backed operations, and dashboard reporting.
                </p>
            </div>

            <div class="readme-panel-section">
                <p class="readme-panel-label">Run locally</p>
                <code>docker compose up -d --build</code>
            </div>

            <div class="readme-panel-section">
                <p class="readme-panel-label">Useful links</p>
                <a href="{{ url('/') }}">Welcome page</a>
                <a href="{{ route('readme') }}">README document</a>
                <a href="{{ route('readme.styles') }}">Document CSS</a>
            </div>
        </aside>

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
