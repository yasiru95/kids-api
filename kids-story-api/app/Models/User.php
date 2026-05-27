<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [

        'name',
        'email',
        'password',
        'gender',

    ];

    protected $hidden = [

        'password',
        'remember_token',

    ];

        public function Payment()
        {
        return $this->hasMany(Payment::class);
        }

        public function HasActiveSubscription()
        {
        return $this->Payment()

        ->where('status', 'paid')

        ->where('end_date', '>', now())

        ->exists();
        }
}