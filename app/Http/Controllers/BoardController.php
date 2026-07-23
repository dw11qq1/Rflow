<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    /**
     * 看板列表：当前用户拥有或参与的看板。
     */
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

        return Inertia::render('Boards/Index', [
            'boards' => $boards,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Boards/Create');
    }

    /**
     * 创建看板：自动附带默认三列与 owner 成员关系。
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'max:50'],
        ]);

        $board = Board::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'owner_id' => auth()->id(),
        ]);

        $board->members()->attach(auth()->id(), ['role' => 'owner']);

        $columnNames = $validated['columns'] ?? ['To Do', 'Doing', 'Done'];
        foreach ($columnNames as $i => $name) {
            $board->columns()->create([
                'name' => $name,
                'position' => $i,
            ]);
        }

        return redirect()->route('boards.show', $board);
    }

    /**
     * 展示单块看板（含列、卡片、成员、最近活动）。
     */
    public function show(Board $board): Response
    {
        $this->authorize('view', $board);

        $board->load([
            'columns' => fn ($q) => $q->orderBy('position'),
            'columns.cards' => fn ($q) => $q->orderBy('position'),
            'columns.cards.assignee' => fn ($q) => $q->select('id', 'name'),
            'columns.cards.labels' => fn ($q) => $q->select('id', 'name', 'color'),
            'columns.cards.subtasks' => fn ($q) => $q->orderBy('position'),
            'columns.cards.comments' => fn ($q) => $q->with('user:id,name')->latest(),
            'labels' => fn ($q) => $q->orderBy('name'),
            'members' => fn ($q) => $q->select('users.id', 'users.name')->withPivot('role'),
            'activities' => fn ($q) => $q->with('user:id,name')->latest()->limit(50),
        ]);

        return Inertia::render('Board/Show', [
            'board' => $board,
        ]);
    }

    public function edit(Board $board): Response
    {
        $this->authorize('update', $board);

        return Inertia::render('Boards/Edit', [
            'board' => $board,
        ]);
    }

    public function update(Request $request, Board $board): RedirectResponse
    {
        $this->authorize('update', $board);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_archived' => ['sometimes', 'boolean'],
        ]);

        $board->update($validated);

        return redirect()->route('boards.show', $board);
    }

    public function destroy(Board $board): RedirectResponse
    {
        $this->authorize('delete', $board);

        // 软删除：移入回收站，可恢复
        $board->delete();

        return redirect()->route('boards.index');
    }

    /**
     * 从回收站恢复看板。
     */
    public function restore(string $board): RedirectResponse
    {
        $board = Board::withTrashed()->where('slug', $board)->firstOrFail();
        $this->authorize('delete', $board);

        $board->restore();

        return redirect()->route('trash');
    }

    /**
     * 从回收站彻底删除看板（含列/卡片/活动）。
     */
    public function forceDelete(string $board): RedirectResponse
    {
        $board = Board::withTrashed()->where('slug', $board)->firstOrFail();
        $this->authorize('delete', $board);

        $board->forceDelete();

        return redirect()->route('trash');
    }

    /**
     * 复盘仪表盘：指标 + 活动流 + 历史快照。
     */
    public function retro(Board $board): Response
    {
        $this->authorize('view', $board);

        $board->load([
            'columns' => fn ($q) => $q->orderBy('position'),
            'columns.cards' => fn ($q) => $q->orderBy('position'),
        ]);

        $columns = $board->columns;
        $totalCards = $board->cards()->count();
        $lastColumn = $columns->last();
        $doneCount = $lastColumn ? $lastColumn->cards->count() : 0;

        $perColumn = $columns->map(fn (Column $c) => [
            'name' => $c->name,
            'count' => $c->cards->count(),
        ])->toArray();

        // 上线兜底：访问复盘页时若当天尚无快照，自动为本看板补一条。
        // 这样即使计划任务（reflow:snapshot）未部署，趋势图也能从今日起自动累积。
        // 注意：metric_snapshots 表无自增 id 主键（board_id+date 复合唯一），
        // 因此“已存在”时必须走原生 DB 更新，不能用 Eloquent updateOrCreate（会拼出 where id is null）。
        $today = now()->toDateString();
        $payload = [
            'total' => $totalCards,
            'per_column' => $columns->mapWithKeys(
                fn (Column $c) => [$c->name => $c->cards->count()]
            )->toArray(),
        ];
        if ($board->metricSnapshots()->where('date', $today)->exists()) {
            DB::table('metric_snapshots')
                ->where('board_id', $board->id)
                ->where('date', $today)
                ->update(['snapshot' => json_encode($payload), 'updated_at' => now()]);
        } else {
            $board->metricSnapshots()->create(['date' => $today, 'snapshot' => $payload]);
        }

        $activities = $board->activities()
            ->with('user:id,name')
            ->latest()
            ->limit(40)
            ->get();

        $snapshots = $board->metricSnapshots()
            ->orderBy('date')
            ->get(['date', 'snapshot']);

        return Inertia::render('Board/Retro', [
            'board' => $board,
            'metrics' => [
                'total' => $totalCards,
                'done' => $doneCount,
                'wip' => $totalCards - $doneCount,
            ],
            'perColumn' => $perColumn,
            'activities' => $activities,
            'snapshots' => $snapshots,
        ]);
    }
}
