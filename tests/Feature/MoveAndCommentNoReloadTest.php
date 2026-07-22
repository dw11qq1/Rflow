<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Card;
use App\Models\Column;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 回归测试：拖拽移动(move)与评论增删(comment)必须返回 204 而不触发整页看板 reload，
 * 否则 Inertia 的 reload 会用服务端数据覆盖前端乐观更新，导致卡片被重置回初始列。
 */
class MoveAndCommentNoReloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_returns_204_and_persists_without_reload(): void
    {
        $owner = User::create(['name' => 'O', 'email' => 'o@x.com', 'password' => 'p']);
        $member = User::create(['name' => 'M', 'email' => 'm@x.com', 'password' => 'p']);
        $board = new Board(['name' => 'B', 'slug' => 'b-' . \Illuminate\Support\Str::random(4), 'owner_id' => $owner->id]);
        $board->save();
        $board->members()->attach([$owner->id => ['role' => 'owner'], $member->id => ['role' => 'member']]);

        $c1 = new Column(['board_id' => $board->id, 'name' => 'A', 'position' => 0]); $c1->save();
        $c2 = new Column(['board_id' => $board->id, 'name' => 'B', 'position' => 1]); $c2->save();

        $k1 = new Card(['board_id' => $board->id, 'column_id' => $c1->id, 'title' => 'k1', 'position' => 0, 'created_by' => $owner->id]); $k1->save();
        $k2 = new Card(['board_id' => $board->id, 'column_id' => $c1->id, 'title' => 'k2', 'position' => 1, 'created_by' => $owner->id]); $k2->save();
        $k3 = new Card(['board_id' => $board->id, 'column_id' => $c1->id, 'title' => 'k3', 'position' => 2, 'created_by' => $owner->id]); $k3->save();

        $items = [
            ['column_id' => $c1->id, 'ids' => [$k1->id, $k3->id]],
            ['column_id' => $c2->id, 'ids' => [$k2->id]],
        ];

        $resp = $this->actingAs($member)->post('/cards/move', ['items' => $items]);

        $this->assertSame(204, $resp->getStatusCode(), 'move 必须返回 204，不得整页 reload');
        $this->assertDatabaseHas('cards', ['id' => $k2->id, 'column_id' => $c2->id]);
    }

    public function test_comment_store_and_destroy_return_204(): void
    {
        $owner = User::create(['name' => 'O', 'email' => 'o@x.com', 'password' => 'p']);
        $member = User::create(['name' => 'M', 'email' => 'm@x.com', 'password' => 'p']);
        $board = new Board(['name' => 'B', 'slug' => 'b-' . \Illuminate\Support\Str::random(4), 'owner_id' => $owner->id]);
        $board->save();
        $board->members()->attach([$owner->id => ['role' => 'owner'], $member->id => ['role' => 'member']]);

        $col = new Column(['board_id' => $board->id, 'name' => 'A', 'position' => 0]); $col->save();
        $card = new Card(['board_id' => $board->id, 'column_id' => $col->id, 'title' => 'k', 'position' => 0, 'created_by' => $owner->id]); $card->save();

        $store = $this->actingAs($member)->post('/comments', ['card_id' => $card->id, 'body' => 'hi']);
        $this->assertSame(204, $store->getStatusCode(), 'comment store 必须返回 204');

        $comment = \App\Models\Comment::where('card_id', $card->id)->firstOrFail();
        $destroy = $this->actingAs($member)->delete('/comments/' . $comment->id);
        $this->assertSame(204, $destroy->getStatusCode(), 'comment destroy 必须返回 204');
    }
}
