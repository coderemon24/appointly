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
      $table->string('whatsapp_number_id')->nullable();
      $table->string('whatsapp_access_token')->nullable();
      $table->string('whatsapp_admin_number')->nullable();
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
      $table->dropColumn('whatsapp_number_id');
      $table->dropColumn('whatsapp_access_token');
      $table->dropColumn('whatsapp_admin_number');
    });
  }
};
