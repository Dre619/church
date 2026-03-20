<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPlan extends Model
{
    protected $fillable = [
        'organization_id',
        'plan_id',
        'months',
        'amount_paid',
        'discount',
        'status',
        'payment_reference',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'start_date' => 'date',
            'end_date'   => 'date',
            'months'     => 'integer',
            'discount'   => 'integer',
            'amount_paid' => 'float',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function hasActivePlan(): bool
    {
        return $this->is_active && $this->start_date <= now() && (is_null($this->end_date) || $this->end_date >= now());
    }
}
