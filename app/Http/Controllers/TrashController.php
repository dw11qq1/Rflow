<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Card;
use App\Models\Column;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    /**
     * 回收站：当前用户拥有或参与的看板，以及这些看板下被软删除的列/卡片。
     * 仅展示已删除的项目（board/column/card 均走 SoftDeletes）。
     */
    public function index(): Response
    {
        $user = auth()->user();

        // 用户拥有或参与的看板 id（含已软删除的，以便其下列/卡片也能在回收站出现）
        $boardIds = Board::withTrashed()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        // 看板
        $boards = Board::onlyTrashed()
            ->whereIn('id', $boardIds)
            ->withCount(['cards' => fn ($q) => $q->withTrashed()])
            ->latest('deleted_at')
            ->get()
            ->map(fn (Board $b) => [
                'type' => 'board',
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'deleted_at' => $b->deleted_at,
                'cards_count' => $b->cards_count,
            ]);

        // 看板列
        $columns = Column::onlyTrashed()
            ->whereIn('board_id', $boardIds)
            ->with(['board' => fn ($q) => $q->withTrashed()->select('id', 'name')])
            ->withCount(['cards' => fn ($q) => $q->withTrashed()])
            ->latest('deleted_at')
            ->get()
            ->map(fn (Column $c) => [
                'type' => 'column',
                'id' => $c->id,
                'name' => $c->name,
                'deleted_at' => $c->deleted_at,
                'board_id' => $c->board_id,
                'board_name' => $c->board?->name,
                'cards_count' => $c->cards_count,
            ]);

        // 卡片
        $cards = Card::onlyTrashed()
            ->whereIn('board_id', $boardIds)
            ->with([
                'board' => fn ($q) => $q->withTrashed()->select('id', 'name'),
                'column' => fn ($q) => $q->withTrashed()->select('id', 'name'),
            ])
            ->latest('deleted_at')
            ->get()
            ->map(fn (Card $c) => [
                'type' => 'card',
                'id' => $c->id,
                'name' => $c->title,
                'deleted_at' => $c->deleted_at,
                'board_id' => $c->board_id,
                'board_name' => $c->board?->name,
                'column_name' => $c->column?->name,
            ]);

        $items = $boards->concat($columns)->concat($cards)
            ->sortByDesc('deleted_at')
            ->values();

        return Inertia::render('Trash', [
            'items' => $items,
        ]);
    }

    /**
     * 回收站内看板的只读预览：用 withTrashed 加载，避免软删除看板数据为空。
     */
    public function showBoard(string $slug): Response
    {
        $board = Board::withTrashed()->where('slug', $slug)->firstOrFail();
        $this->authorize('view', $board);

        $board->load([
            'columns' => fn ($q) => $q->withTrashed()->orderBy('position'),
            'columns.cards' => fn ($q) => $q->withTrashed()->orderBy('position'),
            'columns.cards.assignee' => fn ($q) => $q->select('id', 'name'),
            'members' => fn ($q) => $q->select('users.id', 'users.name')->withPivot('role'),
        ]);

        return Inertia::render('Trash/Board', [
            'board' => $board,
            'canManage' => $board->owner_id === auth()->id(),
        ]);
    }

    /**
     * 恢复被软删除的列或卡片（仅看板拥有者可操作）。
     */
    public function restore(string $type, int $id): RedirectResponse
    {
        [$model, $board] = $this->resolveTrashed($type, $id);
        $this->authorize('delete', $board);

        $model->restore();

        return redirect()->route('trash');
    }

    /**
     * 彻底删除列或卡片（仅看板拥有者可操作；列会级联删除其下卡片）。
     */
    public function force(string $type, int $id): RedirectResponse
    {
        [$model, $board] = $this->resolveTrashed($type, $id);
        $this->authorize('delete', $board);

        $model->forceDelete();

        return redirect()->route('trash');
    }

    /**
     * 按类型解析软删除的模型，并返回其所属看板（用于权限校验）。
     */
    protected function resolveTrashed(string $type, int $id): array
    {
        if ($type === 'column') {
            $model = Column::withTrashed()->findOrFail($id);

            return [$model, $model->board()->withTrashed()->first()];
        }

        if ($type === 'card') {
            $model = Card::withTrashed()->findOrFail($id);

            return [$model, $model->board()->withTrashed()->first()];
        }

        abort(404);
    }
}
