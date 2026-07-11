<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Anand\LaravelPaytmWallet\Facades\PaytmWallet;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class PaytmController extends Controller
{
  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $notifyURL = route('vendor.featured.paytm.notify');
    $vendorEmail = Auth::guard('vendor')->user()->email;
    $vendorPhone = Auth::guard('vendor')->user()->phone;
    $payment = PaytmWallet::with('receive');
    $payment->prepare([
      'order' => time(),
      'user' => uniqid(),
      'mobile_number' => $vendorPhone,
      'email' => $vendorEmail,
      'amount' => round($amount, 2),
      'callback_url' => $notifyURL
    ]);

    // put some data in session before redirect to paytm url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);
    session()->put('language_id', $arrData['language_id']);
    return $payment->receive();
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $languageId = session()->get('language_id');

    $transaction = PaytmWallet::with('receive');

    // this response is needed to check the transaction status
    $response = $transaction->response();

    if ($transaction->isSuccessful()) {
      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');

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

      return redirect()->route('featured.service.online.success.page');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('language_id');

      return redirect()->route('vendor.featured.cancel');
    }
  }
}
