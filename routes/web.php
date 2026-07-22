<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TrashController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/boards')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 通知中心
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // 看板
    Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
    Route::get('boards/create', [BoardController::class, 'create'])->name('boards.create');
    Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
    Route::get('boards/{board:slug}/retro', [BoardController::class, 'retro'])->name('boards.retro');
    Route::get('boards/{board:slug}/edit', [BoardController::class, 'edit'])->name('boards.edit');
    Route::patch('boards/{board:slug}', [BoardController::class, 'update'])->name('boards.update');
    Route::delete('boards/{board:slug}', [BoardController::class, 'destroy'])->name('boards.destroy');
    Route::post('boards/{board}/restore', [BoardController::class, 'restore'])->name('boards.restore');
    Route::delete('boards/{board}/force', [BoardController::class, 'forceDelete'])->name('boards.force');
    Route::get('boards/{board:slug}', [BoardController::class, 'show'])->name('boards.show');

    // 回收站 / 模板
    Route::get('trash', [TrashController::class, 'index'])->name('trash');
    Route::get('trash/boards/{board:slug}', [TrashController::class, 'showBoard'])->name('trash.board');
    Route::post('trash/{type}/{id}/restore', [TrashController::class, 'restore'])
        ->where('type', 'column|card')->name('trash.restore');
    Route::delete('trash/{type}/{id}/force', [TrashController::class, 'force'])
        ->where('type', 'column|card')->name('trash.force');
    Route::get('templates', [TemplateController::class, 'index'])->name('templates');

    // 列
    Route::post('columns', [ColumnController::class, 'store'])->name('columns.store');
    Route::patch('columns/reorder', [ColumnController::class, 'reorder'])->name('columns.reorder');
    Route::patch('columns/{column}', [ColumnController::class, 'update'])->name('columns.update');
    Route::delete('columns/{column}', [ColumnController::class, 'destroy'])->name('columns.destroy');

    // 卡片
    Route::post('cards', [CardController::class, 'store'])->name('cards.store');
    Route::post('cards/move', [CardController::class, 'move'])->name('cards.move');
    Route::patch('cards/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    // 评论
    Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // 看板成员管理（邀请 / 改角色 / 移除）
    Route::post('boards/{board:slug}/members', [MemberController::class, 'store'])->name('members.store');
    Route::patch('boards/{board:slug}/members/{user}', [MemberController::class, 'update'])->name('members.update');
    Route::delete('boards/{board:slug}/members/{user}', [MemberController::class, 'destroy'])->name('members.destroy');
});

require __DIR__ . '/settings.php';
