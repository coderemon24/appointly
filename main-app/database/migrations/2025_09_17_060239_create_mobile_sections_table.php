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
    Schema::create('mobile_sections', function (Blueprint $table) {
      $table->id();
      $table->bigInteger('language_id')->nullable();
      $table->string('hero_section_background_img')->nullable();
      $table->string('hero_section_title')->nullable();
      $table->string('hero_section_subtitle')->nullable();
      $table->text('hero_section_text')->nullable();
      $table->string('category_section_title')->nullable();
      $table->string('featured_service_section_title')->nullable();
      $table->string('vendor_section_title')->nullable();
      $table->string('latest_service_section_title')->nullable();
      $table->string('favicon')->nullable();
      $table->string('logo')->nullable();
      $table->string('preloader')->nullable();
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
    Schema::dropIfExists('mobile_sections');
  }
};
