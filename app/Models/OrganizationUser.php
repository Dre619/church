<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'user_type',
        'branch_role',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function isOwner(): bool
    {
        return $this->branch_role === 'owner';
    }

    public function isManager(): bool
    {
        return $this->branch_role === 'manager';
    }

    public function isMember(): bool
    {
        return $this->branch_role === 'member';
    }

    public function canManage(): bool
    {
        return in_array($this->branch_role, ['owner', 'manager']);
    }
}
