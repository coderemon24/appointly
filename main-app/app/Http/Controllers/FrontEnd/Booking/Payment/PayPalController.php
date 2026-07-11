<?php

namespace App\Http\Controllers\FrontEnd\Booking\Payment;

use PayPal\Api\Item;
use PayPal\Api\Payer;
use PayPal\Api\Amount;
use PayPal\Api\Payment;
use PayPal\Api\ItemList;
use PayPal\Api\Transaction;
use PayPal\Rest\ApiContext;
use Illuminate\Http\Request;
use PayPal\Api\RedirectUrls;
use PayPal\Api\PaymentExecution;
use App\Http\Controllers\Controller;
use PayPal\Auth\OAuthTokenCredential;
use Illuminate\Support\Facades\Config;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class PayPalController extends Controller
{
  private $api_context;

  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('paypal')->first();
    $paypalData = json_decode($data->information, true);

    $paypal_conf = Config::get('paypal');
    $paypal_conf['client_id'] = $paypalData['client_id'];
    $paypal_conf['secret'] = $paypalData['client_secret'];
    $paypal_conf['settings']['mode'] = $paypalData['sandbox_status'] == 1 ? 'sandbox' : 'live';

    $this->api_context = new ApiContext(
      new OAuthTokenCredential(
        $paypal_conf['client_id'],
        $paypal_conf['secret']
      )
    );

    $this->api_context->setConfig($paypal_conf['settings']);
  }

  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {

    $customerPaid = $amount;
    $currencyInfo = $this->getCurrencyInfo();

    // changing the currency before redirect to PayPal
    if ($currencyInfo->base_currency_text !== 'USD') {
      $rate = floatval($currencyInfo->base_currency_rate);
      $convertedTotal = $customerPaid / $rate;
    }

    $paypalTotal = $currencyInfo->base_currency_text === 'USD' ? $customerPaid : $convertedTotal;

    $title = 'Service Booking';
    $notifyURL = route('frontend.service_booking.paypal.notify');
    $cancelURL = route('frontend.service_booking.cancel');

    $payer = new Payer();
    $payer->setPaymentMethod('paypal');

    $item_1 = new Item();
    $item_1->setName($title)
      /** item name **/
      ->setCurrency('USD')
      ->setQuantity(1)
      ->setPrice($paypalTotal);
    /** unit price **/
    $item_list = new ItemList();
    $item_list->setItems(array($item_1));

    $amount = new Amount();
    $amount->setCurrency('USD')
      ->setTotal($paypalTotal);

    $transaction = new Transaction();
    $transaction->setAmount($amount)
      ->setItemList($item_list)
      ->setDescription($title . ' via PayPal');

    $redirect_urls = new RedirectUrls();
    $redirect_urls->setReturnUrl($notifyURL)
      /** Specify return URL **/
      ->setCancelUrl($cancelURL);

    $payment = new Payment();
    $payment->setIntent('Sale')
      ->setPayer($payer)
      ->setRedirectUrls($redirect_urls)
      ->setTransactions(array($transaction));

    try {
      $payment->create($this->api_context);
    } catch (\Exception $ex) {
      return redirect($cancelURL)->with('error', $ex->getMessage());
    }


    foreach ($payment->getLinks() as $link) {
      if ($link->getRel() == 'approval_url') {
        $redirectURL = $link->getHref();
        break;
      }
    }

    // put some data in session before redirect to paypal url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);
    session()->put('paymentId', $payment->getId());

    if (isset($redirectURL)) {
      /** redirect to paypal **/
      return response()->json(['redirectURL' => $redirectURL]);
    }
  }

  public function notify(Request $request)
  {
    // get the information from session
    $paymentPurpose = session()->get('paymentFor');
    $arrData = session()->get('arrData');
    $paymentId = session()->get('paymentId');

    $urlInfo = $request->all();

    if (empty($urlInfo['token']) || empty($urlInfo['PayerID'])) {
      if ($paymentPurpose == 'service booking') {
        return redirect()->route('frontend.services');
      }
    }

    /** Execute The Payment **/
    $payment = Payment::get($paymentId, $this->api_context);
    $execution = new PaymentExecution();
    $execution->setPayerId($urlInfo['PayerID']);
    $result = $payment->execute($execution, $this->api_context);

    if ($result->getState() == 'approved') {
      $purchaseProcess = new ServicePaymentController();

      zoomCreate($arrData);
      calendarEventCreate($arrData);

      // store service booking information in database
      $bookingInfo = $purchaseProcess->storeData($arrData);
      //send whatsapp sms
      WhatsAppController::sendMessage($bookingInfo->id, "customer_booking_confirmation","new_booking");


      $type = 'service_payment_approved';
      payemntStatusMail($type, $bookingInfo->id);

      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('paymentId');
      session()->forget('serviceData');

      // redirect url here after succesfully payment
      Session::put('complete', 'payment_complete');
      Session::put('paymentInfo', $bookingInfo);
      return redirect()->route('frontend.services');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      session()->forget('paymentId');

      if ($paymentPurpose == 'service booking') {
        return redirect()->back();
      }
    }
  }
}
