<?php

namespace App\Http\Controllers;

use App\Events\BoardUpdated;
use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ColumnController extends Controller
{
    /**
     * 新建列（结构变更，需 update 权限）。
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'board_id' => ['required', 'exists:boards,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $board = Board::findOrFail($validated['board_id']);
        $this->authorize('update', $board);

        $board->columns()->create([
            'name' => $validated['name'],
            'position' => $board->columns()->count(),
        ]);

        event(new BoardUpdated($board, auth()->id()));

        return redirect()->back();
    }

    public function update(Request $request, Column $column): RedirectResponse
    {
        $this->authorize('update', $column->loadMissing('board')->board);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'wip_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $column->update($validated);

        event(new BoardUpdated($board, auth()->id()));

        return redirect()->back();
    }

    public function destroy(Column $column): RedirectResponse
    {
        $board = $column->loadMissing('board')->board;
        $this->authorize('update', $board);

        // 软删除：移入回收站（列模型已启用 SoftDeletes）
        $column->delete();

        event(new BoardUpdated($board, auth()->id()));

        return redirect()->back();
    }

    /**
     * 列排序（拖拽后持久化）。
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'board_id' => ['required', 'exists:boards,id'],
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:columns,id'],
        ]);

        $board = Board::findOrFail($validated['board_id']);
        $this->authorize('update', $board);

        foreach ($validated['ordered_ids'] as $position => $id) {
            Column::where('id', $id)
                ->where('board_id', $board->id)
                ->update(['position' => $position]);
        }

        return redirect()->back();
    }
}
