<?php

namespace App\Http\Controllers\Vendor\ServicePromotion\Payment;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\ServicePromotion\ServicePromotionController;

class IyzicoController extends Controller
{
  public function index($arrData, $paymentFor, $success_url, $amount)
  {
    $callBackUrl = route('vendor.featured.iyzico.notify');
    //get vendor details for iyzico payment configuration
    $name = $arrData['name'];
    $email = $arrData['email'];
    $address = $arrData['address'];
    $city = $arrData['country'];
    $country = $arrData['country'];
    $phone = $arrData['phone'];
    $identity_number = $arrData['identity_number'];
    $zip_code = $arrData['zip_code'];

    $paymentMethod = OnlineGateway::where('keyword', 'iyzico')->first();
    $paydata = json_decode($paymentMethod->information, true);

    $options = new \Iyzipay\Options();
    $options->setApiKey($paydata['api_key']);
    $options->setSecretKey($paydata['secret_key']);
    $basket_id = 'B' . uniqid(999, 99999);

    $paydata['sandbox_status'] == 1 ? $options->setBaseUrl("https://sandbox-api.iyzipay.com") : $options->setBaseUrl("https://api.iyzipay.com");

    $conversion_id = uniqid(9999, 999999);
    # create request class
    $iyzipay_request = new \Iyzipay\Request\CreatePayWithIyzicoInitializeRequest();
    $iyzipay_request->setLocale(\Iyzipay\Model\Locale::EN);
    $iyzipay_request->setConversationId($conversion_id);
    $iyzipay_request->setPrice($amount);
    $iyzipay_request->setPaidPrice($amount);
    $iyzipay_request->setCurrency(\Iyzipay\Model\Currency::TL);
    $iyzipay_request->setBasketId($basket_id);
    $iyzipay_request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
    $iyzipay_request->setCallbackUrl($callBackUrl);
    $iyzipay_request->setEnabledInstallments(array(2, 3, 6, 9));


    $buyer = new \Iyzipay\Model\Buyer();
    $buyer->setId(uniqid());
    $buyer->setName($name);
    $buyer->setSurname($name);
    $buyer->setGsmNumber($phone);
    $buyer->setEmail($email);
    $buyer->setIdentityNumber($identity_number);
    $buyer->setLastLoginDate("");
    $buyer->setRegistrationDate("");
    $buyer->setRegistrationAddress($address);
    $buyer->setIp("");
    $buyer->setCity($city);
    $buyer->setCountry($country);
    $buyer->setZipCode($zip_code);
    $iyzipay_request->setBuyer($buyer);

    $shippingAddress = new \Iyzipay\Model\Address();
    $shippingAddress->setContactName($name);
    $shippingAddress->setCity($city);
    $shippingAddress->setCountry($country);
    $shippingAddress->setAddress($address);
    $shippingAddress->setZipCode($zip_code);
    $iyzipay_request->setShippingAddress($shippingAddress);

    $billingAddress = new \Iyzipay\Model\Address();
    $billingAddress->setContactName($name);
    $billingAddress->setCity($city);
    $billingAddress->setCountry($country);
    $billingAddress->setAddress($address);
    $billingAddress->setZipCode($zip_code);
    $iyzipay_request->setBillingAddress($billingAddress);

    $q_id = uniqid(999, 99999);
    $basketItems = array();
    $firstBasketItem = new \Iyzipay\Model\BasketItem();
    $firstBasketItem->setId($q_id);
    $firstBasketItem->setName("Purchase Id " . $q_id);
    $firstBasketItem->setCategory1("Purchase or Booking");
    $firstBasketItem->setCategory2("");
    $firstBasketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
    $firstBasketItem->setPrice($amount);
    $basketItems[0] = $firstBasketItem;

    $iyzipay_request->setBasketItems($basketItems);
    # make request
    $payWithIyzicoInitialize = \Iyzipay\Model\PayWithIyzicoInitialize::create($iyzipay_request, $options);
    $paymentResponse = (array)$payWithIyzicoInitialize;
    foreach ($paymentResponse as $key => $data) {
      $paymentInfo = json_decode($data, true);
      if ($paymentInfo['status'] == 'success') {
        if (!empty($paymentInfo['payWithIyzicoPageUrl'])) {
          session()->put('conversation_id', $conversion_id);
          session()->put('arrData', $arrData);
          session()->put('language_id', $arrData['language_id']);
          session()->put('success_url', $success_url);
          return response()->json(['redirectURL' => $paymentInfo['payWithIyzicoPageUrl']]);
        }
      }
      return redirect()->route('vendor.featured.cancel');
    }
  }

  public function notify()
  {
    $arrData = session()->get('arrData');
    $conversation_id = session()->get('conversation_id');
    $languageId = session()->get('language_id');
    $success_url = session()->get('success_url');
    $arrData['conversation_id'] = $conversation_id;

    $servicePromotion = new ServicePromotionController();
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

    $invoice = $servicePromotion->generateInvoice($featuredInfo);
    $featuredInfo->update(['invoice' => $invoice]);
    $servicePromotion->prepareMail($featuredInfo, $languageId);

    // remove this session datas
    session()->forget('paymentFor');
    session()->forget('arrData');
    session()->forget('paymentId');
    session()->forget('language_id');
    return redirect($success_url);
  }
}
