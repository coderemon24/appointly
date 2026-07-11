<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class XenditController extends Controller
{
  public function index($arrData, $paymentFor, $success_url, $amount)
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
      'success_redirect_url' => route('vendor.featured.xendit.notify')
    ]);
    $response = $data_request->json();
    if (isset($response['error_code']) && isset($response['message'])) {
      session()->flash('warning', $response['message']);
      return redirect()->back();
    }

    if (!empty($response['invoice_url'])) {
      session()->put('cancel_url', route('vendor.featured.cancel'));
      session()->put("arrData", $arrData);
      session()->put('xendit_id', $response['id']);
      session()->put('secret_key', $secret_key);
      session()->put('language_id', $arrData['language_id']);
      $redirectURL = $response['invoice_url'];
      return response()->json(['redirectURL' => $redirectURL]);
    } else {
      return redirect()->route('vendor.featured.cancel');
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
