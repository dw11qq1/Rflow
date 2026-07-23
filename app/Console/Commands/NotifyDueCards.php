<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Notifications\CardDue;
use Illuminate\Console\Command;

class NotifyDueCards extends Command
{
    protected $signature = 'reflow:notify-due';

    protected $description = '通知临近到期（2 天内）与已逾期的卡片被指派成员（按 due_notified_at 去重）';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $soonUntil = now()->addDays(2)->endOfDay();

        // 逾期：due_date < 今天
        $overdue = Card::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereNull('due_notified_at')
            ->with('assignee', 'column.board')
            ->get();

        // 临近：今天 ~ 今天+2 天
        $soon = Card::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $soonUntil)
            ->whereNull('due_notified_at')
            ->with('assignee', 'column.board')
            ->get();

        $count = 0;

        foreach ($overdue as $card) {
            $this->notify($card, 'overdue');
            $count++;
        }
        foreach ($soon as $card) {
            $this->notify($card, 'soon');
            $count++;
        }

        $this->info("已发送 {$count} 条到期提醒（逾期 {$overdue->count()} / 临近 {$soon->count()}）。");

        return self::SUCCESS;
    }

    private function notify(Card $card, string $type): void
    {
        $board = $card->column?->board;
        if (! $board || ! $card->assignee) {
            // 无看板或无被指派者：仅标记已处理，避免反复扫到
            $card->update(['due_notified_at' => now()]);

            return;
        }

        $card->assignee->notify(new CardDue($board, $card, $type));
        $card->update(['due_notified_at' => now()]);
    }
}
