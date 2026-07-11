<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Session;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class YocoController extends Controller
{
  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $cancel_url = route('vendor.featured.cancel');
    $paymentMethod = OnlineGateway::where('keyword', 'yoco')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $response = Http::withHeaders([
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . $paydata['secret_key'],
    ])->post('https://payments.yoco.com/api/checkouts', [
      'amount' => $amount * 100,
      'currency' => 'ZAR',
      'successUrl' => route('vendor.featured.yoco.notify'),
      'cancelUrl' => $cancel_url
    ]);


    Session::put('arrData', $arrData);
    $responseData = $response->json();
    if (array_key_exists('redirectUrl', $responseData)) {
      Session::put('yoco_id', $responseData['id']);
      Session::put('s_key', $paydata['secret_key']);
      Session::put('amount', $amount);
      Session::put('language_id', $arrData['language_id']);
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
    $id = Session::get('yoco_id');
    $s_key = Session::get('s_key');
    $paymentMethod = OnlineGateway::where('keyword', 'yoco')->first();
    $paydata = $paymentMethod->convertAutoData();
    if ($id && $paydata['secret_key'] == $s_key) {
      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('razorpayOrderId');
      $languageId = session()->get('language_id');

      $servicePromotion = new ServicePromotionController();

      // store product order information in database
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

      // generate an invoice in pdf format
      $invoice = $servicePromotion->generateInvoice($featuredInfo);

      // then, update the invoice field info in database
      $featuredInfo->update(['invoice' => $invoice]);

      // send a mail to the customer with the invoice
      $servicePromotion->prepareMail($featuredInfo, $languageId);
      // remove all session data
      return redirect()->route('featured.service.online.success.page');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('razorpayOrderId');
      session()->forget('language_id');

      // remove session data
      return redirect()->route('vendor.featured.cancel');
    }
  }
}
