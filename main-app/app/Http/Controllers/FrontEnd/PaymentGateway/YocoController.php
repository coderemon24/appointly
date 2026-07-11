<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class YocoController extends Controller
{
  public function index($arrData, $productList)
  {
    $paymentMethod = OnlineGateway::where('keyword', 'yoco')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $response = Http::withHeaders([
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . $paydata['secret_key'],
    ])->post('https://payments.yoco.com/api/checkouts', [
      'amount' => $arrData['grandTotal'] * 100,
      'currency' => 'ZAR',
      'successUrl' => route('shop.purchase_product.yoco.notify'),
      'cancelUrl' => route('shop.purchase_product.cancel')
    ]);


    Session::put('arrData', $arrData);
    $responseData = $response->json();
    if (array_key_exists('redirectUrl', $responseData)) {
      Session::put('yoco_id', $responseData['id']);
      Session::put('s_key', $paydata['secret_key']);
      Session::put('amount', $arrData['grandTotal']);
      Session::put('productList', $productList);
      Session::put('cancel_url', route('shop.purchase_product.cancel'));
      //redirect for received payment from user
      return redirect($responseData['redirectUrl']);
    } else {
      return redirect()->route('shop.purchase_product.cancel');
    }
  }

  public function notify()
  {
    $arrData = Session::get('arrData');
    $productList = Session::get('productList');
    $id = Session::get('yoco_id');
    $s_key = Session::get('s_key');
    $paymentMethod = OnlineGateway::where('keyword', 'yoco')->first();
    $paydata = $paymentMethod->convertAutoData();
    if ($id && $paydata['secret_key'] == $s_key) {
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
