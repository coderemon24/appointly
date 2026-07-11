<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use Illuminate\Http\Request;
use Basel\MyFatoorah\MyFatoorah;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

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
      'myfatoorah.CallBackUrl' => route('shop.purchase_product.myfatoorah.notify'),
      'myfatoorah.ErrorUrl' => route('shop.purchase_product.cancel'),
    ]);

    $sandboxMode = isset($information['sandbox_status']) && $information['sandbox_status'] == 1;

    $this->myfatoorah = MyFatoorah::getInstance($sandboxMode);
  }

  public function index($arrData, $productList)
  {
    $info = OnlineGateway::where('keyword', 'myfatoorah')->first();
    $information = json_decode($info->information, true);
    $paymentFor = Session::get('paymentFor');

    $random_1 = rand(999, 9999);
    $random_2 = rand(9999, 99999);

    $result = $this->myfatoorah->sendPayment(
      $arrData['billing_name'],
      intval($arrData['grandTotal']),
      [
        'CustomerMobile' => $information['sandbox_status'] == 1 ? '56562123544' : $arrData['billing_phone'],
        'CustomerReference' => "$random_1",  //orderID
        'UserDefinedField' => "$random_2", //clientID
        "InvoiceItems" => [
          [
            "ItemName" => "Package Purchase or Extends",
            "Quantity" => 1,
            "UnitPrice" => intval($arrData['grandTotal'])
          ]
        ]
      ]
    );

    if ($result && $result['IsSuccess'] == true) {
      Session::put('myfatoorah_payment_type', $paymentFor);
      Session::put("arrData", $arrData);
      Session::put("productList", $productList);
      return redirect($result['Data']['InvoiceURL']);
    } else {

      return redirect()->route('shop.purchase_product.cancel');
    }
  }

  public function notify(Request $request)
  {
    // get the information from session
    $arrData = session()->get('arrData');
    $productList = session()->get('productList');
    if (!empty($request->paymentId)) {
      $result = $this->myfatoorah->getPaymentStatus('paymentId', $request->paymentId);
      if ($result && $result['IsSuccess'] == true && $result['Data']['InvoiceStatus'] == "Paid") {

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
