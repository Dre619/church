<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id','name', 'description'];

    // Relationship to Expenses
    public function expenses()
    {
        return $this->hasMany(Expense::class,'category_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class,'organization_id');
    }
}
