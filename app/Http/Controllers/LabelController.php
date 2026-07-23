<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Label;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function store(Request $request, Board $board): RedirectResponse
    {
        $this->authorize('view', $board);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $board->labels()->create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#5C5CD6',
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Board $board, Label $label): RedirectResponse
    {
        $this->authorize('view', $board);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $label->update($validated);

        return redirect()->back();
    }

    public function destroy(Board $board, Label $label): RedirectResponse
    {
        $this->authorize('view', $board);

        $label->delete();

        return redirect()->back();
    }
}
