<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOnboardingScreen extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('onboarding_screen')) {
            if (!Schema::hasColumn('onboarding_screen', 'order')) {
                Schema::table('onboarding_screen', function (Blueprint $table) {
                    $table->integer('order')->after('screen')->default(0);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('onboarding_screen');
    }
}
