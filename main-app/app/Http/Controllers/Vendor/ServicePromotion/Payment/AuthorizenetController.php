<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Auth;
use Omnipay\Omnipay;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

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


  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    if ($arrData['opaqueDataValue'] && $arrData['opaqueDataDescriptor']) {
      // generate a unique merchant site transaction ID
      $transactionId = rand(100000000, 999999999);

      $response = $this->gateway->authorize([
        'amount' => sprintf('%0.2f', $amount),
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
        $servicePromotion = new ServicePromotionController();
        // store product order information in database
        $featuredInfo = $servicePromotion->storeData($arrData);

        //transaction create
        $after_balance = NULL;
        $pre_balance = NULL;
        $transactionData = [
          'vendor_id' => Auth::guard('vendor')->user()->id,
          'transaction_type' => 'featured_service',
          'pre_balance' => $pre_balance,
          'actual_total' => $arrData['amount'],
          'after_balance' => $after_balance,
          'admin_profit' => $arrData['amount'],
          'payment_method' => $arrData['paymentMethod'],
          'currency_symbol' => $arrData['currencySymbol'],
          'currency_symbol_position' => $arrData['currencySymbolPosition'],
          'payment_status' => $arrData['paymentStatus'],
        ];
        store_transaction($transactionData);

        // generate an invoice in pdf format
        $invoice = $servicePromotion->generateInvoice($featuredInfo);

        // then, update the invoice field info in database
        $featuredInfo->update(['invoice' => $invoice]);

        // send a mail to the customer with the invoice
        $servicePromotion->prepareMail($featuredInfo, $arrData['language_id']);

        return redirect()->route('featured.service.online.success.page');
      } else {
        return redirect()->route('vendor.featured.cancel');
      }
    } else {
      //return cancel url
      return redirect()->route('vendor.featured.cancel');
    }
    return redirect()->route('vendor.featured.cancel');
  }
}
