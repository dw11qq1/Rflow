<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Column;
use Illuminate\Support\Facades\Auth;

class ColumnObserver
{
    public function created(Column $column): void
    {
        if (!Auth::check()) {
            return;
        }

        $column->board->activities()->create([
            'user_id' => Auth::id(),
            'type' => 'column.created',
            'subject_type' => Column::class,
            'subject_id' => $column->id,
            'payload' => ['name' => $column->name],
        ]);
    }

    public function updated(Column $column): void
    {
        if (!Auth::check()) {
            return;
        }

        $column->board->activities()->create([
            'user_id' => Auth::id(),
            'type' => 'column.updated',
            'subject_type' => Column::class,
            'subject_id' => $column->id,
            'payload' => ['name' => $column->name, 'changes' => array_keys($column->getDirty())],
        ]);
    }

    public function deleted(Column $column): void
    {
        if (!Auth::check()) {
            return;
        }

        $board = $column->board;
        if (!$board) {
            return;
        }

        $board->activities()->create([
            'user_id' => Auth::id(),
            'type' => 'column.deleted',
            'subject_type' => Column::class,
            'subject_id' => $column->id,
            'payload' => ['name' => $column->name],
        ]);
    }
}
