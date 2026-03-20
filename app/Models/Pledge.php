<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pledge extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'project_id',
        'amount',
        'fulfilled_amount',
        'pledge_date',
        'deadline',
        'status',
    ];
    protected $casts = [
        'pledge_date' => 'date', // Automatically casts to a Carbon instance
        'deadline' => 'date',   // Optional: If you have a deadline field
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class,'organization_id');
    }
    // Relationship: A pledge belongs to a user (donor)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Projects::class);
    }

    // Relationship: A pledge can have many donations
    public function donations()
    {
        return $this->hasMany(payments::class,'pledge_id');
    }

    // Accessor for the total amount paid
    public function getAmountPaidAttribute()
    {
        return $this->donations->sum('amount');
    }

    // Accessor for the remaining balance
    public function getBalanceAttribute()
    {
        return max(0, $this->amount - $this->amount_paid);
    }
}
