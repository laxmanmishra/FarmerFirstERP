<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Writes created / updated / deleted events to the append-only audit log.
 *
 * Models may define `protected array $auditExclude` to skip attributes and
 * `protected string $auditModule` to group entries (defaults to table name).
 */
trait Auditable
{
    /** @var list<string> */
    private static array $alwaysExcludedFromAudit = ['created_at', 'updated_at', 'created_by', 'updated_by', 'remember_token'];

    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created', [], $model->auditableAttributes($model->getAttributes())));

        static::updated(function ($model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAudit('updated', $model->auditableAttributes($original), $changes);
        });

        static::deleted(fn ($model) => $model->writeAudit('deleted', $model->auditableAttributes($model->getOriginal()), []));
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    public function auditModule(): string
    {
        return property_exists($this, 'auditModule') ? $this->auditModule : $this->getTable();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $excluded = array_merge(
            self::$alwaysExcludedFromAudit,
            $this->getHidden(),
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAudit(string $event, array $old, array $new): void
    {
        app(AuditService::class)->record($event, $this->auditModule(), $this, $old, $new);
    }
}
