<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    //
    protected $fillable = [
        'owner_id',
        'parent_id',
        'name',
        'slug',
        'logo',
        'website',
        'address',
        'phone',
        'email',
        'currency',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function parent()
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    public function branches()
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    public function isBranch(): bool
    {
        return ! is_null($this->parent_id);
    }

    public function isParent(): bool
    {
        return is_null($this->parent_id);
    }
    public function plans()
    {
        return $this->hasMany(OrganizationPlan::class,'organization_id','id');
    }
    public function activePlan()
    {
        return $this->hasOne(OrganizationPlan::class, 'organization_id', 'id')->where('is_active', true);
    }

    public function organizationUsers()
    {
        return $this->hasMany(OrganizationUser::class, 'organization_id');
    }

    public function members()
    {
        return $this->hasMany(OrganizationUser::class, 'organization_id')->where('branch_role', 'member');
    }
}
