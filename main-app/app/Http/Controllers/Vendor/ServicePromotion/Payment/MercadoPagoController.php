<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class MercadoPagoController extends Controller
{
  private $token, $sandbox_status;

  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('mercadopago')->first();
    $mercadopagoData = json_decode($data->information, true);

    $this->token = $mercadopagoData['token'];
    $this->sandbox_status = $mercadopagoData['sandbox_status'];
  }

  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $title = $paymentFor . ' via MercadoPago';
    $notifyURL = route('vendor.featured.mercadopago.notify');
    $cancelURL = route('vendor.featured.cancel');

    $vendorEmail = Auth::guard('vendor')->user()->email;

    $curl = curl_init();

    $preferenceData = [
      'items' => [
        [
          'id' => uniqid(),
          'title' => $title,
          'description' => $title . ' via MercadoPago',
          'quantity' => 1,
          'currency' => $arrData['currencyText'],
          'unit_price' => $amount
        ]
      ],
      'payer' => [
        'email' => $vendorEmail
      ],
      'back_urls' => [
        'success' => $notifyURL,
        'pending' => '',
        'failure' => $cancelURL
      ],
      'notification_url' => $notifyURL,
      'auto_return' => 'approved'
    ];

    $httpHeader = ['Content-Type: application/json'];

    $url = 'https://api.mercadopago.com/checkout/preferences?access_token=' . $this->token;

    $curlOPT = [
      CURLOPT_URL             => $url,
      CURLOPT_CUSTOMREQUEST   => 'POST',
      CURLOPT_POSTFIELDS      => json_encode($preferenceData, true),
      CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
      CURLOPT_RETURNTRANSFER  => true,
      CURLOPT_TIMEOUT         => 30,
      CURLOPT_HTTPHEADER      => $httpHeader
    ];

    curl_setopt_array($curl, $curlOPT);

    $response = curl_exec($curl);
    $responseInfo = json_decode($response, true);

    curl_close($curl);

    // put some data in session before redirect to mercadopago url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);
    session()->put('language_id', $arrData['language_id']);

    if ($this->sandbox_status == 1) {
      return redirect($responseInfo['sandbox_init_point']);
    } else {
      return redirect($responseInfo['init_point']);
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $languageId = session()->get('language_id');

    if ($request->status == 'approved') {
      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');

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

      return redirect()->route('featured.service.online.success.page');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('language_id');

      return redirect()->route('vendor.featured.cancel');
    }
  }

  public function curlCalls($url)
  {
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $curlData = curl_exec($curl);

    curl_close($curl);

    return $curlData;
  }
}
