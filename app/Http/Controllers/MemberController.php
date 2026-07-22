<?php

namespace App\Http\Controllers;

use App\Events\BoardUpdated;
use App\Models\Board;
use App\Models\User;
use App\Notifications\AddedToBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MemberController extends Controller
{
    /**
     * 通过邮箱邀请已有用户加入看板（仅 owner / admin 可操作，被邀请者必须是已注册用户）。
     */
    public function store(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('manageMembers', $board);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:admin,member'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()->withErrors(['email' => __('members.userNotFound')]);
        }

        if ($board->owner_id === $user->id) {
            return back()->withErrors(['email' => __('members.isOwner')]);
        }

        if ($board->members()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['email' => __('members.alreadyMember')]);
        }

        $board->members()->attach($user->id, ['role' => $validated['role']]);

        $user->notify(new AddedToBoard($board, (string) auth()->user()->name, $validated['role']));

        event(new BoardUpdated($board, auth()->id()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('members.added')]);

        return back();
    }

    /**
     * 修改成员角色（owner 角色不可被修改）。
     */
    public function update(Request $request, Board $board, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $board);

        $validated = $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        if ($board->owner_id === $user->id) {
            return back()->withErrors(['role' => __('members.ownerRoleFixed')]);
        }

        $board->members()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        event(new BoardUpdated($board, auth()->id()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('members.roleUpdated')]);

        return back();
    }

    /**
     * 移除成员（看板拥有者不可被移除）。
     */
    public function destroy(Board $board, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $board);

        if ($board->owner_id === $user->id) {
            return back()->withErrors(['role' => __('members.cannotRemoveOwner')]);
        }

        $board->members()->detach($user->id);

        event(new BoardUpdated($board, auth()->id()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('members.removed')]);

        return back();
    }
}
