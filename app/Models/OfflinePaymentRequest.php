<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflinePaymentRequest extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'category_id',
        'amount',
        'reference',
        'proof_path',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(PaymentCategory::class, 'category_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payment()
    {
        return $this->belongsTo(Payments::class, 'payment_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
