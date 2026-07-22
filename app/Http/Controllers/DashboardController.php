<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Board;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $boards = Board::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->withCount('cards')
            ->withCount('columns')
            ->with('members:id,name')
            ->latest()
            ->get();

        // 跨所有可见看板的去重协作者数
        $memberIds = $boards->pluck('members')->flatten()->pluck('id')->unique();

        // 复盘概览：每块看板的最新快照 + 历史趋势
        $retro = $boards->map(function (Board $board) {
            $snapshots = $board->metricSnapshots()->orderBy('date')->get(['date', 'snapshot']);
            $latest = $snapshots->last();

            return [
                'slug' => $board->slug,
                'name' => $board->name,
                'total' => $latest ? ($latest->snapshot['total'] ?? $board->cards_count) : $board->cards_count,
                'trend' => $snapshots->map(fn ($s) => [
                    'date' => substr((string) $s->date, 5),
                    'total' => $s->snapshot['total'] ?? 0,
                ])->all(),
            ];
        })->all();

        // 全局动态：跨所有可见看板的最近活动
        $activities = Activity::query()
            ->whereIn('board_id', $boards->pluck('id')->all())
            ->with('user:id,name')
            ->with('board:id,slug,name')
            ->latest()
            ->limit(15)
            ->get();

        return Inertia::render('Dashboard', [
            'boards' => $boards->take(5)->values(),
            'stats' => [
                'boards' => $boards->count(),
                'cards' => $boards->sum('cards_count'),
                'members' => $memberIds->count(),
            ],
            'retro' => $retro,
            'activities' => $activities,
        ]);
    }
}
