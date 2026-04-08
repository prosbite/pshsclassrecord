<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    /** @use HasFactory<\Database\Factories\TeacherFactory> */
    use HasFactory;

    protected $fillable = [
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'gender',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'photo',
        'status',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
