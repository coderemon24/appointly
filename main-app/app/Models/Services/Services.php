<?php

namespace App\Models\Services;

use App\Models\Vendor;
use App\Models\Language;
use App\Models\VendorInfo;
use Illuminate\Database\Eloquent\Model;
use App\Models\FeaturedService\ServicePromotion;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Services extends Model
{
  use HasFactory;

  protected $fillable = [
    'vendor_id',
    'status',
    'price',
    'prev_price',
    'service_image',
    'zoom_meeting',
    'max_person',
    'calendar_status',
    'average_rating',
    'staff_id',
    'latitude',
    'longitude'
  ];

  public function language()
  {
    return $this->belongsTo(Language::class);
  }
  public function vendor()
  {
    return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
  }
  public function vendorInfo()
  {
    return $this->belongsTo(VendorInfo::class, 'vendor_id', 'vendor_id');
  }
  public function content()
  {
    return $this->hasMany(ServiceContent::class, 'service_id', 'id');
  }
  public function sliderImage()
  {
    return $this->hasMany(ServiceImage::class, 'service_id', 'id');
  }
  public function appointment()
  {
    return $this->hasMany(ServiceBooking::class, 'service_id', 'id');
  }
  public function reviews()
  {
    return $this->hasMany(ServiceReview::class, 'service_id', 'id')
      ->select('id', 'service_id', 'user_id', 'rating', 'comment', 'created_at'); // select needed fields
  }

  public function servicePromotion()
  {
    return $this->hasMany(ServicePromotion::class, 'service_id', 'id');
  }
}
