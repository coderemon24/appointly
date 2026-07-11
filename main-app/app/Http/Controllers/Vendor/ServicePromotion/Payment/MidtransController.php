<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Midtrans\Snap;
use Illuminate\Http\Request;
use Midtrans\Config as MidtransConfig;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class MidtransController extends Controller
{
  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $data = [];
    $name = Auth::guard('vendor')->user()->username;
    $email =  Auth::guard('vendor')->user()->email;
    $phone = Auth::guard('vendor')->user()->phone;
    $data['title'] = $paymentFor;


    $paymentMethod = OnlineGateway::where('keyword', 'midtrans')->first();
    $paydata = json_decode($paymentMethod->information, true);

    // will come from database
    MidtransConfig::$serverKey = $paydata['server_key'];
    MidtransConfig::$isProduction = $paydata['is_production'] == 0 ? true : false;
    MidtransConfig::$isSanitized = true;
    MidtransConfig::$is3ds = true;
    $token = uniqid();
    Session::put('token', $token);
    Session::put('cancel_url', route('vendor.featured.cancel'));
    Session::put('arrData', $arrData);
    $params = [
      'transaction_details' => [
        'order_id' => $token,
        'gross_amount' => (int)round($amount)
      ],
      'customer_details' => [
        'first_name' => $name,
        'email' => $email,
        'phone' => $phone,
      ],
    ];

    $snapToken = Snap::getSnapToken($params);

    // put some data in session before redirect to midtrans url
    if (
      $paydata['is_production'] == 1
    ) {
      $is_production = $paydata['is_production'];
    }
    $notifyUrl = route('vendor.featured.midtrans.notify');
    $data['snapToken'] = $snapToken;
    $data['is_production'] = $is_production;
    $data['success_url'] = $notifyUrl;
    $data['_cancel_url'] = route('vendor.featured.cancel');
    $data['client_key'] = $paydata['server_key'];
    return view('frontend.payment.midtrans-membership', $data);
  }


  public function notify(Request $request)
  {
    $arrData = Session::get('arrData');
    $token = Session::get('token');
    if ($request->status_code == 200 && $token == $request->order_id) {
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
      $servicePromotion->prepareMail($featuredInfo, $arrData['language_id']);

      return redirect()->route('featured.service.online.success.page');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      return redirect()->route('vendor.featured.cancel');
    }
  }
}
