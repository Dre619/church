<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'organization_id',
        'type',
        'category_id',
        'name',
        'amount',
        'year',
        'month',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'year'   => 'integer',
            'month'  => 'integer',
        ];
    }

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Resolve the linked category based on type. */
    public function getCategoryModelAttribute(): ?Model
    {
        if (! $this->category_id) {
            return null;
        }

        return $this->type === 'income'
            ? PaymentCategory::find($this->category_id)
            : ExpenseCategory::find($this->category_id);
    }

    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }
}
