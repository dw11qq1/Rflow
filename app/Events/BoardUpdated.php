<?php

namespace App\Events;

use App\Models\Board;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 看板内容发生变更时广播（即时，不依赖队列）。
 *
 * 前端 useBoardRealtime 监听 `.board.updated` 后增量刷新 board 属性，
 * 从而实现多人实时协同（卡片/列/评论/成员的增删改对所有在线成员即时可见）。
 */
class BoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Board $board,
        public ?int $byUserId = null,
    ) {}

    /**
     * 广播到对应看板的 presence 频道（仅看板成员可订阅）。
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('board.' . $this->board->slug),
        ];
    }

    /**
     * 事件名（前端 listen 时对应 '.board.updated'）。
     */
    public function broadcastAs(): string
    {
        return 'board.updated';
    }

    /**
     * 广播载荷：前端仅用它判断是否需要刷新，不依赖具体字段。
     */
    public function broadcastWith(): array
    {
        return [
            'board_id' => $this->board->id,
            'by' => $this->byUserId,
        ];
    }
}
