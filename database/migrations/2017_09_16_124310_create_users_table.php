<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('Users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('installation_id', 100);
            $table->string('device', 100);
            $table->string('data', 3000)->nullable();
            $table->string('cookies', 500)->nullable();
            $table->unsignedInteger('logged_count')->default(0);
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('Users');
    }
}
