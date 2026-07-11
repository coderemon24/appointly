<?php

namespace App\Http\Controllers\Frontend\Booking\Payment;

use Response;
use App\Models\Staff\Staff;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Staff\StaffGlobalHour;
use App\Http\Helpers\CheckLimitHelper;
use App\Models\Staff\StaffServiceHour;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use Anand\LaravelPaytmWallet\Facades\PaytmWallet;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class PaytmController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $customerpaid = intval($amount);
    $notifyURL = route('frontend.service_booking.paytm.notify');

    $customerEmail = $arrData['customer_email'];
    $customerPhone = $arrData['customer_phone'];

    $payment = PaytmWallet::with('receive');

    $payment->prepare([
      'order' => time(),
      'user' => uniqid(),
      'mobile_number' => $customerPhone,
      'email' => $customerEmail,
      'amount' => round($customerpaid, 2),
      'callback_url' => $notifyURL
    ]);

    // put some data in session before redirect to paytm url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);

    return $payment->receive();
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');

    $transaction = PaytmWallet::with('receive');

    // this response is needed to check the transaction status
    $response = $transaction->response();

    if ($transaction->isSuccessful()) {
      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');

      $bookingProcess = new ServicePaymentController();

      zoomCreate($arrData);
      calendarEventCreate($arrData);

      // store product order information in database
      $bookingInfo = $bookingProcess->storeData($arrData);
      //send whatsapp sms
      WhatsAppController::sendMessage($bookingInfo->id, "customer_booking_confirmation","new_booking");


      $type = 'service_payment_approved';
      payemntStatusMail($type, $bookingInfo->id);

      //redirect url with billing session
      Session::put('complete', 'payment_complete');
      Session::put('paymentInfo', $bookingInfo);
      return redirect()->route('frontend.services');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');

      return redirect()->route('frontend.service_booking.cancel');
    }
  }
}
