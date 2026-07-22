<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;

class CardObserver
{
    public function created(Card $card): void
    {
        if (!Auth::check()) {
            return;
        }

        $card->board->activities()->create([
            'user_id' => Auth::id(),
            'type' => 'card.created',
            'subject_type' => Card::class,
            'subject_id' => $card->id,
            'payload' => ['title' => $card->title, 'column_id' => $card->column_id],
        ]);
    }

    public function updated(Card $card): void
    {
        if (!Auth::check()) {
            return;
        }

        $moved = $card->isDirty('column_id');

        $card->board->activities()->create([
            'user_id' => Auth::id(),
            'type' => $moved ? 'card.moved' : 'card.updated',
            'subject_type' => Card::class,
            'subject_id' => $card->id,
            'payload' => $moved
                ? ['title' => $card->title, 'from' => $card->getOriginal('column_id'), 'to' => $card->column_id]
                : ['title' => $card->title, 'changes' => array_keys($card->getDirty())],
        ]);
    }

    public function deleted(Card $card): void
    {
        if (!Auth::check()) {
            return;
        }

        $board = $card->board;
        if (!$board) {
            return;
        }

        $board->activities()->create([
            'user_id' => Auth::id(),
            'type' => 'card.deleted',
            'subject_type' => Card::class,
            'subject_id' => $card->id,
            'payload' => ['title' => $card->title],
        ]);
    }
}
