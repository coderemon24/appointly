<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use Razorpay\Api\Api;
use Illuminate\Http\Request;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use Razorpay\Api\Errors\SignatureVerificationError;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

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

  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $customerpaid = intval($amount);
    $title = 'Service Booking';
    $notifyURL = route('frontend.service_booking.razorpay.notify');

    // create order data
    $orderData = [
      'receipt'         => $title,
      'amount'          => ($customerpaid * 100),
      'currency'        => 'INR',
      'payment_capture' => 1 // auto capture
    ];

    $razorpayOrder = $this->api->order->create($orderData);

    $webInfo = Basic::select('website_title')->first();

    $customerName = $arrData['customer_name'];
    $customerEmail = $arrData['customer_email'];
    $customerPhone = $arrData['customer_phone'];

    // create checkout data
    $checkoutData = [
      'key'               => $this->key,
      'amount'            => $orderData['amount'],
      'name'              => $webInfo->website_title,
      'description'       => $title . ' via Razorpay.',
      'prefill'           => [
        'name'              => $customerName,
        'email'             => $customerEmail,
        'contact'           => $customerPhone
      ],
      'order_id'          => $razorpayOrder->id
    ];

    $jsonData = json_encode($checkoutData);

    // put some data in session before redirect to razorpay url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);
    session()->put('razorpayOrderId', $razorpayOrder->id);

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
     * the keys of $attributes array must follow razorpay convention.
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
      session()->forget('serviceData');

      $bookingProcess = new ServicePaymentController();

      zoomCreate($arrData);
      calendarEventCreate($arrData);

      // store product order information in the database
      $bookingInfo = $bookingProcess->storeData($arrData);
      //send whatsapp sms
      WhatsAppController::sendMessage($bookingInfo->id, "customer_booking_confirmation","new_booking");


      $type = 'service_payment_approved';
      payemntStatusMail($type, $bookingInfo->id);

      // remove all session data
      Session::put('complete', 'payment_complete');
      Session::put('paymentInfo', $bookingInfo);
      return redirect()->route('frontend.services');
    } else {
      // remove session data
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      session()->forget('razorpayOrderId');

      return redirect()->route('frontend.service_booking.cancel');
    }
  }
}
