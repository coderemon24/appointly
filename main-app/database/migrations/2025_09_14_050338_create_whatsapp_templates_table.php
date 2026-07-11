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
    Schema::create('whatsapp_templates', function (Blueprint $table) {
      $table->id();
      $table->string('name')->nullable();
      $table->string('type')->nullable();
      $table->string('language_code')->nullable();
      $table->json('params')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('whatsapp_templates');
  }
};
