<?php

namespace App\Notifications;

use App\Models\Board;
use App\Models\Card;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 卡片被指派给某成员时通知该成员。
 */
class CardAssigned extends Notification
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
            'kind' => 'card.assigned',
            'board_id' => $this->board->id,
            'board_slug' => $this->board->slug,
            'board_name' => $this->board->name,
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'by' => $this->byName,
        ];
    }
}
