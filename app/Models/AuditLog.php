<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Convenience: log an event from any observer. */
    public static function record(
        string $action,
        Model $subject,
        string $description,
        array $oldValues = [],
        array $newValues = [],
    ): void {
        $orgId = method_exists($subject, 'getOrganizationId')
            ? $subject->getOrganizationId()
            : ($subject->organization_id ?? null);

        static::create([
            'organization_id' => $orgId,
            'user_id'         => auth()->id(),
            'action'          => $action,
            'subject_type'    => get_class($subject),
            'subject_id'      => $subject->getKey(),
            'description'     => $description,
            'old_values'      => $oldValues ?: null,
            'new_values'      => $newValues ?: null,
            'ip_address'      => request()->ip(),
            'created_at'      => now(),
        ]);
    }
}
