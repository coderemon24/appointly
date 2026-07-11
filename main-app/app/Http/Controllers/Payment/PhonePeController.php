<?php

namespace App\Http\Controllers\Payment;

use Carbon\Carbon;
use App\Models\Package;
use App\Models\Membership;
use Illuminate\Http\Request;
use App\Http\Helpers\MegaMailer;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\VendorCheckoutController;

class PhonePeController extends Controller
{
  private $sandboxCheck;
  public function paymentProcess(Request $request, $_amount, $_success_url, $_cancel_url)
  {
    $info = OnlineGateway::where('keyword', 'phonepe')->first();
    $paydata = json_decode($info->information, true);

    $cancel_url = $_cancel_url;
    $notify_url = $_success_url;

    $this->sandboxCheck = $paydata['sandbox_status'];

    $clientId = $paydata['merchant_id'];
    $clientSecret = $paydata['salt_key'];

    //* Here i completed 1 step which is generating access token in each request

    $accessToken = $this->getPhonePeAccessToken($clientId, $clientSecret);

    if (!$accessToken) {
      return back()->withError(__('Failed to get PhonePe access token') . '.');
    }
    Session::put("request", $request->all());
    Session::put('cancel_url', $cancel_url);

    return  $this->initiatePayment($accessToken, $notify_url, $cancel_url, $_amount);
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
        return redirect()->away($responseData['redirectUrl']);
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


  public function successPayment(Request $request)
  {
    $requestData = Session::get('request');
    $bs = Basic::first();
    $cancel_url = Session::get('cancel_url');

    $merchantOrderId = $request->input('merchantOrderId') ??
      Session::get('merchantOrderId') ??
      uniqid();

    $verificationResponse = $this->verifyOrderStatus($merchantOrderId);

    if ($verificationResponse['success']) {
      //transaction create
      $after_balance = NULL;
      $pre_balance = NULL;
      $transactionData = [
        'vendor_id' => $requestData['vendor_id'],
        'transaction_type' => 'membership_buy',
        'pre_balance' => $pre_balance,
        'actual_total' => $requestData['price'],
        'after_balance' => $after_balance,
        'admin_profit' => $requestData['price'],
        'payment_method' => $requestData['payment_method'],
        'currency_symbol' => $bs->base_currency_symbol,
        'currency_symbol_position' => $bs->base_currency_symbol_position,
        'payment_status' => 'completed',
      ];
      store_transaction($transactionData);

      $paymentFor = session()->get('paymentFor');
      $package = Package::find($requestData['package_id']);
      $transaction_id = VendorPermissionHelper::uniqidReal(8);
      $transaction_details = NULL;
      if ($paymentFor == "membership") {
        $amount = $requestData['price'];
        $password = $requestData['password'];
        $checkout = new VendorCheckoutController();

        $vendor = $checkout->store($requestData, $transaction_id, $transaction_details, $amount, $bs, $password);

        $lastMemb = $vendor->memberships()->orderBy('id', 'DESC')->first();

        $activation = \Carbon\Carbon::parse($lastMemb->start_date);
        $expire = \Carbon\Carbon::parse($lastMemb->expire_date);
        $file_name = $this->makeInvoice($requestData, "membership", $vendor, $password, $amount, "Paypal", $requestData['phone'], $bs->base_currency_symbol_position, $bs->base_currency_symbol, $bs->base_currency_text, $transaction_id, $package->title, $lastMemb);

        $mailer = new MegaMailer();
        $data = [
          'toMail' => $vendor->email,
          'toName' => $vendor->fname,
          'username' => $vendor->username,
          'package_title' => $package->title,
          'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
          'discount' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $lastMemb->discount . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
          'total' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $lastMemb->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
          'activation_date' => $activation->toFormattedDateString(),
          'expire_date' => \Carbon\Carbon::parse($expire->toFormattedDateString())->format('Y') == '9999' ? 'Lifetime' : $expire->toFormattedDateString(),
          'membership_invoice' => $file_name,
          'website_title' => $bs->website_title,
          'templateType' => 'package_purchase',
          'type' => 'registrationWithPremiumPackage'
        ];
        $mailer->mailFromAdmin($data);
        @unlink(public_path('assets/front/invoices/' . $file_name));

        session()->flash('success', 'Your payment has been completed.');
        session()->forget('request');
        session()->forget('paymentFor');
        return redirect()->route('success.page');
      } elseif ($paymentFor == "extend") {
        $amount = $requestData['price'];
        $password = uniqid('qrcode');
        $checkout = new VendorCheckoutController();
        $vendor = $checkout->store($requestData, $transaction_id, $transaction_details, $amount, $bs, $password);

        $lastMemb = Membership::where('vendor_id', $vendor->id)->orderBy('id', 'DESC')->first();
        $activation = \Carbon\Carbon::parse($lastMemb->start_date);
        $expire = \Carbon\Carbon::parse($lastMemb->expire_date);

        $file_name = $this->makeInvoice($requestData, "extend", $vendor, $password, $amount, $requestData["payment_method"], $vendor->phone, $bs->base_currency_symbol_position, $bs->base_currency_symbol, $bs->base_currency_text, $transaction_id, $package->title, $lastMemb);

        $mailer = new MegaMailer();
        $data = [
          'toMail' => $vendor->email,
          'toName' => $vendor->fname,
          'username' => $vendor->username,
          'package_title' => $package->title,
          'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
          'activation_date' => $activation->toFormattedDateString(),
          'expire_date' => \Carbon\Carbon::parse($expire->toFormattedDateString())->format('Y') == '9999' ? 'Lifetime' : $expire->toFormattedDateString(),
          'membership_invoice' => $file_name,
          'website_title' => $bs->website_title,
          'templateType' => 'package_purchase',
          'type' => 'membershipExtend'
        ];
        $mailer->mailFromAdmin($data);
        @unlink(public_path('assets/front/invoices/' . $file_name));

        session()->forget('request');
        session()->forget('paymentFor');
        return redirect()->route('success.page');
      }
      return redirect($cancel_url);
    }
  }

  public function cancelPayment()
  {
    session()->flash('warning', __('cancel_payment'));
    return redirect()->route('vendor.plan.extend.checkout', ['package_id' => session()->get('request')['package_id']])->withInput(session()->get('request'));
  }
}
