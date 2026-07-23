<?php

namespace App\Http\Controllers;

use App\Events\BoardUpdated;
use App\Models\Board;
use App\Models\Card;
use App\Models\Subtask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubtaskController extends Controller
{
    private function boardOf(Card $card): Board
    {
        return $card->column->board;
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'card_id' => ['required', 'exists:cards,id'],
            'title' => ['required', 'string', 'max:200'],
        ]);

        $card = Card::findOrFail($validated['card_id']);
        $board = $this->boardOf($card);
        $this->authorize('view', $board);

        $card->subtasks()->create([
            'title' => $validated['title'],
            'position' => $card->subtasks()->count(),
        ]);

        event(new BoardUpdated($board, auth()->id()));

        return redirect()->back();
    }

    public function update(Request $request, Subtask $subtask): RedirectResponse
    {
        $board = $this->boardOf($subtask->card);
        $this->authorize('view', $board);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'is_complete' => ['sometimes', 'boolean'],
        ]);

        $subtask->update($validated);

        event(new BoardUpdated($board, auth()->id()));

        return redirect()->back();
    }

    public function destroy(Subtask $subtask): RedirectResponse
    {
        $board = $this->boardOf($subtask->card);
        $this->authorize('view', $board);

        $subtask->delete();

        event(new BoardUpdated($board, auth()->id()));

        return redirect()->back();
    }

    /**
     * 重排子任务顺序：{ ids: [int,...] }，按数组下标写入 position。
     */
    public function reorder(Request $request): Response
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:subtasks,id'],
        ]);

        $first = Subtask::findOrFail($validated['ids'][0]);
        $board = $this->boardOf($first->card);
        $this->authorize('view', $board);

        foreach ($validated['ids'] as $position => $id) {
            Subtask::where('id', $id)->update(['position' => $position]);
        }

        event(new BoardUpdated($board, auth()->id()));

        return response()->noContent();
    }
}
