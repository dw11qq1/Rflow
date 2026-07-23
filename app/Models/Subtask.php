<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subtask extends Model
{
    protected $fillable = ['card_id', 'title', 'is_complete', 'position'];

    protected function casts(): array
    {
        return [
            'is_complete' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
