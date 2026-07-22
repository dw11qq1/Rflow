<?php

use App\Models\Board;
use Illuminate\Support\Facades\Broadcast;

/*
 * 实时协作频道（启用 Soketi + Laravel Echo 后生效）。
 * 详见 README：运行 soketi 并在前端启用 useBoardRealtime 组合式函数。
 */

// 在线成员（presence 频道）：返回当前用户基本信息，非成员返回 false
// 注意：此处注册名不带 presence- 前缀。Laravel 鉴权时会先对频道名做
// normalizeChannelName()（去掉 presence- 前缀）再去匹配，与官方 Pusher 约定一致。
Broadcast::channel('board.{slug}', function ($user, $slug) {
    $board = Board::where('slug', $slug)->first();

    if (! $board) {
        return false;
    }

    if ($board->owner_id !== $user->id && ! $board->members()->where('user_id', $user->id)->exists()) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
