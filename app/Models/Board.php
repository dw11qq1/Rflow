<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Board extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'owner_id', 'is_archived'];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean'];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Board $board): void {
            if (empty($board->slug)) {
                $board->slug = Str::slug($board->name) . '-' . Str::random(4);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(MetricSnapshot::class);
    }
}
