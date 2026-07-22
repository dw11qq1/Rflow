<?php

namespace App\Console\Commands;

use App\Models\Board;
use App\Models\MetricSnapshot;
use Illuminate\Console\Command;

class SnapshotBoards extends Command
{
    protected $signature = 'reflow:snapshot {--date=today : 快照日期 (Y-m-d)}';

    protected $description = '为所有看板生成本日指标快照（用于复盘趋势图）';

    public function handle(): int
    {
        $date = $this->option('date') === 'today'
            ? now()->toDateString()
            : $this->option('date');

        $boards = Board::with(['columns.cards'])->get();

        if ($boards->isEmpty()) {
            $this->warn('没有看板可供快照。');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($boards->count());

        foreach ($boards as $board) {
            $perColumn = $board->columns
                ->mapWithKeys(fn ($column) => [$column->name => $column->cards->count()])
                ->toArray();

            $snapshot = $board->metricSnapshots()->where('date', $date)->first();

            $payload = [
                'total' => $board->cards()->count(),
                'per_column' => $perColumn,
            ];

            if ($snapshot) {
                \Illuminate\Support\Facades\DB::table('metric_snapshots')
                    ->where('board_id', $board->id)
                    ->where('date', $date)
                    ->update([
                        'snapshot' => json_encode($payload),
                        'updated_at' => now(),
                    ]);
            } else {
                $board->metricSnapshots()->create([
                    'date' => $date,
                    'snapshot' => $payload,
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("已为 {$boards->count()} 块看板生成 {$date} 的快照。");

        return self::SUCCESS;
    }
}
