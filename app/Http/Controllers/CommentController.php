<?php

namespace App\Http\Controllers;

use App\Events\BoardUpdated;
use App\Models\Board;
use App\Models\Card;
use App\Models\Comment;
use App\Notifications\CommentAdded;
use App\Notifications\MentionedInComment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    /**
     * 新增评论（任何成员可操作）。
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'card_id' => ['required', 'exists:cards,id'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $card = Card::findOrFail($validated['card_id']);
        $board = $card->column->board;
        $this->authorize('view', $board);

        $card->comments()->create([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        $actorName = (string) auth()->user()->name;

        // 通知被指派者（非评论者本人）
        if ($card->assignee_id && $card->assignee_id !== auth()->id()) {
            $card->assignee->notify(new CommentAdded($board, $card, $actorName));
        }

        // 解析 @提及 并通知被提及的看板成员
        $body = $card->comments()->latest()->first()->body;
        foreach ($board->members as $member) {
            if ($member->id === auth()->id()) {
                continue;
            }
            if (str_contains($body, '@' . $member->name)) {
                $excerpt = mb_strimwidth($body, 0, 80, '…');
                $member->notify(new MentionedInComment($board, $card, $actorName, $excerpt));
            }
        }

        event(new BoardUpdated($board, auth()->id()));

        // 返回 204：不触发整页看板 reload，避免干扰拖拽后的卡片布局（前端乐观追加评论）
        return response()->noContent();
    }

    public function destroy(Comment $comment): Response
    {
        $board = $comment->card->column->board;
        $this->authorize('view', $board);

        $comment->delete();

        // 返回 204：同上，避免整页 reload 导致看板卡片回到初始列
        return response()->noContent();
    }
}
