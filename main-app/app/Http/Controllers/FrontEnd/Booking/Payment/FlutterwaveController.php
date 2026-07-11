<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use Response;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class FlutterwaveController extends Controller
{
  private $public_key, $secret_key;

  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('flutterwave')->first();
    $flutterwaveData = json_decode($data->information, true);

    $this->public_key = $flutterwaveData['public_key'];
    $this->secret_key = $flutterwaveData['secret_key'];
  }

  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $customerPaid = intval($amount);
    $title = 'Service Booking';
    $notifyURL = route('frontend.service_booking.flutterwave.notify');

    $customerName = $arrData['customer_name'];
    $customerEmail = $arrData['customer_email'];
    $customerPhone = $arrData['customer_phone'];


    // send payment to flutterwave for processing
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode([
        'tx_ref' => 'FLW | ' . time(),
        'amount' => $customerPaid,
        'currency' => $arrData['currencyText'],
        'redirect_url' => $notifyURL,
        'payment_options' => 'card,banktransfer',
        'customer' => [
          'email' => $customerEmail,
          'phone_number' => $customerPhone,
          'name' => $customerName
        ],
        'customizations' => [
          'title' => $title,
          'description' => $title . ' via Flutterwave.'
        ]
      ]),
      CURLOPT_HTTPHEADER => array(
        'authorization: Bearer ' . $this->secret_key,
        'content-type: application/json'
      )
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $responseData = json_decode($response, true);

    //curl end

    // put some data in session before redirect to flutterwave url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);

    // redirect to payment
    if ($responseData['status'] === 'success') {
      $redirectUrl = $responseData['data']['link'];

      // Return the redirect URL as part of the JSON response
      return Response::json(['redirectURL' => $redirectUrl]);
    } else {
      return redirect()->back()->with('error', 'Error: ' . $responseData['message'])->withInput();
    }
  }

  public function notify(Request $request)
  {
    // get the information from session

    $arrData = $request->session()->get('arrData');

    $urlInfo = $request->all();

    if ($urlInfo['status'] == 'successful') {
      $txId = $urlInfo['transaction_id'];

      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/{$txId}/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          'authorization: Bearer ' . $this->secret_key,
          'content-type: application/json'
        )
      ));

      $response = curl_exec($curl);

      curl_close($curl);

      $responseData = json_decode($response, true);
      if ($responseData['status'] === 'success') {
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
        $request->session()->forget('serviceData');
        return redirect()->route('frontend.services');
      } else {
        $request->session()->forget('arrData');
        $request->session()->forget('serviceData');
        return redirect()->route('frontend.service_booking.cancel');
      }
    } else {
      $request->session()->forget('paymentFor');
      $request->session()->forget('arrData');
      $request->session()->forget('serviceData');

      return redirect()->route('frontend.service_booking.cancel');
    }
  }
}
