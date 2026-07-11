<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use Illuminate\Http\Request;
use Mollie\Laravel\Facades\Mollie;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class MollieController extends Controller
{
  public function index($arrData, $paymentFor)
  {
    $title = 'Purchase Product';
    $notifyURL = route('shop.purchase_product.mollie.notify');

    /**
     * we must send the correct number of decimals.
     * thus, we have used sprintf() function for format.
     */
    $payment = Mollie::api()->payments->create([
      'amount' => [
        'currency' => $arrData['currencyText'],
        'value' => sprintf('%0.2f', $arrData['grandTotal'])
      ],
      'description' => $title . ' via Mollie',
      'redirectUrl' => $notifyURL
    ]);
    // put some data in session before redirect to mollie url
    session()->put('paymentFor', $paymentFor);
    session()->put('arrData', $arrData);
    session()->put('payment', $payment);

    return redirect($payment->getCheckoutUrl(), 303);
  }

  public function notify(Request $request)
  {
    $productList = session()->get('productCart');

    // get the information from session
    $arrData = session()->get('arrData');
    $payment = session()->get('payment');

    $paymentInfo = Mollie::api()->payments->get($payment->id);

    if ($paymentInfo->isPaid() == true) {
      // remove this session datas
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('payment');

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
      session()->forget('paymentFor');
      session()->forget('arrData');
      session()->forget('payment');

      // remove session data
      session()->forget('productCart');
      session()->forget('discount');

      return redirect()->route('shop.purchase_product.cancel');
    }
  }
}
