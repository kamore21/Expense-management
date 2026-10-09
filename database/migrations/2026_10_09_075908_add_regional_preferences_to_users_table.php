<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('country_code', 2)->default('US')->after('email');
            $table->char('currency_code', 3)->default('USD')->after('country_code');
            $table->string('locale', 24)->default('en_US')->after('currency_code');
            $table->string('timezone', 64)->default('UTC')->after('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'currency_code', 'locale', 'timezone']);
        });
    }
};
