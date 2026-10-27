@props(['title'])

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · LoveGames</title>
        <style>
            :root { --text: #1a1a1a; --muted: #555; --accent: #e91e63; --bg: #fff; --border: #e5e5e5; }
            @media (prefers-color-scheme: dark) {
                :root { --text: #ececec; --muted: #a8a8a8; --accent: #f06292; --bg: #121212; --border: #2c2c2c; }
            }
            body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
            main { max-width: 760px; margin: 0 auto; padding: 32px 16px 64px; }
            h1 { font-size: 1.9rem; line-height: 1.25; margin: 0 0 4px; }
            h2 { font-size: 1.2rem; margin: 36px 0 8px; padding-top: 16px; border-top: 1px solid var(--border); }
            .updated { color: var(--muted); margin: 0 0 24px; }
            a { color: var(--accent); }
            ul { padding-left: 22px; }
            li { margin: 4px 0; }
            table { width: 100%; border-collapse: collapse; font-size: .95rem; }
            th, td { text-align: left; vertical-align: top; padding: 8px; border-bottom: 1px solid var(--border); }
            th { color: var(--muted); font-weight: 600; }
            .table-wrap { overflow-x: auto; }
        </style>
    </head>
    <body>
        <main>
        {{ $slot }}
        </main>
    </body>
</html>
