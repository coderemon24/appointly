<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class XenditController extends Controller
{
  public function index($arrData, $productList)
  {
    $paymentMethod = OnlineGateway::where('keyword', 'xendit')->first();
    $paydata = json_decode($paymentMethod->information, true);

    $external_id = \Str::random(10);
    $secret_key = 'Basic ' . base64_encode($paydata['secret_key'] . ':');
    $data_request = Http::withHeaders([
      'Authorization' => $secret_key
    ])->post('https://api.xendit.co/v2/invoices', [
      'external_id' => $external_id,
      'amount' => $arrData['grandTotal'],
      'currency' => $arrData['currencyText'],
      'success_redirect_url' => route('shop.purchase_product.xendit.notify')
    ]);
    $response = $data_request->json();
    if (isset($response['error_code']) && isset($response['message'])) {
      session()->flash('warning', $response['message']);
      return redirect()->back();
    }

    if (!empty($response['invoice_url'])) {
      session()->put('cancel_url', route('shop.purchase_product.cancel'));
      session()->put("arrData", $arrData);
      session()->put('xendit_id', $response['id']);
      session()->put('secret_key', $secret_key);
      session()->put('productList', $productList);
      return redirect($response['invoice_url']);
    } else {
      return redirect()->route('shop.purchase_product.cancel');
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $xendit_id = session()->get('xendit_id');
    $productList = session()->get('productList');
    $secret_key = session()->get('secret_key');
    $paymentMethod = OnlineGateway::where('keyword', 'xendit')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $p_secret_key = 'Basic ' . base64_encode($paydata['secret_key'] . ':');
    if (!is_null($xendit_id) && $secret_key == $p_secret_key) {
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
