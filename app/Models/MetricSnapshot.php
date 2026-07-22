<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricSnapshot extends Model
{
    public $incrementing = false;

    protected $fillable = ['board_id', 'date', 'snapshot'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'snapshot' => 'array',   // json 列自动转成 PHP 数组
        ];
    }
    // 快照属于哪块板
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
}
