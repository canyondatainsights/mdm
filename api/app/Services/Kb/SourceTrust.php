<?php

namespace App\Services\Kb;

use App\Models\Source;

/**
 * Heuristic trust score for a knowledge source, shown in the inspector. Combines approval, currency,
 * metadata completeness, recency, and provenance (official URL vs upload) into a 0-100 score + level.
 */
class SourceTrust
{
    /**
     * @return array{score:int, level:string, factors:array<int,array{label:string,ok:bool}>}
     */
    public function score(Source $s): array
    {
        $factors = [];
        $score = 0;

        $approved = (bool) $s->approved;
        $factors[] = ['label' => 'Approved for retrieval', 'ok' => $approved];
        $score += $approved ? 30 : 0;

        $current = ! $s->superseded;
        $factors[] = ['label' => 'Current version', 'ok' => $current];
        $score += $current ? 15 : 0;

        $complete = ! $s->needs_metadata;
        $factors[] = ['label' => 'Complete metadata', 'ok' => $complete];
        $score += $complete ? 20 : 0;

        $months = $s->created_at ? $s->created_at->diffInMonths(now()) : 999;
        $recent = $months <= 18;
        $factors[] = ['label' => $recent ? 'Recently ingested' : 'Older than 18 months', 'ok' => $recent];
        $score += $recent ? 20 : ($months <= 36 ? 10 : 0);

        $official = $s->doc_type === 'URL' && ! empty($s->owner);
        $factors[] = ['label' => $official ? 'Official source URL' : 'Uploaded document', 'ok' => true];
        $score += $official ? 15 : 10;

        $score = min(100, $score);

        return [
            'score' => $score,
            'level' => $score >= 75 ? 'high' : ($score >= 45 ? 'medium' : 'low'),
            'factors' => $factors,
        ];
    }
}
