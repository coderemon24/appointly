<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Response;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class PaystackController extends Controller
{
  private $api_key;

  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('paystack')->first();
    $paystackData = json_decode($data->information, true);

    $this->api_key = $paystackData['key'];
  }

  public function index($arrData, $paymentFor, $success_url, $amount)
  {

    $notifyURL = route('vendor.featured.paystack.notify');
    $vendorEmail = Auth::guard('vendor')->user()->email;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL            => 'https://api.paystack.co/transaction/initialize',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST  => 'POST',
      CURLOPT_POSTFIELDS     => json_encode([
        'amount'       => ($amount * 100),
        'email'        => $vendorEmail,
        'callback_url' => $notifyURL
      ]),
      CURLOPT_HTTPHEADER     => [
        'authorization: Bearer ' . $this->api_key,
        'content-type: application/json',
        'cache-control: no-cache'
      ]
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $transaction = json_decode($response, true);

    // put some data in session before redirect to paystack url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);
    session()->put('language_id', $arrData['language_id']);

    if ($transaction['status'] == true) {
      return Response::json(['redirectURL' => $transaction['data']['authorization_url']]);
    } else {
      return redirect()->back()->with('error', 'Error: ' . $transaction['message'])->withInput();
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $languageId = session()->get('language_id');

    $urlInfo = $request->all();

    if ($urlInfo['trxref'] === $urlInfo['reference']) {
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
