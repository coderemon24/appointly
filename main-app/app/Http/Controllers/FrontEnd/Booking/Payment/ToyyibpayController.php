<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class ToyyibpayController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $info = OnlineGateway::where('keyword', 'toyyibpay')->first();
    $paydata = json_decode($info->information, true);
    $ref = uniqid();
    session()->put('toyyibpay_ref_id', $ref);
    session()->put('arrData', $arrData);
    $bill_description = 'Package Purchase via toyyibpay';

    $name = $arrData['customer_name'];
    $email =  $arrData['customer_email'];
    $phone =  $arrData['customer_phone'];

    $some_data = array(
      'userSecretKey' => $paydata['secret_key'],
      'categoryCode' => $paydata['category_code'],
      'billName' => 'Package Purchase',
      'billDescription' => $bill_description,
      'billPriceSetting' => 1,
      'billPayorInfo' => 1,
      'billAmount' => $amount * 100,
      'billReturnUrl' => route('frontend.service_booking.toyyibpay_notify'),
      'billExternalReferenceNo' => $ref,
      'billTo' => $name,
      'billEmail' => $email,
      'billPhone' => $phone,
    );

    if ($paydata['sandbox_status'] == 1) {
      $host = 'https://dev.toyyibpay.com/'; // for development environment
    } else {
      $host = 'https://toyyibpay.com/'; // for production environment
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_URL, $host . 'index.php/api/createBill');  // sandbox will be dev.
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $some_data);

    $result = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    $response = json_decode($result, true);
    if (!empty($response[0])) {
      $redirectURL = $host . $response[0]["BillCode"];
      return response()->json(['redirectURL' => $redirectURL]);
    } else {
      return redirect($cancel_url);
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $ref = session()->get('toyyibpay_ref_id');
    if ($request['status_id'] == 1 && $request['order_id'] == $ref) {
      $bookingProcess = new ServicePaymentController();

      zoomCreate($arrData);
      calendarEventCreate($arrData);

      // store product order information in database
      $bookingInfo = $bookingProcess->storeData($arrData);
      //send whatsapp sms
      WhatsAppController::sendMessage($bookingInfo->id, "customer_booking_confirmation","new_booking");


      $type = 'service_payment_approved';
      payemntStatusMail($type, $bookingInfo->id);

      Session::put('complete', 'payment_complete');
      Session::put('paymentInfo', $bookingInfo);
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      session()->forget('cancel_url');
      return redirect()->route('frontend.services');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      return redirect()->route('frontend.service_booking.cancel');
    }
  }
}
