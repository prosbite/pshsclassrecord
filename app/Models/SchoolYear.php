<?php

namespace App\Models;

use Database\Factories\SchoolYearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolYear extends Model
{
    /** @use HasFactory<SchoolYearFactory> */
    use HasFactory;

    protected $fillable = [
        'year_start',
        'year_end',
        'status',
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function quarters(): HasMany
    {
        return $this->hasMany(Quarter::class)->orderBy('quarter');
    }

    public static function current(): ?self
    {
        return self::query()
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
    }

    public function activate(): void
    {
        self::query()
            ->where('id', '!=', $this->id)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        if ($this->status !== 'active') {
            $this->forceFill(['status' => 'active'])->save();
        }
    }
}
