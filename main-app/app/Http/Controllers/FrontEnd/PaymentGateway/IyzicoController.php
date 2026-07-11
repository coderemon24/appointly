<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Models\Shop\Product;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\Shop\PurchaseProcessController;

class IyzicoController extends Controller
{
  public function index($arrData, $productList)
  {
    $_success_url = route('shop.purchase_product.iyzico.notify');
    //get vendor details for iyzico payment configuration
    $name = $arrData['billing_name'];
    $email = $arrData['billing_email'];
    $address = $arrData['billing_address'];
    $city = $arrData['billing_city'];
    $country = $arrData['billing_country'];
    $phone = $arrData['billing_phone'];
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
    $iyzipay_request->setPrice($arrData['grandTotal']);
    $iyzipay_request->setPaidPrice($arrData['grandTotal']);
    $iyzipay_request->setCurrency(\Iyzipay\Model\Currency::TL);
    $iyzipay_request->setBasketId($basket_id);
    $iyzipay_request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
    $iyzipay_request->setCallbackUrl($_success_url);
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
    $firstBasketItem->setPrice($arrData['grandTotal']);
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
          session()->put('productList', $productList);
          return redirect($paymentInfo['payWithIyzicoPageUrl']);
        }
      }
      return redirect()->route('shop.purchase_product.cancel');
    }
  }

  public function notify()
  {
    $arrData = session()->get('arrData');
    $conversation_id = session()->get('conversation_id');
    $productList = session()->get('productList');
    $purchaseProcess = new PurchaseProcessController();
    $arrData['conversation_id'] = $conversation_id;
    // store product order information in database
    $purchaseProcess->storeData($productList, $arrData);

    // then subtract each product quantity from respective product stock
    foreach ($productList as $key => $item) {
      $product = Product::query()->find($key);

      if ($product->product_type == 'physical') {
        $stock = $product->stock - intval($item['quantity']);

        $product->update(['stock' => $stock]);
      }
    }
    // remove all session data
    session()->forget('productCart');
    session()->forget('discount');
    // remove this session datas
    session()->forget('paymentFor');
    session()->forget('arrData');
    session()->forget('paymentId');
    session()->forget('productList');
    return redirect()->route('shop.purchase_product.complete');
  }
}
