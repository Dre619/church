<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'category_id',
        'title',
        'amount',
        'description',
        'image_url',
        'expense_date',
        'reconciled',
        'reconciled_at',
        'reconciled_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'expense_date'  => 'date',
            'reconciled'    => 'boolean',
            'reconciled_at' => 'datetime',
        ];
    }
    // Relationship to ExpenseCategory
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class,'category_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class,'organization_id');
    }
}
