<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class FlutterwaveController extends Controller
{
  private $public_key, $secret_key;

  public function __construct()
  {
    $data = OnlineGateway::whereKeyword('flutterwave')->first();
    $flutterwaveData = json_decode($data->information, true);

    $this->public_key = $flutterwaveData['public_key'];
    $this->secret_key = $flutterwaveData['secret_key'];
  }

  public function index($arrData, $productList)
  {
    $title = 'Purchase Product';
    $notifyURL = route('shop.purchase_product.flutterwave.notify');

    $customerName = $arrData['billing_name'];
    $customerEmail = $arrData['billing_email'];
    $customerPhone = $arrData['billing_phone'];


    // send payment to flutterwave for processing
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode([
        'tx_ref' => 'FLW | ' . time(),
        'amount' => $arrData['grandTotal'],
        'currency' => $arrData['currencyText'],
        'redirect_url' => $notifyURL,
        'payment_options' => 'card,banktransfer',
        'customer' => [
          'email' => $customerEmail,
          'phone_number' => $customerPhone,
          'name' => $customerName
        ],
        'customizations' => [
          'title' => $title,
          'description' => $title . ' via Flutterwave.'
        ]
      ]),
      CURLOPT_HTTPHEADER => array(
        'authorization: Bearer ' . $this->secret_key,
        'content-type: application/json'
      )
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $responseData = json_decode($response, true);

    //curl end

    // put some data in session before redirect to flutterwave url
    session()->put('arrData', $arrData);

    // redirect to payment
    if ($responseData['status'] === 'success') {
      return redirect($responseData['data']['link']);
    } else {
      return redirect()->back()->with('error', 'Error: ' . $responseData['message'])->withInput();
    }
  }

  public function notify(Request $request)
  {
    // get the information from session
    $productList = session()->get('productCart');

    $arrData = session()->get('arrData');

    $urlInfo = $request->all();

    if ($urlInfo['status'] == 'successful') {
      $txId = $urlInfo['transaction_id'];

      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/{$txId}/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          'authorization: Bearer ' . $this->secret_key,
          'content-type: application/json'
        )
      ));

      $response = curl_exec($curl);

      curl_close($curl);

      $responseData = json_decode($response, true);
      if ($responseData['status'] === 'success') {
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
        session()->forget('arrData');

        // remove session data
        session()->forget('productCart');
        session()->forget('discount');

        return redirect()->route('shop.purchase_product.cancel');
      }
    } else {
      session()->forget('arrData');

      // remove session data
      session()->forget('productCart');
      session()->forget('discount');

      return redirect()->route('shop.purchase_product.cancel');
    }
  }
}
