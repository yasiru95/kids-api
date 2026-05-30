<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [

        'user_id',

        'payment_id',

        'transaction_id',

        'amount',

        'currency',

        'payment_method',

        'status',

        'is_subscription',

        'start_date',

        'end_date',

       

    ];

 

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    public function User()
    {
        return $this->belongsTo(User::class);
    }
}