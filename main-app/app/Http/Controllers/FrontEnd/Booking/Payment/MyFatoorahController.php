<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use Illuminate\Http\Request;
use Basel\MyFatoorah\MyFatoorah;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class MyFatoorahController extends Controller
{
  private $myfatoorah;

  public function __construct()
  {
    $info = OnlineGateway::where('keyword', 'myfatoorah')->firstOrFail();
    $bs = Basic::first();

    $information = json_decode($info->information, true);
    config([
      'myfatoorah.token' => $information['token'] ?? '',
      'myfatoorah.DisplayCurrencyIso' => $bs->base_currency_text ?? 'KWD',
      'myfatoorah.CallBackUrl' => route('frontend.service_booking.myfatoorah_notify'),
      'myfatoorah.ErrorUrl' => route('frontend.service_booking.cancel'),
    ]);

    $sandboxMode = isset($information['sandbox_status']) && $information['sandbox_status'] == 1;

    $this->myfatoorah = MyFatoorah::getInstance($sandboxMode);
  }

  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $info = OnlineGateway::where('keyword', 'myfatoorah')->first();
    $information = json_decode($info->information, true);
    $paymentFor = Session::get('paymentFor');

    $random_1 = rand(999, 9999);
    $random_2 = rand(9999, 99999);

    $result = $this->myfatoorah->sendPayment(
      $arrData['customer_name'],
      intval($amount),
      [
        'CustomerMobile' => $information['sandbox_status'] == 1 ? '56562123544' : $arrData['customer_phone'],
        'CustomerReference' => "$random_1",  //orderID
        'UserDefinedField' => "$random_2", //clientID
        "InvoiceItems" => [
          [
            "ItemName" => "Package Purchase or Extends",
            "Quantity" => 1,
            "UnitPrice" => intval($amount)
          ]
        ]
      ]
    );

    if ($result && $result['IsSuccess'] == true) {
      Session::put('myfatoorah_payment_type', $paymentFor);
      Session::put("arrData", $arrData);
      $redirectURL = $result['Data']['InvoiceURL'];
      return response()->json(['redirectURL' => $redirectURL]);
    } else {
      return redirect($cancel_url);
    }
  }

  public function notify(Request $request)
  {
    // get the information from session
    $arrData = session()->get('arrData');
    if (!empty($request->paymentId)) {
      $result = $this->myfatoorah->getPaymentStatus('paymentId', $request->paymentId);
      if ($result && $result['IsSuccess'] == true && $result['Data']['InvoiceStatus'] == "Paid") {
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
        return redirect()->route('frontend.services');
      } else {
        session()->forget('paymentFor');
        session()->forget('arrData');
        session()->forget('serviceData');
        return redirect()->route('frontend.service_booking.cancel');
      }
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      return redirect()->route('frontend.service_booking.cancel');
    }
  }
}
