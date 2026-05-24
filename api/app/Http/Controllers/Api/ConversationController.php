<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        return Conversation::where('user_id', $request->user()->id)
            ->orderByDesc('pinned')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($c) => $this->summary($c));
    }

    public function store(Request $request)
    {
        $dims = \App\Services\Taxonomy\Taxonomy::dimensions();

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'mdm_vendor' => ['required', Rule::in($dims['mdm_vendor'])],
            'data_platform' => ['required', Rule::in($dims['data_platform'])],
            'financial_model' => ['nullable', Rule::in($dims['financial_model'])],
            'domains' => ['array'],
            'domains.*' => [Rule::in($dims['domain'])],
            'pii_redacted' => ['boolean'],
        ]);

        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? 'New conversation',
            'mdm_vendor' => $data['mdm_vendor'],
            'data_platform' => $data['data_platform'],
            'financial_model' => $data['financial_model'] ?? null,
            'domains' => $data['domains'] ?? ['general'],
            'pii_redacted' => $data['pii_redacted'] ?? true,
        ]);

        return response()->json($this->summary($conversation), 201);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeOwner($request, $conversation);

        return [
            ...$this->summary($conversation),
            'messages' => $conversation->messages()->orderBy('id')->get()->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'citations' => $m->citations,
                'confidence' => $m->confidence,
                'created_at' => $m->created_at,
            ]),
        ];
    }

    public function destroy(Request $request, Conversation $conversation)
    {
        $this->authorizeOwner($request, $conversation);
        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    private function summary(Conversation $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'pinned' => $c->pinned,
            'mdm_vendor' => $c->mdm_vendor,
            'data_platform' => $c->data_platform,
            'financial_model' => $c->financial_model,
            'domains' => $c->domains,
            'pii_redacted' => $c->pii_redacted,
            'updated_at' => $c->updated_at,
        ];
    }

    private function authorizeOwner(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);
    }
}
