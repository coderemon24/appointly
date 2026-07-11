<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Cartalyst\Stripe\Laravel\Facades\Stripe;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class StripeController extends Controller
{
  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $amount = intval($amount);
    $currencyInfo = $this->getCurrencyInfo();

    // changing the currency before redirect to Stripe
    if ($currencyInfo->base_currency_text !== 'USD') {
      $rate = floatval($currencyInfo->base_currency_rate);
      $convertedTotal = round(($amount / $rate), 2);
    }

    $stripeTotal = $currencyInfo->base_currency_text === 'USD' ? $amount : $convertedTotal;

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
          // store product order information in database
          $servicePromotion = new ServicePromotionController();
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

          $languageId = $arrData['language_id'];
          // send a mail to the customer with the invoice
          $servicePromotion->prepareMail($featuredInfo, $languageId);

          return redirect()->route('featured.service.online.success.page');
        } else {

          return redirect()->route('vendor.featured.cancel');
        }
      } catch (Exception $e) {
        Session::flash('error', $e->getMessage());

        return redirect()->route('vendor.featured.cancel');
      }
    } catch (Exception $e) {
      Session::flash('error', $e->getMessage());
      return redirect()->route('vendor.featured.cancel');
    }
  }
}
