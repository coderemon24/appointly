<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class PaytabsController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    Session::put('arrData', $arrData);
    Session::put('language_id', $arrData['language_id']);

    $paytabInfo = paytabInfo();
    $description = $paymentFor . ' via paytabs';

    try {
      $response = Http::withHeaders([
        'Authorization' => $paytabInfo['server_key'], // Server Key
        'Content-Type' => 'application/json',
      ])->post($paytabInfo['url'], [
        'profile_id' => $paytabInfo['profile_id'], // Profile ID
        'tran_type' => 'sale',
        'tran_class' => 'ecom',
        'cart_id' => uniqid(),
        'cart_description' => $description,
        'cart_currency' => $paytabInfo['currency'], // set currency by region
        'cart_amount' => round($amount, 2),
        'return' => route('vendor.featured.paytabs.notify'),
      ]);

      $responseData = $response->json();
      return response()->json(['redirectURL' => $responseData['redirect_url']]);
    } catch (\Exception $e) {
      return redirect($cancel_url);
    }
  }
  public function notify(Request $request)
  {
    $arrData = Session::get('arrData');
    $resp = $request->all();
    if ($resp['respStatus'] == "A" && $resp['respMessage'] == 'Authorised') {
      // remove this session datas
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
      session()->forget('arrData');
      session()->forget('razorpayOrderId');
      session()->forget('language_id');

      // remove session data
      return redirect()->route('vendor.featured.cancel');
    }
  }
}
