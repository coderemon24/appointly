<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use App\Http\Helpers\UploadFile;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class OfflineController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $paymentProcess = new ServicePaymentController();
    $directory = public_path('assets/file/attachments/service/');
    // store attachment in local storage
    if ($arrData['attachment'] != null) {
      $attachmentName = UploadFile::store($directory, $arrData['attachment']);
    } else {
      $attachmentName = null;
    }

    $arrData['attachment'] = $attachmentName;
    zoomCreate($arrData);
    calendarEventCreate($arrData);

    // store service booking information in database
    $bookingInfo = $paymentProcess->storeData($arrData);


    $type = 'service_payment_request_send';
    //send whatsapp sms
    WhatsAppController::sendMessage($bookingInfo->id, "customer_booking_confirmation","new_booking");
    payemntStatusMail($type, $bookingInfo->id);

    Session::put('complete', 'payment_complete');
    Session::put('paymentInfo', $bookingInfo);
    session()->forget('serviceData');

    return response()->json(['redirectURL' => route('frontend.services')]);

    return response()->json('success fully done!');
  }
}
