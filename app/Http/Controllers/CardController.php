<?php

namespace App\Http\Controllers;

use App\Events\BoardUpdated;
use App\Models\Board;
use App\Models\Card;
use App\Models\Column;
use App\Models\User;
use App\Notifications\CardAssigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CardController extends Controller
{
    /**
     * 新建卡片（任何成员可操作）。
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'column_id' => ['required', 'exists:columns,id'],
            'title' => ['required', 'string', 'max:200'],
        ]);

        $column = Column::findOrFail($validated['column_id']);
        $board = $column->board;
        $this->authorize('view', $board);

        $board->cards()->create([
            'column_id' => $column->id,
            'title' => $validated['title'],
            'position' => $column->cards()->count(),
            'created_by' => auth()->id(),
        ]);

        event(new BoardUpdated($board, auth()->id()));

        return redirect()->back();
    }

    public function update(Request $request, Card $card): RedirectResponse
    {
        $board = $card->column->board;
        $this->authorize('view', $board);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['integer', 'exists:labels,id'],
        ]);

        $oldAssignee = $card->assignee_id;
        $card->update($validated);

        // 标签：仅当请求显式携带 labels 字段时才同步，避免更新其它字段时清空标签
        if (array_key_exists('labels', $validated)) {
            $card->labels()->sync($validated['labels'] ?? []);
        }

        // 指派变更时通知新的被指派者（本人除外）
        if (array_key_exists('assignee_id', $validated)
            && $validated['assignee_id'] !== $oldAssignee
            && $validated['assignee_id']) {
            $assignee = User::find($validated['assignee_id']);
            if ($assignee && $assignee->id !== auth()->id()) {
                $assignee->notify(new CardAssigned($board, $card, (string) auth()->user()->name));
            }
        }

        event(new BoardUpdated($board, auth()->id()));

        return redirect()->back();
    }

    public function destroy(Card $card): RedirectResponse
    {
        $board = $card->column->board;
        $this->authorize('view', $board);

        $card->delete();

        event(new BoardUpdated($board, auth()->id()));

        return redirect()->back();
    }

    /**
     * 拖拽移动：接收受影响的列及其卡片新顺序，逐条 save 以触发活动记录。
     * items: [{ column_id: int, ids: [int,...] }, ...]
     */
    public function move(Request $request): Response
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.column_id' => ['required', 'integer', 'exists:columns,id'],
            'items.*.ids' => ['present', 'array'],
            'items.*.ids.*' => ['integer', 'exists:cards,id'],
        ]);

        $boardIds = [];

        foreach ($validated['items'] as $item) {
            $column = Column::findOrFail($item['column_id']);
            $boardIds[] = $column->board_id;

            foreach ($item['ids'] as $position => $cardId) {
                $card = Card::findOrFail($cardId);
                if ($card->column_id !== $column->id || $card->position !== $position) {
                    $card->column_id = $column->id;
                    $card->position = $position;
                    $card->save(); // 触发 CardObserver -> 记录 card.moved
                }
            }
        }

        // 权限校验：所有涉及的列必须属于同一块且用户可访问
        $board = Board::findOrFail(array_unique($boardIds)[0]);
        $this->authorize('view', $board);

        // 返回 204：不触发整页看板 reload，避免与拖拽乐观更新冲突导致卡片被重置到初始列
        event(new BoardUpdated($board, auth()->id()));

        return response()->noContent();
    }
}
