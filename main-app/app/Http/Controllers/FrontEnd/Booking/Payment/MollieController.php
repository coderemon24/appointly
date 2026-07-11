<?php


namespace App\Http\Controllers\Frontend\Booking\Payment;

use Illuminate\Http\Request;
use Mollie\Laravel\Facades\Mollie;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class MollieController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $title = 'Service Booking';
    $notifyURL = route('frontend.service_booking.mollie.notify');

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

    $checkoutUrl = $payment->getCheckoutUrl();
    return response()->json(['redirectURL' => $checkoutUrl]);
  }

  public function notify(Request $request)
  {
    // get the information from session
    $arrData = session()->get('arrData');
    $payment = session()->get('payment');

    $paymentInfo = Mollie::api()->payments->get($payment->id);

    if ($paymentInfo->isPaid() == true) {
      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('payment');
      session()->forget('serviceData');

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
      return redirect()->route('frontend.services');
    } else {
      $request->session()->forget('paymentFor');
      $request->session()->forget('arrData');
      $request->session()->forget('payment');
      $request->session()->forget('serviceData');
      return redirect()->route('frontend.service_booking.cancel');
    }
  }
}
