<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Target Plus — Dashboard</title>
    <style>
        :root {
            --tp-red: #cc0000;
            --tp-red-dark: #a30000;
            --tp-sky: #20b7f2;
            --ink: #202124;
            --muted: #5f6368;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Avenir Next", "Segoe UI", "Helvetica Neue", sans-serif;
            background:
                radial-gradient(circle at 10% 0%, rgba(32,183,242,.12), transparent 38%),
                radial-gradient(circle at 95% 90%, rgba(204,0,0,.12), transparent 32%),
                #edf1f5;
            color: var(--ink);
            padding: 2rem 1rem;
        }
        .wrap { max-width: 980px; margin: 0 auto; }
        .panel { background: #fff; border: 1px solid #dee2e6; border-radius: 10px; box-shadow: 0 4px 14px rgba(0,0,0,.05); overflow: hidden; }
        .head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.1rem 1.35rem;
            border-bottom: 1px solid #ececec;
            background: linear-gradient(90deg, #fff, #fbfdff);
        }
        .head h1 { font-size: 1.2rem; font-weight: 700; }
        .connected { color: #0f766e; font-weight: 700; font-size: .9rem; }
        .body { padding: 1.2rem 1.35rem 1.35rem; }
        .sub { color: var(--muted); font-size: .92rem; margin-bottom: 1rem; }
        .status-card { background: #f6f7f8; border: 1px solid #e4e7ea; border-radius: 8px; padding: .95rem; display: flex; justify-content: space-between; gap: .8rem; flex-wrap: wrap; margin-bottom: 1.15rem; }
        .status-meta { color: var(--muted); font-size: .84rem; }
        .status-meta strong { color: #111827; font-size: .98rem; }
        .actions { display: flex; gap: .55rem; align-items: center; flex-wrap: wrap; }
        .btn { border: 1px solid #d1d5db; border-radius: 7px; background: #fff; color: #111827; font-weight: 600; font-size: .85rem; padding: .52rem .85rem; cursor: pointer; }
        .btn.dark { background: #111827; border-color: #111827; color: #fff; }
        .btn.warn { background: linear-gradient(180deg, var(--tp-red), var(--tp-red-dark)); border-color: var(--tp-red-dark); color: #fff; }
        .btn.is-loading { opacity: .82; cursor: wait; }
        .btn:disabled { opacity: .7; cursor: not-allowed; }
        .spinner {
            display: inline-block;
            width: .85rem;
            height: .85rem;
            border: 2px solid rgba(255, 255, 255, .45);
            border-top-color: #fff;
            border-radius: 50%;
            margin-right: .45rem;
            animation: spin .8s linear infinite;
            vertical-align: -2px;
        }
        .btn:not(.dark):not(.warn) .spinner {
            border: 2px solid rgba(17, 24, 39, .25);
            border-top-color: #111827;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .section { margin-top: 1.2rem; }
        .section h2 { font-size: 1.03rem; margin-bottom: .5rem; }
        .table-wrap { border: 1px solid #e5e7eb; border-radius: 8px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 740px; }
        th, td { padding: .62rem .7rem; border-bottom: 1px solid #edf0f2; text-align: left; font-size: .85rem; vertical-align: top; }
        th { background: #f9fafb; color: #4b5563; font-weight: 700; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: .18rem .45rem; border-radius: 999px; font-size: .75rem; font-weight: 700; }
        .badge.ok { background: #dcfce7; color: #166534; }
        .badge.warn { background: #fee2e2; color: #9f1239; }
        .muted { color: #6b7280; }
        .alert { border-radius: 8px; padding: .7rem .9rem; margin-bottom: .95rem; font-size: .85rem; }
        .alert.ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert.err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .token-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: .75rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .84rem; word-break: break-all; margin-top: .5rem; }
        .hint { margin-top: .45rem; color: #6b7280; font-size: .8rem; }
        .sync-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; margin-bottom: 1rem; }
        .sync-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: .55rem .62rem; background: #fff; }
        .sync-card .name { font-size: .76rem; font-weight: 700; color: #4b5563; text-transform: uppercase; letter-spacing: .03em; }
        .sync-card .state { margin-top: .25rem; font-size: .81rem; font-weight: 700; }
        .state-idle { color: #6b7280; }
        .state-running { color: #1d4ed8; }
        .state-success { color: #166534; }
        .state-failed { color: #b91c1c; }
        .sync-card .msg { margin-top: .2rem; font-size: .76rem; color: #6b7280; min-height: 1.05rem; }
        @media (max-width: 740px) {
            .body { padding: 1rem; }
            .head { padding: 1rem; }
            .sync-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="panel">
        <div class="head">
            <h1>Linnworks Target Plus Bridge</h1>
            <div class="connected">Connected</div>
        </div>

        <div class="body">
            @if (session('sync_status'))
                <div class="alert ok">{{ session('sync_status') }}</div>
            @endif

            @if (session('sync_error'))
                <div class="alert err">{{ session('sync_error') }}</div>
            @endif

            <div class="sync-grid" id="sync-status-grid">
                @foreach (['orders' => 'Orders', 'inventory' => 'Inventory', 'listings' => 'Listings'] as $key => $label)
                    @php
                        $state = $syncStates[$key]['state'] ?? 'idle';
                        $message = $syncStates[$key]['message'] ?? null;
                    @endphp
                    <div class="sync-card" data-sync-type="{{ $key }}">
                        <div class="name">{{ $label }}</div>
                        <div class="state state-{{ $state }}">{{ ucfirst($state) }}</div>
                        <div class="msg">{{ $message ?: 'No recent activity' }}</div>
                    </div>
                @endforeach
            </div>

            <p class="sub">Your branded operations hub for Target Plus orders, inventory, and listing links.</p>

            <div class="status-card">
                <div class="status-meta">
                    Signed in as<br>
                    <strong>{{ $tenant->linnworks_email ?: ('Tenant #' . $tenant->id) }}</strong><br>
                    Site {{ strtoupper($tenant->target_environment) }} · Seller ID {{ $tenant->target_seller_id }}
                </div>

                <div class="actions">
                    <form method="POST" action="{{ route('auth.sync', ['type' => 'orders']) }}" class="sync-form" data-loading-text="Syncing orders...">
                        @csrf
                        <input type="hidden" name="authorization_token" value="{{ $tenant->authorization_token }}">
                        <button type="submit" class="btn dark">
                            <span class="btn-label">Sync orders</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('auth.sync', ['type' => 'inventory']) }}" class="sync-form" data-loading-text="Syncing inventory...">
                        @csrf
                        <input type="hidden" name="authorization_token" value="{{ $tenant->authorization_token }}">
                        <button type="submit" class="btn warn">
                            <span class="btn-label">Sync inventory</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('auth.sync', ['type' => 'listings']) }}" class="sync-form" data-loading-text="Creating listings...">
                        @csrf
                        <input type="hidden" name="authorization_token" value="{{ $tenant->authorization_token }}">
                        <button type="submit" class="btn">
                            <span class="btn-label">Create listing</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="section">
                <h2>Open orders ({{ $openOrders->count() }})</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Linnworks sync</th>
                            <th>Payment</th>
                            <th>Tracking</th>
                            <th>Updated</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($openOrders as $order)
                            <tr>
                                <td>{{ $order->target_order_number ?: $order->target_order_id }}</td>
                                <td><span class="badge {{ in_array($order->target_status, ['SHIPPED','RELEASED_FOR_SHIPMENT']) ? 'ok' : 'warn' }}">{{ $order->target_status ?: 'UNKNOWN' }}</span></td>
                                <td>
                                    @php
                                        $syncClass = match($order->sync_status) {
                                            'synced' => 'ok',
                                            'failed' => 'warn',
                                            default => 'warn',
                                        };
                                    @endphp
                                    <span class="badge {{ $syncClass }}">{{ ucfirst($order->sync_status ?? 'pending') }}</span>
                                    @if (($order->sync_status ?? null) === 'failed' && $order->last_sync_error)
                                        <div class="muted" style="margin-top:.3rem; max-width:240px; white-space:normal;">
                                            {{ \Illuminate\Support\Str::limit($order->last_sync_error, 110) }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $order->lwPaymentStatus() }}</td>
                                <td>{{ $order->tracking_number ?: 'Not linked' }}</td>
                                <td class="muted">{{ optional($order->target_updated_at)->diffForHumans() ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="muted">No open orders yet. Run Sync orders to fetch from Target Plus.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section">
                <h2>Inventory / Listings ({{ $products->count() }})</h2>
                <p class="sub">Linked items: {{ $linkedCount }}. Create listing maps Target Plus products to Linnworks stock items.</p>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Target item</th>
                            <th>Title</th>
                            <th>Qty</th>
                            <th>Link</th>
                            <th>Last sync</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>{{ $product->sku ?: '—' }}</td>
                                <td>{{ $product->external_id ?: $product->tcin }}</td>
                                <td>{{ $product->title ?: 'Untitled product' }}</td>
                                <td>{{ $product->available_quantity }}</td>
                                <td>
                                    <span class="badge {{ $product->linnworks_channel_mapping_id ? 'ok' : 'warn' }}">
                                        {{ $product->linnworks_channel_mapping_id ? 'Linked' : 'Unlinked' }}
                                    </span>
                                </td>
                                <td class="muted">{{ optional($product->linnworks_synced_at ?: $product->inventory_synced_at)->diffForHumans() ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="muted">No products synced yet. Run Sync inventory first.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($showAuthorizationToken)
                <div class="section">
                    <h2>Linnworks Authorization Token</h2>
                    <div class="token-box">{{ $tenant->authorization_token }}</div>
                    <p class="hint">Use this only when running Channel Integration callback endpoints.</p>
                </div>
            @endif
        </div>
    </div>
</div>
<script>
    (function () {
        const forms = document.querySelectorAll('.sync-form');
        const syncStatusRoute = "{{ route('auth.sync.status') }}";
        const authorizationToken = '{{ $tenant->authorization_token }}';
        const csrfToken = '{{ csrf_token() }}';

        function updateSyncCards(states) {
            Object.keys(states).forEach((type) => {
                const card = document.querySelector('.sync-card[data-sync-type="' + type + '"]');

                if (!card) {
                    return;
                }

                const stateEl = card.querySelector('.state');
                const msgEl = card.querySelector('.msg');
                const state = states[type]?.state || 'idle';
                const msg = states[type]?.message || 'No recent activity';

                stateEl.className = 'state state-' + state;
                stateEl.textContent = state.charAt(0).toUpperCase() + state.slice(1);
                msgEl.textContent = msg;
            });
        }

        async function pollStatuses() {
            try {
                const response = await fetch(syncStatusRoute, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ authorization_token: authorizationToken }),
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();

                if (payload?.states) {
                    updateSyncCards(payload.states);

                    const hasRunning = Object.values(payload.states).some((state) => state?.state === 'running');

                    if (!hasRunning) {
                        forms.forEach((f) => {
                            const button = f.querySelector('button[type="submit"]');
                            const label = button?.querySelector('.btn-label');

                            if (!button || !label) {
                                return;
                            }

                            button.disabled = false;
                            button.classList.remove('is-loading');

                            if (label.dataset.originalText) {
                                label.textContent = label.dataset.originalText;
                            }
                        });
                    }
                }
            } catch (_error) {
                // Keep UI responsive even if polling fails temporarily.
            }
        }

        forms.forEach((form) => {
            form.addEventListener('submit', function () {
                const loadingText = form.dataset.loadingText || 'Working...';

                forms.forEach((f) => {
                    const button = f.querySelector('button[type="submit"]');
                    const label = button?.querySelector('.btn-label');

                    if (!button || !label) {
                        return;
                    }

                    button.disabled = true;
                    button.classList.add('is-loading');

                    if (f === form) {
                        label.dataset.originalText = label.textContent;
                        label.innerHTML = '<span class="spinner" aria-hidden="true"></span>' + loadingText;
                        button.setAttribute('aria-busy', 'true');
                    }
                });
            });
        });

        setInterval(pollStatuses, 4000);
        pollStatuses();
    })();
</script>
</body>
</html>
