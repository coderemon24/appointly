<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Response;
use Exception;
use App\Models\Vendor;
use Illuminate\Http\Request;
use App\Http\Helpers\Instamojo;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class InstamojoController extends Controller
{
  private $api;
  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('instamojo')->first();
    $instamojoData = json_decode($data->information, true);

    if ($instamojoData['sandbox_status'] == 1) {
      $this->api = new Instamojo($instamojoData['key'], $instamojoData['token'], 'https://test.instamojo.com/api/1.1/');
    } else {
      $this->api = new Instamojo($instamojoData['key'], $instamojoData['token']);
    }
  }

  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $title = $paymentFor;
    $notifyURL = route('vendor.featured.instamojo.notify');

    $vendor = Vendor::join('vendor_infos', 'vendor_infos.vendor_id', '=', 'vendors.id')
      ->where('vendor_infos.vendor_id', $arrData['vendor_id'])
      ->where('vendor_infos.language_id', $arrData['language_id'])
      ->select('vendor_infos.name', 'vendors.email', 'vendors.phone')
      ->first();

    $vendorName = $vendor->name;
    $vendorEmail = $vendor->email;
    $vendorPhone = $vendor->phone;

    try {
      $response = $this->api->paymentRequestCreate(array(
        'purpose' => $title,
        'amount' => round($amount, 2),
        'buyer_name' => $vendorName,
        'email' => $vendorEmail,
        'send_email' => false,
        'phone' => $vendorPhone,
        'send_sms' => false,
        'redirect_url' => $notifyURL
      ));

      // put some data in session before redirect to instamojo url
      session()->put('paymentFor', $paymentFor);
      session()->put('arrData', $arrData);
      session()->put('paymentId', $response['id']);
      session()->put('language_id', $arrData['language_id']);
      session()->put('success_url', $success_url);

      // Return the redirect URL as part of the JSON response
      return Response::json(['redirectURL' => $response['longurl']]);
    } catch (Exception $e) {
      return Response::json(['error' => 'Enter a valid phone number!'], 422);
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $paymentId = session()->get('paymentId');
    $languageId = session()->get('language_id');
    $success_url = session()->get('success_url');

    $urlInfo = $request->all();

    if ($urlInfo['payment_request_id'] == $paymentId) {
      $servicePromotion = new ServicePromotionController();
      $featuredInfo = $servicePromotion->storeData($arrData);

      //transaction create
      $after_balance = NULL;
      $pre_balance = NULL;
      $transactionData = [
        'vendor_id' => Auth::guard('vendor')->user()->id,
        'transaction_type' => 'featured_service',
        'pre_balance' => $pre_balance,
        'actual_total' => $arrData['amount'],
        'after_balance' => $after_balance,
        'admin_profit' => $arrData['amount'],
        'payment_method' => $arrData['paymentMethod'],
        'currency_symbol' => $arrData['currencySymbol'],
        'currency_symbol_position' => $arrData['currencySymbolPosition'],
        'payment_status' => $arrData['paymentStatus'],
      ];
      store_transaction($transactionData);

      $invoice = $servicePromotion->generateInvoice($featuredInfo);
      $featuredInfo->update(['invoice' => $invoice]);
      $servicePromotion->prepareMail($featuredInfo, $languageId);

      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('paymentId');
      session()->forget('language_id');
      return redirect($success_url);
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('paymentId');
      session()->forget('language_id');

      return redirect()->route('vendor.featured.cancel');
    }
  }
}
