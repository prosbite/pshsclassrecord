<?php

namespace App\Models;

use Database\Factories\QuarterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quarter extends Model
{
    /** @use HasFactory<QuarterFactory> */
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'quarter',
        'school_year_id',
        'status',
    ];

    protected $appends = [
        'name',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function getNameAttribute(): string
    {
        return match ((int) $this->quarter) {
            1 => '1st Quarter',
            2 => '2nd Quarter',
            3 => '3rd Quarter',
            4 => '4th Quarter',
            default => 'Quarter '.$this->quarter,
        };
    }

    public static function current(): ?self
    {
        $schoolYear = SchoolYear::current();

        if (! $schoolYear) {
            return null;
        }

        return self::query()
            ->where('school_year_id', $schoolYear->id)
            ->where('status', 'active')
            ->orderBy('quarter')
            ->first();
    }

    public function activate(): void
    {
        self::query()
            ->where('school_year_id', $this->school_year_id)
            ->where('id', '!=', $this->id)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        if ($this->status !== 'active') {
            $this->forceFill(['status' => 'active'])->save();
        }
    }
}
