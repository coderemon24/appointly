<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class PaytabsController extends Controller
{
  public function index($arrData, $productList)
  {
    Session::put('arrData', $arrData);
    Session::put('productList', $productList);

    $paytabInfo = paytabInfo();
    $description = 'Product Purchase via paytabs';

    try {
      $response = Http::withHeaders([
        'Authorization' => $paytabInfo['server_key'], // Server Key
        'Content-Type' => 'application/json',
      ])->post($paytabInfo['url'], [
        'profile_id' => $paytabInfo['profile_id'], // Profile ID
        'tran_type' => 'sale',
        'tran_class' => 'ecom',
        'cart_id' => uniqid(),
        'cart_description' => $description,
        'cart_currency' => $paytabInfo['currency'], // set currency by region
        'cart_amount' => round($arrData['grandTotal'], 2),
        'return' => route('shop.purchase_product.paytabs.notify'),
      ]);

      $responseData = $response->json();
      return redirect($responseData['redirect_url']);
    } catch (\Exception $e) {
      return redirect()->route('shop.purchase_product.cancel');
    }
  }
  public function notify(Request $request)
  {
    $arrData = Session::get('arrData');
    $productList = Session::get('productList');
    $resp = $request->all();
    if ($resp['respStatus'] == "A" && $resp['respMessage'] == 'Authorised') {
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
