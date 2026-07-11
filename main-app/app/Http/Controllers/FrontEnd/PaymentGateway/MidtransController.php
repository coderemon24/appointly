<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use Midtrans\Snap;
use App\Models\Shop\Product;
use Illuminate\Http\Request;
use Midtrans\Config as MidtransConfig;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class MidtransController extends Controller
{
  public function index($arrData, $productList)
  {
    $data = [];
    $name = $arrData['billing_name'];
    $email =  $arrData['billing_email'];
    $phone = $arrData['billing_phone'];
    $data['title'] = 'Purchase Product';


    $paymentMethod = OnlineGateway::where('keyword', 'midtrans')->first();
    $paydata = json_decode($paymentMethod->information, true);

    // will come from database
    MidtransConfig::$serverKey = $paydata['server_key'];
    MidtransConfig::$isProduction = $paydata['is_production'] == 0 ? true : false;
    MidtransConfig::$isSanitized = true;
    MidtransConfig::$is3ds = true;
    $token = uniqid();
    Session::put('token', $token);
    Session::put('cancel_url', route('shop.purchase_product.cancel'));
    Session::put('arrData', $arrData);
    Session::put('productList', $productList);
    $params = [
      'transaction_details' => [
        'order_id' => $token,
        'gross_amount' => (int)round($arrData['grandTotal'])
      ],
      'customer_details' => [
        'first_name' => $name,
        'email' => $email,
        'phone' => $phone,
      ],
    ];

    $snapToken = Snap::getSnapToken($params);

    // put some data in session before redirect to midtrans url
    if (
      $paydata['is_production'] == 1
    ) {
      $is_production = $paydata['is_production'];
    }
    $success_url = route('shop.purchase_product.midtrans.notify');
    $data['snapToken'] = $snapToken;
    $data['is_production'] = $is_production;
    $data['success_url'] = $success_url;
    $data['_cancel_url'] = route('shop.purchase_product.cancel');
    $data['client_key'] = $paydata['server_key'];
    return view('payments.midtrans-membership', $data);
  }


  public function notify(Request $request)
  {
    $arrData = Session::get('arrData');
    $token = Session::get('token');
    $productList = Session::get('productList');
    if ($request->status_code == 200 && $token == $request->order_id) {
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
