<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class YocoController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $paymentMethod = OnlineGateway::where('keyword', 'yoco')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $response = Http::withHeaders([
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . $paydata['secret_key'],
    ])->post('https://payments.yoco.com/api/checkouts', [
      'amount' => $amount * 100,
      'currency' => 'ZAR',
      'successUrl' => route('frontend.service_booking.phonepe_notify'),
      'cancelUrl' => $cancel_url
    ]);


    Session::put('arrData', $arrData);
    $responseData = $response->json();
    if (array_key_exists('redirectUrl', $responseData)) {
      Session::put('yoco_id', $responseData['id']);
      Session::put('s_key', $paydata['secret_key']);
      Session::put('amount', $amount);
      Session::put('cancel_url', $cancel_url);
      //redirect for received payment from user
      $redirectURL = $responseData['redirectUrl'];
      return response()->json(['redirectURL' => $redirectURL]);
    } else {
      return redirect($cancel_url);
    }
  }

  public function notify()
  {
    $arrData = Session::get('arrData');
    $cancel_url = Session::get('cancel_url');
    $id = Session::get('yoco_id');
    $s_key = Session::get('s_key');
    $paymentMethod = OnlineGateway::where('keyword', 'yoco')->first();
    $paydata = $paymentMethod->convertAutoData();
    if ($id && $paydata['secret_key'] == $s_key) {
      $bookingProcess = new ServicePaymentController();

      zoomCreate($arrData);
      calendarEventCreate($arrData);

      // store product order information in database
      $bookingInfo = $bookingProcess->storeData($arrData);
      //send whatsapp sms
      WhatsAppController::sendMessage($bookingInfo->id, "customer_booking_confirmation","new_booking");


      $type = 'service_payment_approved';
      payemntStatusMail($type, $bookingInfo->id);

      Session::put('complete', 'payment_complete');
      Session::put('paymentInfo', $bookingInfo);
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      return redirect()->route('frontend.services');
    } else {

      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      return redirect($cancel_url);
    }
  }
}
