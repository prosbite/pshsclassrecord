<?php

namespace App\Models;

use Database\Factories\SchoolYearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolYear extends Model
{
    /** @use HasFactory<SchoolYearFactory> */
    use HasFactory;

    protected $fillable = [
        'year_start',
        'year_end',
        'status',
    ];

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public static function current()
    {
        return self::where('status', 'active')->first();
    }
}
