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

    public function approve(Request $request, StewardshipTask $task)
    {
        $this->authorizeSteward($request);

        $task->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        AuditLog::record('stewardship.approved', ['task_id' => $task->id], 'StewardshipTask', (string) $task->id);

        // Apply the approved content to the KB, git commit, and re-index chunks.
        ApplyStewardshipTask::dispatch($task);

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
