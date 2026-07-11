<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class ToyyibpayController extends Controller
{
  public function index($arrData, $productList)
  {
    $info = OnlineGateway::where('keyword', 'toyyibpay')->first();
    $paydata = json_decode($info->information, true);
    $ref = uniqid();
    session()->put('toyyibpay_ref_id', $ref);
    session()->put('arrData', $arrData);
    session()->put('productList', $productList);
    $bill_description = 'Package Purchase via toyyibpay';

    $name = $arrData['billing_name'];
    $email =  $arrData['billing_email'];
    $phone =  $arrData['billing_phone'];

    $some_data = array(
      'userSecretKey' => $paydata['secret_key'],
      'categoryCode' => $paydata['category_code'],
      'billName' => 'Package Purchase',
      'billDescription' => $bill_description,
      'billPriceSetting' => 1,
      'billPayorInfo' => 1,
      'billAmount' => $arrData['grandTotal'] * 100,
      'billReturnUrl' => route('shop.purchase_product.toyyibpay.notify'),
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
      return redirect()->to($host . $response[0]["BillCode"]);
    } else {
      return redirect()->route('shop.purchase_product.cancel');
    }
  }

  public function notify(Request $request)
  {
    $arrData = session()->get('arrData');
    $productList = session()->get('productList');
    $ref = session()->get('toyyibpay_ref_id');
    if ($request['status_id'] == 1 && $request['order_id'] == $ref) {
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
