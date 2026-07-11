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
    Schema::table('packages', function (Blueprint $table) {
      $table->string('whatsapp_manager_status')->after('zoom_meeting_status')->default(0);
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::table('packages', function (Blueprint $table) {
      $table->dropColumn('whatsapp_manager_status');
    });
  }
};
