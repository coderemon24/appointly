<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use Omnipay\Omnipay;
use App\Models\Shop\Product;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class AuthorizenetController extends Controller
{
  private $gateway;

  public function __construct()
  {
    $data = OnlineGateway::query()->whereKeyword('authorize.net')->first();
    $authorizeNetData = json_decode($data->information, true);
    $this->gateway = Omnipay::create('AuthorizeNetApi_Api');
    $this->gateway->setAuthName($authorizeNetData['login_id']);
    $this->gateway->setTransactionKey($authorizeNetData['transaction_key']);
    if ($authorizeNetData['sandbox_check'] == 1) {
      $this->gateway->setTestMode(true);
    }
  }
  public function index($arrData, $productList)
  {

    $purchaseProcess = new PurchaseProcessController();

    // put some data in session before redirect to paytm url
    session()->put('arrData', $arrData);

    if ($arrData['opaqueDataValue'] && $arrData['opaqueDataDescriptor']) {
      // generate a unique merchant site transaction ID
      $transactionId = rand(100000000, 999999999);

      $response = $this->gateway->authorize([
        'amount' => sprintf('%0.2f', $arrData['grandTotal']),
        'currency' => $arrData['currencyText'],
        'transactionId' => $transactionId,
        'opaqueDataDescriptor' => $arrData['opaqueDataDescriptor'],
        'opaqueDataValue' => $arrData['opaqueDataValue']
      ])->send();

      if ($response->isSuccessful()) {
        /**
         * success process will be go here
         * remove this session datas
         */
        session()->forget('paymentFor');
        session()->forget('arrData');
        session()->forget('paymentId');
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
        return redirect()->route('shop.purchase_product.complete');
      } else {
        //cancel payment
        session()->forget('paymentFor');
        session()->forget('arrData');
        session()->forget('paymentId');
        // remove session data
        session()->forget('productCart');
        session()->forget('discount');
        return redirect()->route('shop.purchase_product.cancel');
      }
    } else {
      //return cancel url
      return redirect()->route('shop.products');
    }
    return redirect()->route('shop.products');
  }
}
