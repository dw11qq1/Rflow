<?php

namespace App\Notifications;

use App\Models\Board;
use App\Models\Card;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 卡片临近到期（soon）或已逾期（overdue）时通知被指派成员。
 * 去重由 cards.due_notified_at 控制，避免重复提醒。
 */
class CardDue extends Notification
{
    use Queueable;

    public function __construct(
        public Board $board,
        public Card $card,
        public string $type, // 'soon' | 'overdue'
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'kind' => 'card.due',
            'type' => $this->type,
            'board_id' => $this->board->id,
            'board_slug' => $this->board->slug,
            'board_name' => $this->board->name,
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'due_date' => $this->card->due_date?->toDateString(),
        ];
    }
}
