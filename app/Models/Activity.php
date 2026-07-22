<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    protected $fillable = ['board_id','user_id','type','subject_type','subject_id', 'payload'];
    
    protected function casts():array
    {
        return[
            'payload'=>'array',
        ];
    }

     // 事件属于哪块板
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    // 谁触发了这个事件
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 多态关联：事件的对象（可能是 Card / Column / Board）
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
