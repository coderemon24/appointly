<?php

namespace App\Http\Controllers\Frontend\Booking\Payment;

use Session;
use Omnipay\Omnipay;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use App\Http\Controllers\WhatsAppController;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class AuthorizenetController extends Controller
{
  private $gateway;

  public function __construct()
  {
    $data = OnlineGateway::query()->whereKeyword('authorize.net')->first();
    $authorizeNetData = json_decode($data->information, true);
    $this->gateway = Omnipay::create('AuthorizeNetApi_Api');
    $this->gateway->setAuthName($authorizeNetData['login_id']);
    $this->gateway->setTransactionKey($authorizeNetData['transaction_key']);
    if ($authorizeNetData['sandbox_check'] == 1) {
      $this->gateway->setTestMode(true);
    }
  }
  public function index($arrData, $paymentFor, $cancel_url, $amount)
  {
    $customerpaid = intval($amount);
    // put some data in session before redirect to paytm url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);

    if ($arrData['opaqueDataValue'] && $arrData['opaqueDataDescriptor']) {
      // generate a unique merchant site transaction ID
      $transactionId = rand(100000000, 999999999);

      $response = $this->gateway->authorize([
        'amount' => sprintf('%0.2f', $customerpaid),
        'currency' => $arrData['currencyText'],
        'transactionId' => $transactionId,
        'opaqueDataDescriptor' => $arrData['opaqueDataDescriptor'],
        'opaqueDataValue' => $arrData['opaqueDataValue']
      ])->send();

      if ($response->isSuccessful()) {
        $bookingProcess = new ServicePaymentController();
        zoomCreate($arrData);
        calendarEventCreate($arrData);

        // store product order information in database
        $bookingInfo = $bookingProcess->storeData($arrData);
        //send whatsapp sms
        WhatsAppController::sendMessage($bookingInfo->id, "customer_booking_confirmation", "new_booking");


        $type = 'service_payment_approved';
        payemntStatusMail($type, $bookingInfo->id);

        /**
         * success process will be go here
         * remove this session datas
         */
        session()->forget('paymentFor');
        session()->forget('arrData');
        session()->forget('paymentId');
        session()->forget('serviceData');

        Session::put('complete', 'payment_complete');
        Session::put('paymentInfo', $bookingInfo);
        return redirect()->route('frontend.services');
      } else {
        //cancel payment
        session()->forget('paymentFor');
        session()->forget('arrData');
        session()->forget('paymentId');
        session()->forget('serviceData');
        return redirect()->route('frontend.service_booking.cancel');
      }
    }
  }
}
