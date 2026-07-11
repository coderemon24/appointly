<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class ToyyibpayController extends Controller
{
  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $info = OnlineGateway::where('keyword', 'toyyibpay')->first();
    $paydata = json_decode($info->information, true);
    $ref = uniqid();
    session()->put('toyyibpay_ref_id', $ref);
    session()->put('arrData', $arrData);
     session()->put('language_id',$arrData['language_id']);
    $bill_description = 'Package Purchase via toyyibpay';

    $name = Auth::guard('vendor')->user()->username;
    $email =  Auth::guard('vendor')->user()->email;
    $phone =  Auth::guard('vendor')->user()->phone;

    $some_data = array(
      'userSecretKey' => $paydata['secret_key'],
      'categoryCode' => $paydata['category_code'],
      'billName' => 'Package Purchase',
      'billDescription' => $bill_description,
      'billPriceSetting' => 1,
      'billPayorInfo' => 1,
      'billAmount' => $amount * 100,
      'billReturnUrl' => route('vendor.featured.toyyibpay.notify'),
      'billExternalReferenceNo' => $ref,
      'billTo' => $name,
      'billEmail' => $email,
      'billPhone' => $phone,
    );

    if ($paydata['sandbox_status'] == 1) {
      $host = 'https://dev.toyyibpay.com/'; // for development environment
    } else {
      $host = 'https://toyyibpay.com/'; // for production environment
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_URL, $host . 'index.php/api/createBill');  // sandbox will be dev.
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $some_data);

    $result = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    $response = json_decode($result, true);
    if (!empty($response[0])) {
      $redirectURL = $host . $response[0]["BillCode"];
      return response()->json(['redirectURL' => $redirectURL]);
    } else {
      return redirect()->route('vendor.featured.cancel');
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $ref = session()->get('toyyibpay_ref_id');
    if ($request['status_id'] == 1 && $request['order_id'] == $ref) {
      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('razorpayOrderId');
      $languageId = session()->get('language_id');

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
      // remove all session data
      return redirect()->route('featured.service.online.success.page');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('razorpayOrderId');
      session()->forget('language_id');

      // remove session data
      return redirect()->route('vendor.featured.cancel');
    }
  }
}
