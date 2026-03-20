<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'onboarding_completed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'onboarding_completed_at'  => 'datetime',
            'password'                 => 'hashed',
        ];
    }

    /**
     * Returns the OrganizationUser for the currently active branch (session-aware).
     * Falls back to the first org when no branch is selected.
     */
    public function myOrganization()
    {
        $currentOrgId = session('current_org_id');

        return $this->hasOne(OrganizationUser::class, 'user_id')
            ->when(
                $currentOrgId,
                fn ($q) => $q->where('organization_id', $currentOrgId),
                fn ($q) => $q->orderByRaw("FIELD(branch_role, 'owner', 'manager', 'member')")
            );
    }

    public function myOrganizations()
    {
        return $this->hasMany(OrganizationUser::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payments::class,"user_id","id");
    }

    public function pledges()
    {
        return $this->hasMany(Pledge::class,"user_id","id");
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
