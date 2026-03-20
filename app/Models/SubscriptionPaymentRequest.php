<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPaymentRequest extends Model
{
    protected $fillable = [
        'organization_id',
        'plan_id',
        'months',
        'amount',
        'reference',
        'proof_path',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'organization_plan_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'amount'      => 'float',
            'months'      => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function organizationPlan(): BelongsTo
    {
        return $this->belongsTo(OrganizationPlan::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
