<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use Midtrans\Snap;
use Illuminate\Http\Request;
use Midtrans\Config as MidtransConfig;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class MidtransController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $data = [];
    $name = $arrData['customer_name'];
    $email =  $arrData['customer_email'];
    $phone = $arrData['customer_phone'];
    $data['title'] = $paymentFor;


    $paymentMethod = OnlineGateway::where('keyword', 'midtrans')->first();
    $paydata = json_decode($paymentMethod->information, true);

    // will come from database
    MidtransConfig::$serverKey = $paydata['server_key'];
    MidtransConfig::$isProduction = $paydata['is_production'] == 0 ? true : false;
    MidtransConfig::$isSanitized = true;
    MidtransConfig::$is3ds = true;
    $token = uniqid();
    Session::put('token', $token);
    Session::put('cancel_url', $cancel_url);
    Session::put('arrData', $arrData);
    $params = [
      'transaction_details' => [
        'order_id' => $token,
        'gross_amount' => (int)round($amount)
      ],
      'customer_details' => [
        'first_name' => $name,
        'email' => $email,
        'phone' => $phone,
      ],
    ];

    $snapToken = Snap::getSnapToken($params);

    // put some data in session before redirect to midtrans url
    if (
      $paydata['is_production'] == 1
    ) {
      $is_production = $paydata['is_production'];
    }
    $success_url = route('frontend.service_booking.midtrans_notify');
    $data['snapToken'] = $snapToken;
    $data['is_production'] = $is_production;
    $data['success_url'] = $success_url;
    $data['_cancel_url'] = $cancel_url;
    $data['client_key'] = $paydata['server_key'];
    return view('frontend.payment.midtrans-membership', $data);
  }


  public function notify(Request $request)
  {
    $arrData = Session::get('arrData');
    $token = Session::get('token');
    $cancel_url = Session::get('cancel_url');
    if ($request->status_code == 200 && $token == $request->order_id) {

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
