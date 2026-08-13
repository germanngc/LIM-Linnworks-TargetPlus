<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\LinnworksService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(private LinnworksService $linnworks) {}

    /**
     * Step 1: Linnworks opens this page with ?token= in the URL.
     * We validate the token with Linnworks and show the credentials form.
     */
    public function show(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return view('auth.error', ['message' => 'Missing Linnworks token. Please re-open from Linnworks.']);
        }

        $lwData = $this->linnworks->authorizeByApplication($token);

        if (!$lwData || empty($lwData['Token'])) {
            return view('auth.error', ['message' => 'Could not verify Linnworks token. Please try again.']);
        }

        // Find or create the tenant stub so we preserve the authorization_token across page reloads
        $tenant = Tenant::firstOrCreate(
            ['linnworks_user_id' => $lwData['Id']],
            [
                'linnworks_email'             => $lwData['Email'] ?? '',
                'linnworks_application_token' => $token,
                'linnworks_token'             => $lwData['Token'],
                'linnworks_server'            => $lwData['Server'] ?? 'https://api.linnworks.net',
                'authorization_token'         => Tenant::generateAuthorizationToken(),
                // Placeholder encrypted values — overwritten on form submit
                'target_api_key'              => '_pending_',
                'target_seller_id'            => '_pending_',
                'target_seller_token'         => '_pending_',
            ]
        );

        // Always refresh the session token
        $tenant->update([
            'linnworks_application_token' => $token,
            'linnworks_token'             => $lwData['Token'],
            'linnworks_server'            => $lwData['Server'] ?? $tenant->linnworks_server,
        ]);

        $configured = !str_starts_with($tenant->target_api_key, '_pending_');

        return view('auth.setup', compact('tenant', 'configured'));
    }

    /**
     * Step 2: User submits their Target Plus credentials.
     */
    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'authorization_token' => 'required|uuid',
            'target_api_key'      => 'required|string|min:8',
            'target_seller_id'    => 'required|string|min:4',
            'target_seller_token' => 'required|string|min:8',
            'target_environment'  => 'required|in:prod,stage',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = Tenant::where('authorization_token', $request->authorization_token)->firstOrFail();

        $tenant->update([
            'target_api_key'      => $request->target_api_key,
            'target_seller_id'    => $request->target_seller_id,
            'target_seller_token' => $request->target_seller_token,
            'target_environment'  => $request->target_environment,
            'active'              => true,
        ]);

        return view('auth.success', ['tenant' => $tenant]);
    }
}
