<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Target Plus — Linnworks Setup</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.1); padding: 2rem; width: 100%; max-width: 480px; }
        h1 { font-size: 1.3rem; margin-bottom: .25rem; color: #1a1a2e; }
        p.sub { color: #666; font-size: .875rem; margin-bottom: 1.5rem; }
        label { display: block; font-size: .875rem; font-weight: 600; margin-bottom: .3rem; color: #333; }
        input, select { width: 100%; padding: .6rem .75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: .9rem; margin-bottom: 1rem; }
        input:focus, select:focus { outline: none; border-color: #cc0000; box-shadow: 0 0 0 3px rgba(204,0,0,.1); }
        .btn { width: 100%; padding: .75rem; background: #cc0000; color: #fff; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #aa0000; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .875rem; color: #991b1b; }
        .badge { display: inline-block; background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; border-radius: 4px; padding: .2rem .5rem; font-size: .75rem; font-weight: 600; margin-bottom: 1rem; }
        .token-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: .75rem; font-family: monospace; font-size: .8rem; word-break: break-all; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>🎯 Target Plus × Linnworks</h1>
    <p class="sub">Connect your Target Plus seller account to Linnworks.</p>

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

    @if ($configured)
        <div style="margin-top: 1.5rem;">
            <label style="font-size:.75rem; color:#666; font-weight:400;">Your Linnworks Authorization Token</label>
            <div class="token-box">{{ $tenant->authorization_token }}</div>
            <p style="font-size:.75rem; color:#666;">Copy this token and paste it into the <strong>Authorization Token</strong> field in your Linnworks channel settings.</p>
        </div>
    @endif
</div>
</body>
</html>
