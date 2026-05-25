<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ResearchTopic;
use App\Services\Taxonomy\Taxonomy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Saved research topics for deep-dives. Each user sees their own topics plus all `shared` ones
 * ("group research"). A deep-dive launches a new conversation pre-locked to the topic's stack.
 */
class ResearchTopicController extends Controller
{
    public function index(Request $request)
    {
        $uid = $request->user()->id;

        return ResearchTopic::with('user')
            ->where(fn ($q) => $q->where('user_id', $uid)->orWhere('scope', 'shared'))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($t) => $this->summary($t, $uid));
    }

    public function store(Request $request)
    {
        $topic = ResearchTopic::create([...$this->validated($request), 'user_id' => $request->user()->id]);

        return response()->json($this->summary($topic->load('user'), $request->user()->id), 201);
    }

    public function update(Request $request, ResearchTopic $topic)
    {
        $this->authorizeEdit($request, $topic);
        $topic->update($this->validated($request));

        return $this->summary($topic->load('user'), $request->user()->id);
    }

    public function destroy(Request $request, ResearchTopic $topic)
    {
        $this->authorizeEdit($request, $topic);
        $topic->delete();

        return response()->json(['ok' => true]);
    }

    /** Launch a new conversation pre-locked to the topic's stack; returns it + a seed prompt. */
    public function deepDive(Request $request, ResearchTopic $topic)
    {
        abort_unless($topic->user_id === $request->user()->id || $topic->scope === 'shared', 403);

        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'title' => $topic->title,
            'mdm_vendor' => $topic->mdm_vendor ?: 'informatica',
            'data_platform' => $topic->data_platform ?: 'databricks',
            'financial_model' => $topic->financial_model ?: null,
            'domains' => $topic->domains ?: ['general'],
            'extensions' => $topic->extensions ?: null,
            'pii_redacted' => true,
        ]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'seed' => $topic->notes ?: $topic->title,
        ], 201);
    }

    private function validated(Request $request): array
    {
        $dims = Taxonomy::dimensions();

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'scope' => ['required', Rule::in(['private', 'shared'])],
            'mdm_vendor' => ['nullable', Rule::in($dims['mdm_vendor'] ?? [])],
            'data_platform' => ['nullable', Rule::in($dims['data_platform'] ?? [])],
            'financial_model' => ['nullable', Rule::in($dims['financial_model'] ?? [])],
            'domains' => ['nullable', 'array'],
            'domains.*' => [Rule::in($dims['domain'] ?? [])],
            'extensions' => ['nullable', 'array'],
            'extensions.*' => [Rule::in($dims['extension'] ?? [])],
        ]);
    }

    private function authorizeEdit(Request $request, ResearchTopic $topic): void
    {
        $user = $request->user();
        abort_unless($topic->user_id === $user->id || $user->hasAnyRole(['Steward', 'Admin']), 403);
    }

    private function summary(ResearchTopic $t, int $uid): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'notes' => $t->notes,
            'scope' => $t->scope,
            'mdm_vendor' => $t->mdm_vendor,
            'data_platform' => $t->data_platform,
            'financial_model' => $t->financial_model,
            'domains' => $t->domains,
            'extensions' => $t->extensions,
            'owned' => $t->user_id === $uid,
            'owner' => $t->user?->name,
            'created_at' => $t->created_at,
        ];
    }
}
