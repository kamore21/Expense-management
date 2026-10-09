<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId === null) {
            return;
        }

        DB::table('expenses')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        DB::table('invoices')->whereNull('user_id')->update(['user_id' => $firstUserId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
