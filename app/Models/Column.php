<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Column extends Model
{
    use SoftDeletes;

    protected $fillable = ['board_id','name','position','wip_limit'];
    
    protected function casts() : array 
    {
        return[
            'position'=>'integer',
            'wip_limit'=>'integer'
        ];
    }
    public function board():BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
    public function cards():HasMany
    {
        return $this->hasMany(Card::class);
    }
}
