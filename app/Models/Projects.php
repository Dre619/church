<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Projects extends Model
{
    use HasFactory;
    protected $fillable = [
        "organization_id",
        "created_by",
        "project_title",
        "description",
        "project_budget",
    ] ;

    public function pledges()
    {
        return $this->hasMany(Pledge::class, 'project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class,'created_by');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class,'organization_id');
    }
}
