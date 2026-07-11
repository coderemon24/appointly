<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use Illuminate\Http\Request;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class PerfectMoneyController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    Session::put('arrData', $arrData);
    $success_url = route('frontend.service_booking.perfectmoney_notify');
    $paymentMethod = OnlineGateway::where('keyword', 'perfect_money')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $randomNo = substr(uniqid(), 0, 8);
    $bs = Basic::select('base_currency_text', 'base_currency_rate', 'website_title')->first();

    $val['PAYEE_ACCOUNT'] = $paydata['perfect_money_wallet_id'];;
    $val['PAYEE_NAME'] = $bs->website_title;
    $val['PAYMENT_ID'] = "$randomNo"; //random id
    $val['PAYMENT_AMOUNT'] = $amount;
    $val['PAYMENT_UNITS'] = "$bs->base_currency_text";

    $val['STATUS_URL'] = $success_url;
    $val['PAYMENT_URL'] = $success_url;
    $val['PAYMENT_URL_METHOD'] = 'GET';
    $val['NOPAYMENT_URL'] = $cancel_url;
    $val['NOPAYMENT_URL_METHOD'] = 'GET';
    $val['SUGGESTED_MEMO'] = $arrData['customer_email'];
    $val['BAGGAGE_FIELDS'] = 'IDENT';

    $data['val'] = $val;
    $data['method'] = 'post';
    $data['website_title'] = $bs->website_title;
    $data['url'] = 'https://perfectmoney.com/api/step1.asp';

    Session::put('payment_id', $randomNo);
    Session::put('amount', $amount);
    return view('frontend.payment.perfect-money')->with('data', $data);
  }
  public function notify(Request $request)
  {
    $arrData = Session::get('arrData');
    $amo = $request['PAYMENT_AMOUNT'];
    $track = $request['PAYMENT_ID'];
    $id = Session::get('payment_id');
    $final_amount = Session::get('amount');
    $paymentMethod = OnlineGateway::where('keyword', 'perfect_money')->first();
    $perfectMoneyInfo = json_decode($paymentMethod->information, true);
    $bs = Basic::select('base_currency_text', 'base_currency_rate', 'website_title')->first();

    if ($request->PAYEE_ACCOUNT == $perfectMoneyInfo['perfect_money_wallet_id']  && $track == $id && $amo == round($final_amount, 2)) {
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
