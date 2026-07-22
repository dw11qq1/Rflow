<?php

namespace App\Notifications;

use App\Models\Board;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 当前用户被加入某个看板时通知他。
 */
class AddedToBoard extends Notification
{
    use Queueable;

    public function __construct(
        public Board $board,
        public string $byName,
        public string $role,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'kind' => 'board.member_added',
            'board_id' => $this->board->id,
            'board_slug' => $this->board->slug,
            'board_name' => $this->board->name,
            'by' => $this->byName,
            'role' => $this->role,
        ];
    }
}
