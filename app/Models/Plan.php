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
            'price'            => 'decimal:2',
            'is_active'        => 'boolean',
            'is_trial'         => 'boolean',
            'trial_days'       => 'integer',
            'can_view_reports' => 'boolean',
            'can_export'       => 'boolean',
        ];
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
