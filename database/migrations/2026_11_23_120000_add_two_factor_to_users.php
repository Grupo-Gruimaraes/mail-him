<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_method', 10)->nullable()->after('password');
            $table->text('two_factor_secret')->nullable()->after('two_factor_method');
            $table->text('two_factor_recovery_words')->nullable()->after('two_factor_secret');
            $table->unsignedBigInteger('two_factor_last_timestep')->nullable()->after('two_factor_recovery_words');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_last_timestep');
        });

        // Cookies de "lembrar de mim" emitidos antes do 2FA deixam de valer
        DB::table('users')->update(['remember_token' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_method',
                'two_factor_secret',
                'two_factor_recovery_words',
                'two_factor_last_timestep',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
