<?php

namespace App\Models\Staff;

use App\Models\Vendor;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class Staff extends Model implements AuthenticatableContract
{
  use HasFactory, Authenticatable ,HasApiTokens;

  protected $fillable = [
    'vendor_id',
    'status',
    'email',
    'phone',
    'image',
    'order_number',
    'allow_login',
    'username',
    'password',
    'is_day',
    'service_add',
    'service_edit',
    'service_delete',
    'time',
    'role',
    'email_status',
    'info_status',
    'phone_status',
  ];

  // public function getImageAttribute($value): string
  // {
  //   if (!$value) return '';
  //   return asset('assets/img/staff/' . basename($value));
  // }
  public function StaffContent()
  {
    return $this->hasMany(StaffContent::class, 'staff_id', 'id');
  }

  public function vendor()
  {
    return $this->hasOne(Vendor::class, 'id', 'vendor_id');
  }
}
