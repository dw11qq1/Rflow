<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 让看板列支持软删除，从而可进入回收站。
     */
    public function up(): void
    {
        Schema::table('columns', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('columns', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
