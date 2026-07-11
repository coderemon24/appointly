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
    Schema::table('online_gateways', function (Blueprint $table) {
      $table->string('mobile_status')->default(0);
      $table->mediumText('mobile_information')->nullable();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::table('online_gateways', function (Blueprint $table) {
      $table->dropColumn('mobile_status');
      $table->dropColumn('mobile_information');
    });
  }
};
