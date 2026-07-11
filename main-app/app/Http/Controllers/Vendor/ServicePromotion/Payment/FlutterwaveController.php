<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Response;
use App\Models\Vendor;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class FlutterwaveController extends Controller
{
  private $public_key, $secret_key;

  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('flutterwave')->first();
    $flutterwaveData = json_decode($data->information, true);

    $this->public_key = $flutterwaveData['public_key'];
    $this->secret_key = $flutterwaveData['secret_key'];
  }

  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $title = $paymentFor;
    $notifyURL = route('vendor.featured.flutterwave.notify');


    $vendor = Vendor::join('vendor_infos', 'vendor_infos.vendor_id', '=', 'vendors.id')
      ->where('vendor_infos.vendor_id', $arrData['vendor_id'])
      ->where('vendor_infos.language_id', $arrData['language_id'])
      ->select('vendor_infos.name', 'vendors.email', 'vendors.phone')
      ->first();

    $vendorName = $vendor->name;
    $vendorEmail = $vendor->email;
    $vendorPhone = $vendor->phone;


    // send payment to flutterwave for processing
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode([
        'tx_ref' => 'FLW | ' . time(),
        'amount' => intval($amount),
        'currency' => $arrData['currencyText'],
        'redirect_url' => $notifyURL,
        'payment_options' => 'card,banktransfer',
        'customer' => [
          'email' => $vendorEmail,
          'phone_number' => $vendorPhone,
          'name' => $vendorName
        ],
        'customizations' => [
          'title' => $title,
          'description' => $title . ' via Flutterwave.'
        ]
      ]),
      CURLOPT_HTTPHEADER => array(
        'authorization: Bearer ' . $this->secret_key,
        'content-type: application/json'
      )
    ));
    $response = curl_exec($curl);

    curl_close($curl);

    $responseData = json_decode($response, true);

    //curl end

    // put some data in session before redirect to flutterwave url
    session()->put('arrData', $arrData);
    session()->put('success_url', $success_url);
    session()->put('language_id', $arrData['language_id']);

    // redirect to payment
    if ($responseData['status'] === 'success') {
      $redirectUrl = $responseData['data']['link'];

      // Return the redirect URL as part of the JSON response
      return Response::json(['redirectURL' => $redirectUrl]);
    } else {
      return Response::json(['error' => $responseData['message']]);
    }
  }

  public function notify(Request $request)
  {
    // get the information from session

    $arrData = session()->get('arrData');
    $success_url = session()->get('success_url');
    $languageId = session()->get('language_id');

    $urlInfo = $request->all();

    if ($urlInfo['status'] == 'successful') {
      $txId = $urlInfo['transaction_id'];

      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/{$txId}/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          'authorization: Bearer ' . $this->secret_key,
          'content-type: application/json'
        )
      ));

      $response = curl_exec($curl);
      curl_close($curl);
      $responseData = json_decode($response, true);
      if ($responseData['status'] === 'success') {
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
        session()->forget('paymentFor');
        session()->forget('arrData');
        session()->forget('language_id');
        return redirect($success_url);
      } else {
        session()->forget('paymentFor');
        session()->forget('arrData');
        session()->forget('language_id');
        session()->forget('success_url');
        return redirect()->route('vendor.featured.cancel');
      }
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('language_id');
      session()->forget('success_url');

      return redirect()->route('vendor.featured.cancel');
    }
  }
}
