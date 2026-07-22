<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    /**
     * 内置看板模板（无需建表，代码中定义）。
     */
    private const PRESETS = [
        [
            'key' => 'agile',
            'name' => '敏捷冲刺',
            'description' => '从想法到上线的标准冲刺流程',
            'columns' => ['Backlog', 'To Do', 'In Progress', 'Review', 'Done'],
        ],
        [
            'key' => 'bug',
            'name' => 'Bug 追踪',
            'description' => '缺陷从上报到闭环',
            'columns' => ['待处理', '进行中', '待验证', '已关闭'],
        ],
        [
            'key' => 'content',
            'name' => '内容日历',
            'description' => '选题到发布的编辑流',
            'columns' => ['选题', '撰写', '审核', '发布'],
        ],
    ];

    public function index(): Response
    {
        return Inertia::render('Templates', [
            'templates' => self::PRESETS,
        ]);
    }
}
