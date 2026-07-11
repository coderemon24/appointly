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

class XenditController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $paymentMethod = OnlineGateway::where('keyword', 'xendit')->first();
    $paydata = json_decode($paymentMethod->information, true);

    $external_id = \Str::random(10);
    $secret_key = 'Basic ' . base64_encode($paydata['secret_key'] . ':');
    $data_request = Http::withHeaders([
      'Authorization' => $secret_key
    ])->post('https://api.xendit.co/v2/invoices', [
      'external_id' => $external_id,
      'amount' => $amount,
      'currency' => $arrData['currencyText'],
      'success_redirect_url' => route('frontend.service_booking.xendit_notify')
    ]);
    $response = $data_request->json();
    if (isset($response['error_code']) && isset($response['message'])) {
      session()->flash('warning', $response['message']);
      return redirect()->back();
    }

    if (!empty($response['invoice_url'])) {
      session()->put('cancel_url', $cancel_url);
      session()->put("arrData", $arrData);
      session()->put('xendit_id', $response['id']);
      session()->put('secret_key', $secret_key);
      $redirectURL = $response['invoice_url'];
      return response()->json(['redirectURL' => $redirectURL]);
    } else {
      return redirect($cancel_url);
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $xendit_id = session()->get('xendit_id');
    $cancel_url = session()->get('cancel_url');
    $secret_key = session()->get('secret_key');
    $paymentMethod = OnlineGateway::where('keyword', 'xendit')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $p_secret_key = 'Basic ' . base64_encode($paydata['secret_key'] . ':');
    if (!is_null($xendit_id) && $secret_key == $p_secret_key) {
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
      session()->forget('cancel_url');
      return redirect()->route('frontend.services');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      return redirect($cancel_url);
    }
  }
}
