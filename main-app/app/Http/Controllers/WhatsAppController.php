<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\VendorInfo;
use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use App\Models\Staff\StaffContent;
use Illuminate\Support\Facades\DB;
use App\Models\Services\ServiceBooking;
use App\Models\Services\ServiceContent;
use App\Http\Helpers\VendorPermissionHelper;

class WhatsAppController extends Controller
{
  /**
   * Send WhatsApp message
   *
   * @param int $booking_id -> check the booking id
   * @param string $template_type -> check the template type
   * @param string $purpose -> check this tempalte is for customer or vendor
   */
  public static function sendMessage($booking_id, $template_type, $purpose)
  {
    $whatsapp_manager_status = DB::table('basic_settings')
      ->where('uniqid', 12345)
      ->value('whatsapp_manager_status');

    //if whatsapp manager is disabled
    if ($whatsapp_manager_status == 0) {
      return false;
    }

    $booking = ServiceBooking::where('id', $booking_id)->first();
    if (!$booking) {
      return false;
    }

    //if vendor is not admin and whatsapp manager is disabled on package then return
    if ($booking->vendor_id != 0) {
      $currPackage = VendorPermissionHelper::currentPackagePermission($booking->vendor_id);
      if ($currPackage->whatsapp_manager_status == 0) {
        return false;
      }
    }

    $language = Language::where('is_default', 1)->first();

    //this template used for customer notification
    $template = DB::table('whatsapp_templates')->where('type', $template_type)->first();
    $templateParams  = json_decode($template->params, true);

    //this template used for vendor notification
    if ($purpose == "new_booking") {
      $vendorTemplate = DB::table('whatsapp_templates')->where('type', 'vendor_booking_notification')->first();
      $vendorTemplateParams  = json_decode($vendorTemplate->params, true);
    }

    //processing template data
    $serviceInfo = ServiceContent::query()
      ->where('service_id', $booking->service_id)
      ->where('language_id', $language->id)
      ->select('name', 'slug')
      ->firstOrFail();
    $serviceName = truncateString($serviceInfo->name, 50);
    $amount = custom_format_price($booking->customer_paid, $booking->currency_symbol, $booking->currency_symbol_position);
    $vendorName = $booking->vendor_id != 0 ?  VendorInfo::where('vendor_id', $booking->vendor_id)
      ->where('language_id', $language->id)->value('name') : "Admin";

    //get staff name
    if ($booking->staff_id) {
      $staffName = StaffContent::where('staff_id', $booking->staff_id)
        ->where('language_id', $language->id)
        ->value('name');
      $staffName = isset($staffName) ? $staffName : $booking->staff->username;
    }


    //replace data in template parameters with booking data
    $allOptions = [
      'customer_name' => $booking->customer_name,
      'vendor_name' => $vendorName ?? ($booking->vendor_id != 0 ? $booking->vendor->username : "Admin"),
      'service_title' => $serviceName,
      'order_number' => $booking->order_number,
      'booking_date' => $booking->booking_date,
      'start_date' => $booking->start_date,
      'end_date' => $booking->end_date,
      'customer_paid' => $amount,
      'payment_method' => $booking->payment_method,
      'order_status' => $booking->order_status,
      'zoom_info' => $booking->zoom_info,
      'invoice' => $booking->invoice,
      'staff' => $staffName ?? 'N/A',
    ];


    //this is customer template
    $params = [];
    foreach ($templateParams as $key) {
      if ($key === 'invoice') {
        continue;
      }

      if (isset($allOptions[$key])) {
        $params[] = [
          'type' => 'text',
          'text' => $allOptions[$key]
        ];
      }
    }
    WhatsAppService::send($booking->customer_phone, $booking->invoice, $template,  $templateParams, $params);

    //this is vendor template
    if ($purpose == "new_booking") {
      $vendor_params = [];
      foreach ($vendorTemplateParams as $key) {
        if ($key === 'invoice') {
          continue;
        }

        if (isset($allOptions[$key])) {
          $vendor_params[] = [
            'type' => 'text',
            'text' => $allOptions[$key]
          ];
        }
      }
      WhatsAppService::send(env('ADMIN_WHATSAPP'), $booking->invoice, $vendorTemplate,  $vendorTemplateParams, $vendor_params);
    }

    return;
  }
}
