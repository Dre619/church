<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
    //
    protected $fillable = [
        'organization_id',
        'user_id',
        'pledge_id',
        'name',
        'amount',
        'category_id',
        'other',
        'payment_method',
        'transaction_id',
        'donation_date',
        'reconciled',
        'reconciled_at',
        'reconciled_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'donation_date' => 'date',
            'reconciled'    => 'boolean',
            'reconciled_at' => 'datetime',
        ];
    }

    // Relationship: A donation belongs to a pledge (optional)
    public function pledge()
    {
        return $this->belongsTo(Pledge::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class,'organization_id');
    }

    public function category()
    {
        return $this->belongsTo(PaymentCategory::class,'category_id');
    }

    // Relationship: A donation belongs to a user (donor)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
