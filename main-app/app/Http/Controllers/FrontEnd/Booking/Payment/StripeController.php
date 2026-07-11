<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use Cartalyst\Stripe\Laravel\Facades\Stripe;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class StripeController extends Controller
{
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    // card validation end
    $customerpaid = intval($amount);
    $currencyInfo = $this->getCurrencyInfo();

    // changing the currency before redirect to Stripe
    if ($currencyInfo->base_currency_text !== 'USD') {
      $rate = floatval($currencyInfo->base_currency_rate);
      $convertedTotal = round(($customerpaid / $rate), 2);
    }

    $stripeTotal = $currencyInfo->base_currency_text === 'USD' ? $customerpaid : $convertedTotal;

    try {
      // initialize stripe
      $stripe = new Stripe();
      $stripe = Stripe::make(Config::get('services.stripe.secret'));

      try {

        // generate charge
        $charge = $stripe->charges()->create([
          'source' => $arrData['stripeToken'],
          'currency' => 'USD',
          'amount'   => $stripeTotal,
        ]);

        if ($charge['status'] == 'succeeded') {
          $bookingProcess = new ServicePaymentController();

          zoomCreate($arrData);
          calendarEventCreate($arrData);

          // store product order information in database
          $bookingInfo = $bookingProcess->storeData($arrData);
          //send whatsapp sms
          WhatsAppController::sendMessage($bookingInfo->id, "customer_booking_confirmation","new_booking");

          //send mail

          $type = 'service_payment_approved';
          payemntStatusMail($type, $bookingInfo->id);

          Session::put('complete', 'payment_complete');
          Session::put('paymentInfo', $bookingInfo);
          session()->forget('serviceData');

          return redirect()->route('frontend.services');
        } else {
          session()->forget('serviceData');
          return redirect()->route('frontend.service_booking.cancel');
        }
      } catch (Exception $e) {
        Session::flash('error', $e->getMessage());
        Session::forget('serviceData');
        return redirect()->route('frontend.service_booking.cancel');
      }
    } catch (Exception $e) {
      Session::flash('error', $e->getMessage());
      Session::forget('serviceData');
      return redirect()->route('frontend.service_booking.cancel');
    }
  }
}
