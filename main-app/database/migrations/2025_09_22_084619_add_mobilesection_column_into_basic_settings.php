<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::table('basic_settings', function (Blueprint $table) {
      $table->string('mobile_favicon')->nullable();
      $table->string('mobile_app_logo')->nullable();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::table('basic_settings', function (Blueprint $table) {
      $table->dropColumn(['mobile_favicon', 'mobile_app_logo']);
    });
  }
};
