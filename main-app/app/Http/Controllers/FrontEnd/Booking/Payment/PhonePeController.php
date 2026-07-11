<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use Cache;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class PhonePeController extends Controller
{
  private $sandboxCheck;
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $info = OnlineGateway::where('keyword', 'phonepe')->first();
    $paydata = json_decode($info->information, true);
    $notify_url = route('frontend.service_booking.phonepe_notify');

    $this->sandboxCheck = $paydata['sandbox_status'];

    $clientId = $paydata['merchant_id'];
    $clientSecret = $paydata['salt_key'];

    //* Here i completed 1 step which is generating access token in each request

    $accessToken = $this->getPhonePeAccessToken($clientId, $clientSecret);

    if (!$accessToken) {
      return back()->withError(__('Failed to get PhonePe access token') . '.');
    }
    Session::put("arrData", $arrData);
    Session::put('cancel_url', $cancel_url);

    return  $this->initiatePayment($accessToken, $notify_url, $cancel_url, $amount);
  }

  private function getPhonePeAccessToken($clientId, $clientSecret)
  {

    return Cache::remember('phonepe_access_token', 3500, function () use ($clientId, $clientSecret) {


      $tokenUrl = $this->sandboxCheck
        ? 'https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token'
        : 'https://api.phonepe.com/apis/identity-manager/v1/oauth/token';

      $response = Http::asForm()->post($tokenUrl, [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'client_version' => 1,
        'grant_type' => 'client_credentials'
      ]);


      if ($response->successful()) {
        return $response->json()['access_token'];
      }
      return null;
    });
  }

  public function initiatePayment($accessToken, $successUrl, $cancelUrl, $_amount)
  {
    $baseUrl = $this->sandboxCheck
      ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
      : 'https://api.phonepe.com/apis/pg';

    $endpoint = '/checkout/v2/pay';

    // Generate a unique merchantOrderId and store it in the session
    $merchantOrderId = uniqid();
    Session::put('merchantOrderId', $merchantOrderId);
    Session::put('cancel_url', $cancelUrl);

    //here we preapare the parameter of the request
    $payload = [
      'merchantOrderId' => $merchantOrderId,
      'amount' => intval($_amount * 100),
      'paymentFlow' => [
        'type' => 'PG_CHECKOUT',
        'merchantUrls' => [
          'redirectUrl' => $successUrl,
          'cancelUrl' => $cancelUrl
        ]
      ]
    ];

    try {
      //after preparing the parameter we send a request to create a payment link
      $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'Authorization' => 'O-Bearer ' . $accessToken,
      ])->post($baseUrl . $endpoint, $payload);

      $responseData = $response->json();

      //after successfully created the payment link of we redirect the user to api responsed redirectUrl
      if ($response->successful() && isset($responseData['redirectUrl'])) {
        $redirectURL = $responseData['redirectUrl'];
        return response()->json(['redirectURL' => $redirectURL]);
      } else {
        // Handle API errors
        Session::forget(['merchantOrderId', 'cancel_url']);
        return back()->with('error', 'Failed to initiate payment' . '.');
      }
    } catch (\Exception $e) {

      Session::forget(['merchantOrderId', 'cancel_url']);
      return response()->json([
        'success' => false,
        'code' => 'NETWORK_ERROR',
        'message' => $e->getMessage()
      ], 500);
    }
  }

  private function verifyOrderStatus($merchantOrderId)
  {
    $info = OnlineGateway::where('keyword', 'phonepe')->first();
    $paymentInfo = json_decode($info->information, true);

    $this->sandboxCheck = $paymentInfo['sandbox_status'];

    try {

      $accessToken = $this->getPhonePeAccessToken(
        $paymentInfo['merchant_id'],
        $paymentInfo['salt_key']
      );

      if (!$accessToken) {
        throw new \Exception('Failed to get access token');
      }

      $baseUrl = $this->sandboxCheck
        ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
        : 'https://api.phonepe.com/apis/pg';

      $endpoint = "/checkout/v2/order/{$merchantOrderId}/status";

      $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'Authorization' => 'O-Bearer ' . $accessToken,
      ])->get($baseUrl . $endpoint);

      if ($response->successful()) {
        $responseData = $response->json();

        return [
          'success' => true,
          'state' => $responseData['state'] ?? null,
          'amount' => $responseData['amount'] ?? null,
          'data' => $responseData
        ];
      } else {
        return [
          'success' => false,
          'error' => $response->json() ?? 'Unknown error'
        ];
      }
    } catch (\Exception $e) {
      // return [
      //     'success' => false,
      //     'error' => $e->getMessage()
      // ];
    }
  }

  public function notify(Request $request)
  {
    $arrData = Session::get('arrData');
    $cancel_url = Session::get('cancel_url');

    $merchantOrderId = $request->input('merchantOrderId') ??
      Session::get('merchantOrderId') ??
      uniqid();

    $verificationResponse = $this->verifyOrderStatus($merchantOrderId);

    if ($verificationResponse['success']) {
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
