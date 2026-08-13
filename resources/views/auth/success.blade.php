<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Target Plus — Connected</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.1); padding: 2rem; width: 100%; max-width: 480px; text-align: center; }
        h1 { font-size: 1.3rem; margin-bottom: .5rem; color: #1a1a2e; }
        p { color: #555; font-size: .9rem; margin-bottom: 1rem; }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        .token-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: .75rem; font-family: monospace; font-size: .85rem; word-break: break-all; margin: 1rem 0; text-align: left; }
        .label { font-size: .75rem; color: #666; margin-bottom: .25rem; font-weight: 600; text-align: left; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">✅</div>
    <h1>Successfully Connected!</h1>
    <p>Your Target Plus credentials have been saved and your integration is active.</p>

    <div class="label">Linnworks Authorization Token</div>
    <div class="token-box">{{ $tenant->authorization_token }}</div>

    <p style="font-size:.8rem; color:#888;">
        Copy this token and paste it into the <strong>Authorization Token</strong> field
        in your Linnworks &rarr; Settings &rarr; Channel Integrations &rarr; Target Plus configuration.
    </p>
</div>
</body>
</html>
