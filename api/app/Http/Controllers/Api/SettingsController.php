<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\SettingsService;
use Illuminate\Http\Request;

/** Admin-only management of the Claude API key (stored encrypted; never returned in full). */
class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function show(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'anthropic' => [
                'has_key' => $this->settings->hasAnthropicKey(),
                'hint' => $this->settings->maskedHint(),
                'source' => $this->settings->source(),
                'model' => config('mdm.anthropic.model'),
            ],
            'embeddings' => [
                'driver' => config('mdm.embeddings.driver'),
                'dim' => config('mdm.embeddings.dim'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'anthropic_api_key' => ['required', 'string', 'min:8'],
        ]);

        $this->settings->setAnthropicKey($data['anthropic_api_key']);
        AuditLog::record('settings.anthropic_key.updated', ['source' => 'admin-ui']);

        return response()->json([
            'ok' => true,
            'has_key' => true,
            'hint' => $this->settings->maskedHint(),
        ]);
    }

    /** Validate a candidate key (or the stored one) against the Anthropic API. */
    public function test(Request $request)
    {
        $this->authorizeAdmin($request);

        $key = $request->input('anthropic_api_key'); // optional; tests stored key if omitted

        return response()->json($this->settings->testAnthropicKey($key));
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('Admin'), 403, 'Admin role required.');
    }
}
