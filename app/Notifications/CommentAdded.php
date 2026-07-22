<?php

namespace App\Notifications;

use App\Models\Board;
use App\Models\Card;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 卡片收到新评论时通知该卡片的被指派者（评论者本人除外）。
 */
class CommentAdded extends Notification
{
    use Queueable;

    public function __construct(
        public Board $board,
        public Card $card,
        public string $byName,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'kind' => 'comment.added',
            'board_id' => $this->board->id,
            'board_slug' => $this->board->slug,
            'board_name' => $this->board->name,
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'by' => $this->byName,
        ];
    }
}
