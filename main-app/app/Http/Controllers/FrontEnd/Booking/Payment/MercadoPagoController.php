<?php

namespace App\Http\Controllers\Frontend\Booking\Payment;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class MercadoPagoController extends Controller
{
  private $token, $sandbox_status;

  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('mercadopago')->first();
    $mercadopagoData = json_decode($data->information, true);

    $this->token = $mercadopagoData['token'];
    $this->sandbox_status = $mercadopagoData['sandbox_status'];
  }

  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $customerpaid = intval($amount);

    $title = 'Booking Service';
    $notifyURL = route('frontend.service_booking.mercadopago.notify');
    $cancelURL = route('frontend.services');

    $customerEmail = $arrData['customer_email'];

    $curl = curl_init();

    $preferenceData = [
      'items' => [
        [
          'id' => uniqid(),
          'title' => $title,
          'description' => $title . ' via MercadoPago',
          'quantity' => 1,
          'currency' => $arrData['currencyText'],
          'unit_price' => $customerpaid
        ]
      ],
      'payer' => [
        'email' => $customerEmail
      ],
      'back_urls' => [
        'success' => $notifyURL,
        'pending' => '',
        'failure' => $cancelURL
      ],
      'notification_url' => $notifyURL,
      'auto_return' => 'approved'
    ];

    $httpHeader = ['Content-Type: application/json'];

    $url = 'https://api.mercadopago.com/checkout/preferences?access_token=' . $this->token;

    $curlOPT = [
      CURLOPT_URL             => $url,
      CURLOPT_CUSTOMREQUEST   => 'POST',
      CURLOPT_POSTFIELDS      => json_encode($preferenceData, true),
      CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
      CURLOPT_RETURNTRANSFER  => true,
      CURLOPT_TIMEOUT         => 30,
      CURLOPT_HTTPHEADER      => $httpHeader
    ];

    curl_setopt_array($curl, $curlOPT);

    $response = curl_exec($curl);
    $responseInfo = json_decode($response, true);

    curl_close($curl);

    // put some data in session before redirect to mercadopago url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);

    if ($this->sandbox_status == 1) {
      return response()->json(['redirectURL' => $responseInfo['sandbox_init_point']]);
    } else {
      return response()->json(['redirectURL' => $responseInfo['init_point']]);
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');


    if ($request->status == 'approved') {
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

  public function curlCalls($url)
  {
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $curlData = curl_exec($curl);

    curl_close($curl);

    return $curlData;
  }
}
