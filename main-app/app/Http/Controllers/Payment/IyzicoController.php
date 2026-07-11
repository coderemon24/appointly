<?php

namespace App\Http\Controllers\Payment;

use App\Models\Vendor;
use Illuminate\Http\Request;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\VendorCheckoutController;

class IyzicoController extends Controller
{
  public function paymentProcess(Request $request, $_amount, $_success_url, $_cancel_url, $_title, $bex)
  {
    //get vendor details for iyzico payment configuration
    $name = Vendor::where('id', $request->vendor_id)->value('username');
    $email = $request->email;
    $address = $request->address;
    $city = $request->city;
    $country = $request->country;
    $phone = $request->phone;
    $identity_number = $request->identity_number;
    $zip_code = $request->zip_code;

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
    $iyzipay_request->setPrice($_amount);
    $iyzipay_request->setPaidPrice($_amount);
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
    $firstBasketItem->setPrice($_amount);
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
          session()->put('request', $request->all());
          return redirect($paymentInfo['payWithIyzicoPageUrl']);
        }
      }
      return redirect($_cancel_url);
    }
  }

  public function successPayment()
  {
    $paymentFor = session()->get('paymentFor');
    $requestData = session()->get('request');
    $requestData['conversation_id'] = session()->get('conversation_id');
    $requestData['status'] = 0;
    $bs = Basic::select('base_currency_text', 'base_currency_rate')->first();

    $transaction_id = VendorPermissionHelper::uniqidReal(8);
    $transaction_details = "online";

    $amount = $requestData['price'];
    $password = $paymentFor == 'membership'
      ? ($requestData['password'] ?? null)
      : uniqid('qrcode');
    $checkout = new VendorCheckoutController();
    $checkout->store($requestData, $transaction_id, $transaction_details, $amount, $bs, $password);
    session()->forget(['request', 'paymentFor']);
    return redirect()->route('success.page');
  }


  public function cancelPayment()
  {
    $requestData = session()->get('request');
    $paymentFor = session()->get('paymentFor');
    session()->flash('warning', __('cancel_payment'));
    if ($paymentFor == "membership") {
      return redirect()->route('front.register.view', ['status' => $requestData['package_type'], 'id' => $requestData['package_id']])->withInput($requestData);
    } else {
      return redirect()->route('vendor.plan.extend.checkout', ['package_id' => $requestData['package_id']])->withInput($requestData);
    }
  }
}
