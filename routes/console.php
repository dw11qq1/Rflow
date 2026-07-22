<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每天 00:05 自动为所有看板生成当日指标快照（复盘趋势图数据来源）
Schedule::command('reflow:snapshot')->dailyAt('00:05');
