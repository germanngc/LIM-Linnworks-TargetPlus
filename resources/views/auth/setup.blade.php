<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Linnworks Target Plus Bridge — Setup</title>
    <style>
        :root {
            --tp-red: #cc0000;
            --tp-red-dark: #a30000;
            --tp-sky: #20b7f2;
            --ink: #121821;
            --muted: #5e6672;
            --panel: #ffffff;
            --line: #dfe5ec;
            --bg-a: #f6f8fb;
            --bg-b: #eef2f6;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Avenir Next", "Segoe UI", "Helvetica Neue", sans-serif;
            background:
                radial-gradient(circle at 10% 0%, rgba(32,183,242,.13), transparent 40%),
                radial-gradient(circle at 90% 100%, rgba(204,0,0,.10), transparent 35%),
                linear-gradient(160deg, var(--bg-a), var(--bg-b));
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.25rem;
            color: var(--ink);
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 14px 36px rgba(16,24,40,.10);
            width: 100%;
            max-width: 560px;
            overflow: hidden;
        }

        .head {
            padding: 1.1rem 1.35rem;
            border-bottom: 1px solid #ecf0f4;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(90deg, #fff, #fbfdff);
        }

        .brand {
            font-size: .85rem;
            letter-spacing: .05em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .brand strong { color: var(--tp-red); }

        .state {
            font-size: .75rem;
            font-weight: 700;
            color: #0f766e;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 999px;
            padding: .16rem .5rem;
        }

        .body { padding: 1.35rem; }
        h1 { font-size: 1.32rem; margin-bottom: .25rem; }
        p.sub { color: var(--muted); font-size: .9rem; margin-bottom: 1.3rem; }

        label { display: block; font-size: .84rem; font-weight: 700; margin-bottom: .34rem; color: #2a3342; }
        input, select {
            width: 100%;
            padding: .64rem .78rem;
            border: 1px solid #d5dce4;
            border-radius: 8px;
            font-size: .92rem;
            margin-bottom: 1rem;
            background: #fff;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--tp-red);
            box-shadow: 0 0 0 3px rgba(204, 0, 0, .12);
        }

        .btn {
            width: 100%;
            padding: .8rem;
            background: linear-gradient(180deg, var(--tp-red), var(--tp-red-dark));
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .97rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform .18s ease, filter .18s ease;
        }

        .btn:hover { filter: brightness(1.05); transform: translateY(-1px); }
        .alert { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .86rem; color: #991b1b; }
        .badge { display: inline-block; background: #ecfeff; color: #155e75; border: 1px solid #a5f3fc; border-radius: 6px; padding: .24rem .52rem; font-size: .75rem; font-weight: 700; margin-bottom: 1rem; }
        .token-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: .75rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; word-break: break-all; margin-bottom: 1rem; }

        @media (max-width: 640px) {
            .body { padding: 1rem; }
            .head { padding: .9rem 1rem; }
            h1 { font-size: 1.18rem; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="head">
        <div class="brand">Linnworks <span style="color:#8a95a5;">×</span> <strong>Target Plus</strong></div>
        <div class="state">Embedded App</div>
    </div>

    <div class="body">
    <h1>Connect Your Seller Account</h1>
    <p class="sub">Configure Target Plus credentials for this Linnworks tenant.</p>

    @if ($errors->any())
        <div class="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if ($configured)
        <div class="badge">✓ Credentials already configured</div>
    @endif

    <form method="POST" action="{{ route('auth.save') }}">
        @csrf
        <input type="hidden" name="authorization_token" value="{{ $tenant->authorization_token }}">

        <label for="target_api_key">developer.target.com API Key (x-api-key)</label>
        <input type="password" id="target_api_key" name="target_api_key" required autocomplete="off"
               placeholder="{{ $configured ? '••••••••  (leave blank to keep current)' : 'Enter your API key' }}">

        <label for="target_seller_id">Seller ID (x-seller-id)</label>
        <input type="text" id="target_seller_id" name="target_seller_id" required
               value="{{ old('target_seller_id', $configured ? $tenant->target_seller_id : '') }}"
               placeholder="5d9b63230b782d009720a6cb">

        <label for="target_seller_token">Seller Token (x-seller-token)</label>
        <input type="password" id="target_seller_token" name="target_seller_token" required autocomplete="off"
               placeholder="{{ $configured ? '••••••••  (leave blank to keep current)' : 'Enter your seller token' }}">

        <label for="target_environment">Environment</label>
        <select id="target_environment" name="target_environment">
            <option value="prod" {{ ($tenant->target_environment ?? 'prod') === 'prod' ? 'selected' : '' }}>Production</option>
            <option value="stage" {{ ($tenant->target_environment ?? '') === 'stage' ? 'selected' : '' }}>Stage (Sandbox)</option>
        </select>

        <button type="submit" class="btn">Save &amp; Connect</button>
    </form>

    @if ($configured && $showAuthorizationToken)
        <div style="margin-top: 1.5rem;">
            <label style="font-size:.75rem; color:#666; font-weight:400;">Your Linnworks Authorization Token</label>
            <div class="token-box">{{ $tenant->authorization_token }}</div>
            <p style="font-size:.75rem; color:#666;">
                One-time step: copy this token to Linnworks <strong>Authorization Token</strong> in Channel Integrations.
                Embedded app launch and channel callback authorization are separate in Linnworks.
            </p>
        </div>
    @endif
    </div>
</div>
</body>
</html>
