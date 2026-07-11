<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Illuminate\Http\Request;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class PerfectMoneyController extends Controller
{
  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    Session::put('arrData', $arrData);
    $notifyUrl = route('vendor.featured.perfectmoney.notify');
    $paymentMethod = OnlineGateway::where('keyword', 'perfect_money')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $randomNo = substr(uniqid(), 0, 8);
    $bs = Basic::select('base_currency_text', 'base_currency_rate', 'website_title')->first();

    $val['PAYEE_ACCOUNT'] = $paydata['perfect_money_wallet_id'];;
    $val['PAYEE_NAME'] = $bs->website_title;
    $val['PAYMENT_ID'] = "$randomNo"; //random id
    $val['PAYMENT_AMOUNT'] = $amount;
    $val['PAYMENT_UNITS'] = "$bs->base_currency_text";

    $val['STATUS_URL'] = $notifyUrl;
    $val['PAYMENT_URL'] = $notifyUrl;
    $val['PAYMENT_URL_METHOD'] = 'GET';
    $val['NOPAYMENT_URL'] = route('vendor.featured.cancel');
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

      return redirect()->route('featured.service.online.success.page');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('language_id');

      return redirect()->route('vendor.featured.cancel');
    }
  }
}
