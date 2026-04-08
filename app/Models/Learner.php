<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;


class Learner extends Model
{
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'gender',
        'status',
        'email_verified_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function latestEnrollment()
    {
        return $this->hasOne(Enrollment::class)->latestOfMany();
    }

    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(Assessment::class, 'assessment_learners')
            ->withPivot('score')
            ->withTimestamps();
    }
}
