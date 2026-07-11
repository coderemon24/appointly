<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Razorpay\Api\Api;
use Illuminate\Http\Request;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use Razorpay\Api\Errors\SignatureVerificationError;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class RazorpayController extends Controller
{
  private $key, $secret, $api;

  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('razorpay')->first();
    $razorpayData = json_decode($data->information, true);

    $this->key = $razorpayData['key'];
    $this->secret = $razorpayData['secret'];

    $this->api = new Api($this->key, $this->secret);
  }

  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $title = 'Service Featured';
    $notifyURL = route('vendor.featured.razorpay.notify');

    // create order data
    $orderData = [
      'receipt'         => $title,
      'amount'          => ($amount * 100),
      'currency'        => 'INR',
      'payment_capture' => 1 // auto capture
    ];

    $razorpayOrder = $this->api->order->create($orderData);

    $webInfo = Basic::select('website_title')->first();

    $vendorName = Auth::guard('vendor')->user()->username;
    $vendorEmail = Auth::guard('vendor')->user()->email;
    $vendorPhone = Auth::guard('vendor')->user()->phone;

    // create checkout data
    $checkoutData = [
      'key'               => $this->key,
      'amount'            => $orderData['amount'],
      'name'              => $webInfo->website_title,
      'description'       => $title . ' via Razorpay.',
      'prefill'           => [
        'name'              => $vendorName,
        'email'             => $vendorEmail,
        'contact'           => $vendorPhone
      ],
      'order_id'          => $razorpayOrder->id
    ];

    $jsonData = json_encode($checkoutData);

    // put some data in session before redirect to razorpay url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);
    session()->put('razorpayOrderId', $razorpayOrder->id);
    session()->put('language_id', $arrData['language_id']);
    return view('frontend.payment.razorpay', compact('jsonData', 'notifyURL'));
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $razorpayOrderId = session()->get('razorpayOrderId');

    $urlInfo = $request->all();

    // assume that the transaction was successful
    $success = true;

    /**
     * either razorpay_order_id or razorpay_subscription_id must be present.
     * the keys of $attributes array must be follow razorpay convention.
     */
    try {
      $attributes = [
        'razorpay_order_id' => $razorpayOrderId,
        'razorpay_payment_id' => $urlInfo['razorpayPaymentId'],
        'razorpay_signature' => $urlInfo['razorpaySignature']
      ];

      $this->api->utility->verifyPaymentSignature($attributes);
    } catch (SignatureVerificationError $e) {
      $success = false;
    }

    if ($success === true) {
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
