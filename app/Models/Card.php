<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'board_id', 'column_id', 'title', 'description', 'position',
        'assignee_id', 'created_by', 'due_date', 'priority', 'color',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'due_date' => 'date',
            'due_notified_at' => 'datetime',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
    }
}
