<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use Illuminate\Http\Request;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class PerfectMoneyController extends Controller
{
  public function index($arrData, $productList)
  {
    Session::put('arrData', $arrData);
    $success_url = route('frontend.service_booking.perfectmoney_notify');
    $paymentMethod = OnlineGateway::where('keyword', 'perfect_money')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $randomNo = substr(uniqid(), 0, 8);
    $bs = Basic::select('base_currency_text', 'base_currency_rate', 'website_title')->first();

    $val['PAYEE_ACCOUNT'] = $paydata['perfect_money_wallet_id'];;
    $val['PAYEE_NAME'] = $bs->website_title;
    $val['PAYMENT_ID'] = "$randomNo"; //random id
    $val['PAYMENT_AMOUNT'] = $arrData['grandTotal'];
    $val['PAYMENT_UNITS'] = "$bs->base_currency_text";

    $val['STATUS_URL'] = $success_url;
    $val['PAYMENT_URL'] = $success_url;
    $val['PAYMENT_URL_METHOD'] = 'GET';
    $val['NOPAYMENT_URL'] = route('shop.purchase_product.cancel');
    $val['NOPAYMENT_URL_METHOD'] = 'GET';
    $val['SUGGESTED_MEMO'] = $arrData['customer_email'];
    $val['BAGGAGE_FIELDS'] = 'IDENT';

    $data['val'] = $val;
    $data['method'] = 'post';
    $data['website_title'] = $bs->website_title;
    $data['url'] = 'https://perfectmoney.com/api/step1.asp';

    Session::put('payment_id', $randomNo);
    Session::put('productList', $productList);
    Session::put('amount', $arrData['grandTotal']);
    return view('frontend.payment.perfect-money')->with('data', $data);
  }
  public function notify(Request $request)
  {
    $arrData = Session::get('arrData');
    $productList = Session::get('productList');
    $amo = $request['PAYMENT_AMOUNT'];
    $track = $request['PAYMENT_ID'];
    $id = Session::get('payment_id');
    $final_amount = Session::get('amount');
    $paymentMethod = OnlineGateway::where('keyword', 'perfect_money')->first();
    $perfectMoneyInfo = json_decode($paymentMethod->information, true);

    if ($request->PAYEE_ACCOUNT == $perfectMoneyInfo['perfect_money_wallet_id']  && $track == $id && $amo == round($final_amount, 2)) {
      $purchaseProcess = new PurchaseProcessController();

      // store product order information in database
      $orderInfo = $purchaseProcess->storeData($productList, $arrData);

      // then subtract each product quantity from respective product stock
      foreach ($productList as $key => $item) {
        $product = Product::query()->find($key);

        if ($product->product_type == 'physical') {
          $stock = $product->stock - intval($item['quantity']);

          $product->update(['stock' => $stock]);
        }
      }

      //transaction create
      $after_balance = NULL;
      $pre_balance = NULL;
      $transactionData = [
        'transaction_id' => time(),
        'vendor_id' => $arrData['vendor_id'] ?? 0,
        'transaction_type' => 'product_purchase',
        'pre_balance' => $pre_balance,
        'actual_total' => $arrData['grandTotal'],
        'after_balance' => $after_balance,
        'admin_profit' => $arrData['grandTotal'],
        'payment_method' => $arrData['paymentMethod'],
        'currency_symbol' => $arrData['currencySymbol'],
        'currency_symbol_position' => $arrData['currencySymbolPosition'],
        'payment_status' => $arrData['paymentStatus'],
      ];
      store_transaction($transactionData);

      // generate an invoice in pdf format
      $invoice = $purchaseProcess->generateInvoice($orderInfo, $productList);

      // then, update the invoice field info in database
      $orderInfo->update(['invoice' => $invoice]);

      // send a mail to the customer with the invoice
      $purchaseProcess->prepareMail($orderInfo);

      // remove all session data
      session()->forget('productCart');
      session()->forget('discount');
      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('paymentId');
      session()->forget('productList');
      return redirect()->route('shop.purchase_product.complete');
    } else {
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('paymentId');

      // remove session data
      session()->forget('productCart');
      session()->forget('discount');

      return redirect()->route('shop.purchase_product.cancel');
    }
  }
}
