<?php

namespace App\Models\FeaturedService;

use App\Models\Vendor;
use App\Models\VendorInfo;
use App\Models\Services\Services;
use App\Models\Services\ServiceContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ServicePromotion extends Model
{
  use HasFactory;
  protected $guarded = [];

  public function vendor()
  {
    return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
  }

  public function vendorInfo()
  {
    return $this->belongsTo(VendorInfo::class, 'vendor_id', 'vendor_id');
  }

  public function service()
  {
    return $this->belongsTo(Services::class, 'service_id', 'id');
  }

  public function serviceContent()
  {
    return $this->hasMany(ServiceContent::class, 'service_id', 'service_id');
  }
}
