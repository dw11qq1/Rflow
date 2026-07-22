<?php

namespace App\Notifications;

use App\Models\Board;
use App\Models\Card;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 评论中 @提及 某成员时通知被提及者。
 */
class MentionedInComment extends Notification
{
    use Queueable;

    public function __construct(
        public Board $board,
        public Card $card,
        public string $byName,
        public string $commentExcerpt,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'kind' => 'comment.mentioned',
            'board_id' => $this->board->id,
            'board_slug' => $this->board->slug,
            'board_name' => $this->board->name,
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'by' => $this->byName,
            'excerpt' => $this->commentExcerpt,
        ];
    }
}
