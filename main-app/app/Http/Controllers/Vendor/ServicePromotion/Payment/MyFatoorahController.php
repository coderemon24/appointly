<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Session;
use Illuminate\Http\Request;
use Basel\MyFatoorah\MyFatoorah;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

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
      'myfatoorah.CallBackUrl' => route('vendor.featured.myfatoorah.notify'),
      'myfatoorah.ErrorUrl' => route('vendor.featured.cancel'),
    ]);

    $sandboxMode = isset($information['sandbox_status']) && $information['sandbox_status'] == 1;

    $this->myfatoorah = MyFatoorah::getInstance($sandboxMode);
  }

  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $info = OnlineGateway::where('keyword', 'myfatoorah')->first();
    $information = json_decode($info->information, true);
    $paymentFor = Session::get('paymentFor');

    $random_1 = rand(999, 9999);
    $random_2 = rand(9999, 99999);

    $result = $this->myfatoorah->sendPayment(
      Auth::guard('vendor')->user()->username,
      intval($amount),
      [
        'CustomerMobile' => $information['sandbox_status'] == 1 ? '56562123544' : Auth::guard('vendor')->user()->phone,
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
      return redirect()->route('vendor.featured.cancel');
    }
  }

  public function notify(Request $request)
  {
    // get the information from session
    $arrData = session()->get('arrData');
    if (!empty($request->paymentId)) {
      $result = $this->myfatoorah->getPaymentStatus('paymentId', $request->paymentId);
      if ($result && $result['IsSuccess'] == true && $result['Data']['InvoiceStatus'] == "Paid") {
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
        $servicePromotion->prepareMail($featuredInfo, $arrData['language_id']);
        session()->forget('paymentFor');
        session()->forget('arrData');
        session()->forget('serviceData');
        return redirect()->route('featured.service.online.success.page');
      } else {
        session()->forget('paymentFor');
        session()->forget('arrData');
        session()->forget('serviceData');
        return redirect()->route('vendor.featured.cancel');
      }
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('serviceData');
      return redirect()->route('vendor.featured.cancel');
    }
  }
}
