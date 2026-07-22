<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Board;
use App\Models\MetricSnapshot;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $board = Board::create([
            'name' => '我的第一块看板',
            'slug' => 'my-first-board',
            'owner_id' => $user->id,
            'is_archived' => false,
        ]);

        $board->members()->attach($user->id, ['role' => 'owner']);

        $columns = collect(['To Do', 'Doing', 'Done'])->map(function ($name, $i) use ($board) {
            return $board->columns()->create([
                'name' => $name,
                'position' => $i,
            ]);
        });

        $todo = $columns[0];
        $doing = $columns[1];
        $done = $columns[2];

        $samples = [
            [$todo, '修复登录页', '用户反馈在 Safari 下登录按钮无响应'],
            [$todo, '写首页文案'],
            [$todo, '接入实时同步', '使用 Soketi + Laravel Echo'],
            [$doing, '设计看板列组件'],
            [$done, '搭建 Laravel + Inertia 骨架'],
            [$done, '梳理数据库 Schema'],
        ];

        foreach ($samples as $sample) {
            [$column, $title, $description] = array_pad($sample, 3, null);

            $board->cards()->create([
                'column_id' => $column->id,
                'title' => $title,
                'description' => $description,
                'position' => $column->cards()->count(),
                'created_by' => $user->id,
            ]);
        }

        // 手动补一条活动流（seeder 阶段模型事件被关闭，不会自动记录）
        Activity::insert([
            [
                'board_id' => $board->id,
                'user_id' => $user->id,
                'type' => 'card.created',
                'subject_type' => \App\Models\Card::class,
                'subject_id' => $todo->cards()->first()->id,
                'payload' => json_encode(['title' => '修复登录页']),
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(5),
            ],
            [
                'board_id' => $board->id,
                'user_id' => $user->id,
                'type' => 'card.moved',
                'subject_type' => \App\Models\Card::class,
                'subject_id' => $done->cards()->first()->id,
                'payload' => json_encode(['title' => '搭建 Laravel + Inertia 骨架', 'from' => $doing->id, 'to' => $done->id]),
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
        ]);

        // 生成今日指标快照
        $perColumn = $board->columns
            ->mapWithKeys(fn ($column) => [$column->name => $column->cards()->count()])
            ->toArray();

        MetricSnapshot::create([
            'board_id' => $board->id,
            'date' => now()->toDateString(),
            'snapshot' => [
                'total' => $board->cards()->count(),
                'per_column' => $perColumn,
            ],
        ]);
    }
}
