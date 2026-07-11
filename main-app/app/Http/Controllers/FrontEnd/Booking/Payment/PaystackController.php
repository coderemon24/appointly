<?php

namespace App\Http\Controllers\Frontend\Booking\Payment;

use Response;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class PaystackController extends Controller
{
  private $api_key;

  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('paystack')->first();
    $paystackData = json_decode($data->information, true);

    $this->api_key = $paystackData['key'];
  }

  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {

    $customerpaid = intval($amount);
    $notifyURL = route('frontend.service_booking.paystack.notify');
    $customerEmail = $arrData['customer_email'];

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL            => 'https://api.paystack.co/transaction/initialize',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST  => 'POST',
      CURLOPT_POSTFIELDS     => json_encode([
        'amount'       => ($customerpaid * 100),
        'email'        => $customerEmail,
        'callback_url' => $notifyURL
      ]),
      CURLOPT_HTTPHEADER     => [
        'authorization: Bearer ' . $this->api_key,
        'content-type: application/json',
        'cache-control: no-cache'
      ]
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $transaction = json_decode($response, true);

    // put some data in session before redirect to paystack url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);

    if ($transaction['status'] == true) {
      return Response::json(['redirectURL' => $transaction['data']['authorization_url']]);
    } else {
      return redirect()->back()->with('error', 'Error: ' . $transaction['message'])->withInput();
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');

    $urlInfo = $request->all();

    if ($urlInfo['trxref'] === $urlInfo['reference']) {
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


      session()->forget('discount');

      //redirect url with billing session data

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
