<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoardPolicy
{
    use HandlesAuthorization;

    /**
     * 任何登录用户都可以创建看板。
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * 看板拥有者或成员可查看。
     */
    public function view(User $user, Board $board): bool
    {
        if ($board->owner_id === $user->id) {
            return true;
        }

        return $board->members()->where('user_id', $user->id)->exists();
    }

    /**
     * 拥有者或管理员（非普通成员）可编辑。
     */
    public function update(User $user, Board $board): bool
    {
        if ($board->owner_id === $user->id) {
            return true;
        }

        return $board->members()
            ->where('user_id', $user->id)
            ->where('role', '!=', 'member')
            ->exists();
    }

    /**
     * 管理成员（邀请/改角色/移除）：与 update 同权——拥有者或管理员。
     */
    public function manageMembers(User $user, Board $board): bool
    {
        return $this->update($user, $board);
    }

    /**
     * 仅拥有者可删除。
     */
    public function delete(User $user, Board $board): bool
    {
        return $board->owner_id === $user->id;
    }
}
