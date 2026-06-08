<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'LineUp') }} - {{ $classroom->name }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                color-scheme: light;
            }

            body.public-display {
                min-height: 100vh;
                margin: 0;
                background: #f3f4f6;
                color: #111827;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            .public-display__page {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            .public-display__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1.5rem;
                min-height: 4rem;
                padding: 0.75rem 2rem;
                background: #ffffff;
                border-bottom: 1px solid #e5e7eb;
                box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
            }

            .public-display__brand,
            .public-display__room {
                min-width: 0;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                font-size: clamp(1.125rem, 1.5vw, 1.5rem);
                font-weight: 700;
                color: #111827;
            }

            .public-display__room {
                justify-content: flex-end;
                text-align: right;
            }

            .public-display__brand img {
                width: 2.25rem;
                height: 2.25rem;
                flex: 0 0 auto;
            }

            .public-display__truncate {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .public-display__main {
                flex: 1;
                width: min(100% - 2rem, 72rem);
                margin: 0 auto;
                padding: 1.5rem 0;
            }

            .public-display__list {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .public-display__request {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1.5rem;
                min-height: 5.25rem;
                padding: 1rem 1.25rem;
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                box-shadow: 0 1px 2px rgb(15 23 42 / 0.05);
            }

            .public-display__request--priority {
                background: #fff1f2;
                border-color: #fb7185;
                border-left: 0.6rem solid #be123c;
                box-shadow: 0 8px 20px rgb(190 18 60 / 0.12);
                color: #881337;
            }

            .public-display__student {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: clamp(1.5rem, 3vw, 2.25rem);
                font-weight: 700;
                color: #111827;
            }

            .public-display__request--priority .public-display__student {
                color: #881337;
            }

            .public-display__table {
                flex: 0 0 auto;
                min-width: 5rem;
                padding: 0.55rem 1rem;
                border-radius: 0.45rem;
                background: #eef2ff;
                border: 1px solid #c7d2fe;
                color: #312e81;
                text-align: center;
                font-size: clamp(1.5rem, 3vw, 2.25rem);
                font-weight: 800;
            }

            .public-display__empty {
                display: flex;
                min-height: 45vh;
                align-items: center;
                justify-content: center;
                padding: 3rem 1.5rem;
                background: #ffffff;
                border: 1px dashed #cbd5e1;
                border-radius: 0.5rem;
                color: #475569;
                text-align: center;
                font-size: clamp(1.5rem, 3vw, 2.25rem);
                font-weight: 700;
            }

            @media (max-width: 640px) {
                .public-display__header {
                    padding: 0.75rem 1rem;
                    gap: 1rem;
                }

                .public-display__main {
                    width: min(100% - 1rem, 72rem);
                    padding: 0.75rem 0;
                }

                .public-display__request {
                    gap: 1rem;
                    min-height: 4.5rem;
                    padding: 0.85rem 1rem;
                }

                .public-display__table {
                    min-width: 4rem;
                }
            }
        </style>
    </head>
    <body class="public-display">
        <div class="public-display__page">
            <header class="public-display__header">
                <div class="public-display__brand">
                    <img src="{{ asset('logo.svg') }}" alt="">
                    <span class="public-display__truncate">{{ config('app.name', 'LineUp') }}</span>
                </div>

                <div class="public-display__room">
                    <span class="public-display__truncate">{{ $classroom->name }}</span>
                </div>
            </header>

            <main class="public-display__main">
                <div
                    id="public-request-list"
                    data-version="{{ $version }}"
                    data-refresh-url="{{ route('public-display.requests', $classroom->public_slug) }}"
                >
                    @include('public-display.partials.requests', ['requests' => $requests])
                </div>
            </main>
        </div>

        <script>
            (() => {
                const list = document.getElementById('public-request-list');

                if (! list) {
                    return;
                }

                async function refreshRequests() {
                    const url = new URL(list.dataset.refreshUrl, window.location.origin);
                    url.searchParams.set('version', list.dataset.version || '0');

                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    if (response.status === 204) {
                        return;
                    }

                    if (response.status === 404) {
                        window.location.reload();
                        return;
                    }

                    if (! response.ok) {
                        return;
                    }

                    const payload = await response.json();
                    list.dataset.version = payload.version;
                    list.innerHTML = payload.html;
                }

                window.setInterval(refreshRequests, 5000);
            })();
        </script>
    </body>
</html>
