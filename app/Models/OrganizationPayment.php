<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationPayment extends Model
{
    //
    protected $fillable = [
        'organization_id',
        'plan_id',
        'amount',
        'payment_method',
        'transaction_id',
        'paid_at',
        'status',
    ];
    public function organization()
    {
        return $this->belongsTo(Organization::class,'organization_id','id');
    }
    public function plan()
    {
        return $this->belongsTo(Plan::class,'plan_id','id');
    }

}
