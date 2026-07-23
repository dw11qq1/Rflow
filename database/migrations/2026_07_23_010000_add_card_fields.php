<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            // 优先级：low / medium / high（默认 medium）
            $table->string('priority')->default('medium')->after('description');
            // 卡片强调色（可选，hex，如 #5C5CD6）
            $table->string('color', 7)->nullable()->after('priority');
            // 到期提醒去重标记：已通知过则不再重复提醒
            $table->timestamp('due_notified_at')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['priority', 'color', 'due_notified_at']);
        });
    }
};
