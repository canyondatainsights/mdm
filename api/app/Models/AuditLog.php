<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_log';

    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'meta', 'git_commit', 'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A human-readable one-line description of this entry, built from action + meta. */
    public function summary(): string
    {
        $m = is_array($this->meta) ? $this->meta : [];
        $n = (int) ($m['count'] ?? 0);
        $s = fn (int $c) => $c === 1 ? '' : 's';
        $file = fn (?string $p) => $p ? basename($p) : 'a source';

        return match ($this->action) {
            'source.uploaded' => "Uploaded {$n} source".$s($n).(! empty($m['per_file']) ? ' with reviewed tags' : ''),
            'source.ingested' => 'Ingested '.$file($m['path'] ?? null).(isset($m['chunks']) ? " → {$m['chunks']} chunks" : ''),
            'source.url_failed' => 'URL fetch failed: '.($m['url'] ?? '').(isset($m['error']) ? " ({$m['error']})" : ''),
            'source.approved' => "Approved {$n} source".$s($n),
            'source.unapproved' => "Unapproved {$n} source".$s($n),
            'taxonomy.products_fetched' => "Fetched {$n} ".Str::title((string) ($m['vendor'] ?? ''))." products".(isset($m['added']) ? " ({$m['added']} new)" : ''),
            'taxonomy.term_created' => 'Added taxonomy '.($m['type'] ?? 'term').' "'.($m['value'] ?? '').'"',
            'taxonomy.term_updated' => 'Updated taxonomy '.($m['type'] ?? 'term').' "'.($m['value'] ?? '').'"',
            'taxonomy.term_deleted' => 'Deleted taxonomy '.($m['type'] ?? 'term').' "'.($m['value'] ?? '').'"',
            default => Str::ucfirst(str_replace(['.', '_'], ' ', $this->action)),
        };
    }

    /** Badge color category for an action (for the admin table). */
    public static function actionColor(string $action): string
    {
        return match (true) {
            str_contains($action, 'approved') && ! str_contains($action, 'unapproved') => 'success',
            str_contains($action, 'unapprove'), str_contains($action, 'fail'), str_contains($action, 'delete'), str_contains($action, 'reject') => 'danger',
            str_starts_with($action, 'taxonomy.') => 'warning',
            str_starts_with($action, 'source.') => 'info',
            default => 'gray',
        };
    }

    public static function record(string $action, array $meta = [], ?string $subjectType = null, ?string $subjectId = null, ?string $gitCommit = null): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta,
            'git_commit' => $gitCommit,
            'created_at' => now(),
        ]);
    }
}
