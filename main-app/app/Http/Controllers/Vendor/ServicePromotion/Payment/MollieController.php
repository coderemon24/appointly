<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Illuminate\Http\Request;
use Mollie\Laravel\Facades\Mollie;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class MollieController extends Controller
{
  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $title = $paymentFor;
    $notifyURL = route('vendor.featured.mollie.notify');

    /**
     * we must send the correct number of decimals.
     * thus, we have used sprintf() function for format.
     */
    $payment = Mollie::api()->payments->create([
      'amount' => [
        'currency' => $arrData['currencyText'],
        'value' => sprintf('%0.2f', $amount)
      ],
      'description' => $title . ' via Mollie',
      'redirectUrl' => $notifyURL
    ]);
    // put some data in session before redirect to mollie url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);
    session()->put('payment', $payment);
    session()->put('language_id', $arrData['language_id']);

    $checkoutUrl = $payment->getCheckoutUrl();
    return response()->json(['redirectURL' => $checkoutUrl]);
  }

  public function notify(Request $request)
  {
    // get the information from session
    $arrData = session()->get('arrData');
    $payment = session()->get('payment');
    $languageId = session()->get('language_id');

    $paymentInfo = Mollie::api()->payments->get($payment->id);

    if ($paymentInfo->isPaid() == true) {
      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('payment');

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
      session()->forget('payment');
      session()->forget('language_id');

      return redirect()->route('vendor.featured.cancel');
    }
  }
}
