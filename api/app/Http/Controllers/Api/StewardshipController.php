<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ApplyStewardshipTask;
use App\Models\AuditLog;
use App\Models\StewardshipTask;
use Illuminate\Http\Request;

class StewardshipController extends Controller
{
    public function index(Request $request)
    {
        return StewardshipTask::with('proposer:id,name')
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /** A user-submitted request (e.g. "crawl this whole site") for a steward to action. */
    public function request(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $host = parse_url($data['url'], PHP_URL_HOST) ?: $data['url'];

        $task = StewardshipTask::create([
            'type' => 'crawl_request',
            'target_path' => $data['url'],
            'summary' => "Crawl request: {$host}".(! empty($data['note']) ? " — {$data['note']}" : ''),
            'proposed_content' => $data['note'] ?? null,
            'status' => 'pending',
            'proposed_by' => $request->user()->id,
        ]);
        AuditLog::record('stewardship.requested', ['task_id' => $task->id, 'type' => 'crawl_request', 'url' => $data['url']], 'StewardshipTask', (string) $task->id);

        return response()->json(['ok' => true, 'task_id' => $task->id], 201);
    }

    public function approve(Request $request, StewardshipTask $task)
    {
        $this->authorizeSteward($request);

        $task->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        AuditLog::record('stewardship.approved', ['task_id' => $task->id], 'StewardshipTask', (string) $task->id);

        // Apply approved CONTENT to the KB; request-type tasks (e.g. crawl_request) carry no content —
        // a steward actions them by setting up a crawler in the admin, so don't run the apply job.
        if ($task->type !== 'crawl_request') {
            ApplyStewardshipTask::dispatch($task);
        }

        return response()->json(['ok' => true, 'status' => 'approved']);
    }

    public function reject(Request $request, StewardshipTask $task)
    {
        $this->authorizeSteward($request);

        $task->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        AuditLog::record('stewardship.rejected', ['task_id' => $task->id], 'StewardshipTask', (string) $task->id);

        return response()->json(['ok' => true, 'status' => 'rejected']);
    }

    private function authorizeSteward(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['Steward', 'Admin']), 403, 'Steward role required.');
    }
}
