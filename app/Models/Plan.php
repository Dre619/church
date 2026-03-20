<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    //
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'discount_percentage',
        'discount_max_organizations',
        'max_members',
        'is_active',
        'is_trial',
        'trial_days',
        'can_view_reports',
        'can_export',
    ];

    protected function casts(): array
    {
        return [
            'price'               => 'decimal:2',
            'discount_percentage'       => 'integer',
            'discount_max_organizations' => 'integer',
            'is_active'           => 'boolean',
            'is_trial'            => 'boolean',
            'trial_days'          => 'integer',
            'can_view_reports'    => 'boolean',
            'can_export'          => 'boolean',
        ];
    }

    public function hasActiveDiscount(): bool
    {
        if (! $this->discount_percentage) {
            return false;
        }

        if ($this->discount_max_organizations === null) {
            return true;
        }

        return $this->organizationPlans()->count() < $this->discount_max_organizations;
    }

    public function discountedPrice(): float
    {
        if (! $this->hasActiveDiscount()) {
            return (float) $this->price;
        }

        return round((float) $this->price * (1 - $this->discount_percentage / 100), 2);
    }

    public function scopeTrial($query)
    {
        return $query->where('is_trial', true);
    }

    public function scopePaid($query)
    {
        return $query->where('is_trial', false);
    }
    public function organizationPlans()
    {
        return $this->hasMany(OrganizationPlan::class,'plan_id','id');
    }
    public function activeOrganizationPlans()
    {
        return $this->hasMany(OrganizationPlan::class,'plan_id','id')->where('is_active', true);
    }
}
