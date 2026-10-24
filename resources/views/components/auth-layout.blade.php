@props(['title'])

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · LoveGames</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #fdf2f6; color: #1f1f1f; padding: 16px; box-sizing: border-box; }
        .card { width: 100%; max-width: 380px; background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 8px 30px rgba(233, 30, 99, .12); }
        h1 { font-size: 1.4rem; margin: 0 0 8px; color: #e91e63; }
        p { color: #555; line-height: 1.5; }
        label { display: block; font-size: .85rem; font-weight: 600; margin: 16px 0 6px; }
        input { width: 100%; box-sizing: border-box; padding: 12px; border: 1px solid #ddd; border-radius: 10px; font-size: 1rem; }
        button { width: 100%; margin-top: 22px; padding: 14px; border: 0; border-radius: 10px; background: #e91e63; color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .error { color: #c62828; font-size: .85rem; margin-top: 6px; }
    </style>
</head>
<body>
    <main class="card">
        {{ $slot }}
    </main>
</body>
</html>
