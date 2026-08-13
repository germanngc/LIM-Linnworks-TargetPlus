<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Target Plus — Error</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.1); padding: 2rem; width: 100%; max-width: 420px; text-align: center; }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { font-size: 1.2rem; color: #991b1b; margin-bottom: .75rem; }
        p { color: #555; font-size: .9rem; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">⚠️</div>
    <h1>Setup Error</h1>
    <p>{{ $message }}</p>
</div>
</body>
</html>
