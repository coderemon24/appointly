<?php

namespace App\Http\Controllers\FrontEnd\Shop;

use PDF;
use Illuminate\Http\Request;
use App\Rules\ImageMimeTypeRule;
use App\Http\Helpers\BasicMailer;
use App\Models\Shop\ProductOrder;
use App\Models\BasicSettings\Basic;
use App\Models\Shop\ShippingCharge;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Shop\ProductPurchaseItem;
use Illuminate\Support\Facades\Validator;
use App\Models\BasicSettings\MailTemplate;
use App\Models\PaymentGateway\OfflineGateway;
use App\Http\Requests\Shop\PurchaseProcessRequest;
use App\Http\Controllers\FrontEnd\MiscellaneousController;
use App\Http\Controllers\FrontEnd\PaymentGateway\YocoController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PaytmController;
use App\Http\Controllers\FrontEnd\PaymentGateway\IyzicoController;
use App\Http\Controllers\FrontEnd\PaymentGateway\MollieController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PayPalController;
use App\Http\Controllers\FrontEnd\PaymentGateway\StripeController;
use App\Http\Controllers\FrontEnd\PaymentGateway\XenditController;
use App\Http\Controllers\FrontEnd\PaymentGateway\OfflineController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PaytabsController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PhonePeController;
use App\Http\Controllers\FrontEnd\PaymentGateway\MidtransController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController;
use App\Http\Controllers\FrontEnd\PaymentGateway\RazorpayController;
use App\Http\Controllers\FrontEnd\PaymentGateway\InstamojoController;
use App\Http\Controllers\FrontEnd\PaymentGateway\ToyyibpayController;
use App\Http\Controllers\FrontEnd\PaymentGateway\MyFatoorahController;
use App\Http\Controllers\FrontEnd\PaymentGateway\FlutterwaveController;
use App\Http\Controllers\FrontEnd\PaymentGateway\MercadoPagoController;
use App\Http\Controllers\FrontEnd\PaymentGateway\AuthorizenetController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PerfectMoneyController;

class PurchaseProcessController extends Controller
{
  public function index(PurchaseProcessRequest $request)
  {
    // get the products from session
    if ($request->session()->has('productCart')) {
      $productList = $request->session()->get('productCart');
    } else {
      Session::flash('error', 'Something went wrong!');
      return redirect()->route('shop.products');
    }

    if (!$request->exists('gateway')) {
      Session::flash('error', 'Please select a payment method.');

      return redirect()->back()->withInput();
    }
    // do calculation
    $calculatedData = $this->calculation($request, $productList);
    if (!onlyDigitalItemsInCart()) {
      if (!$request->exists('shipping_method')) {
        Session::flash('error', 'Please select a shipping method.');
        return redirect()->back()->withInput();
      }
    }
    $currencyInfo = $this->getCurrencyInfo();
    $arrData = array(
      'billing_name' => $request['billing_name'],
      'billing_email' => $request['billing_email'],
      'billing_phone' => $request['billing_phone'],
      'billing_city' => $request['billing_city'],
      'billing_state' => $request['billing_state'],
      'billing_country' => $request['billing_country'],
      'billing_address' => $request['billing_address'],

      'shipping_name' => $request->checkbox == 1 ? $request['shipping_name'] : $request['billing_name'],
      'shipping_email' => $request->checkbox == 1 ? $request['shipping_email'] : $request['billing_email'],
      'shipping_phone' => $request->checkbox == 1 ? $request['shipping_phone'] : $request['billing_phone'],
      'shipping_city' => $request->checkbox == 1 ? $request['shipping_city'] : $request['billing_city'],
      'shipping_state' => $request->checkbox == 1 ? $request['shipping_state'] : $request['billing_state'],
      'shipping_country' => $request->checkbox == 1 ? $request['shipping_country'] : $request['billing_country'],
      'shipping_address' => $request->checkbox == 1 ? $request['shipping_address'] : $request['billing_address'],

      'total' => $calculatedData['total'],
      'discount' => $calculatedData['discount'],
      'productShippingChargeId' => $request->exists('shipping_method') ? $request['shipping_method'] : null,
      'shippingCharge' => $calculatedData['shippingCharge'],
      'tax' => $calculatedData['tax'],
      'grandTotal' => $calculatedData['grandTotal'],
      'currencyText' => $currencyInfo->base_currency_text,
      'currencyTextPosition' => $currencyInfo->base_currency_text_position,
      'currencySymbol' => $currencyInfo->base_currency_symbol,
      'currencySymbolPosition' => $currencyInfo->base_currency_symbol_position,
      'paymentMethod' => ucfirst($request['gateway']),
      'gatewayType' => 'online',
      'paymentStatus' => 'completed',
      'orderStatus' => 'pending'
    );

    switch ($request['gateway']) {
      case 'paypal':
        $controller = new PayPalController();
        break;
      case 'instamojo':
        $available_currency = array('INR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for instamojo payment');
          return redirect()->back()->withInput();
        }
        $controller = new InstamojoController();
        break;
      case 'paystack':
        $available_currency = array('NGN');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for paystack payment');
          return redirect()->back()->withInput();
        }
        $controller = new PaystackController();
        break;
      case 'flutterwave':
        $available_currency = array('BIF', 'CAD', 'CDF', 'CVE', 'EUR', 'GBP', 'GHS', 'GMD', 'GNF', 'KES', 'LRD', 'MWK', 'MZN', 'NGN', 'RWF', 'SLL', 'STD', 'TZS', 'UGX', 'USD', 'XAF', 'XOF', 'ZMK', 'ZMW', 'ZWD');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for flutterwave payment');
          return redirect()->back()->withInput();
        }
        $controller = new FlutterwaveController();
        break;
      case 'razorpay':
        $available_currency = array('INR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for razorpay payment');
          return redirect()->back()->withInput();
        }
        $controller = new RazorpayController();
        break;
      case 'mercadopago':
        $available_currency = array('ARS', 'BOB', 'BRL', 'CLF', 'CLP', 'COP', 'CRC', 'CUC', 'CUP', 'DOP', 'EUR', 'GTQ', 'HNL', 'MXN', 'NIO', 'PAB', 'PEN', 'PYG', 'USD', 'UYU', 'VEF', 'VES');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for mercadopago payment');
          return redirect()->back()->withInput();
        }
        $controller = new MercadoPagoController();
        break;
      case 'mollie':
        $available_currency = array('AED', 'AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HRK', 'HUF', 'ILS', 'ISK', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'USD', 'ZAR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for mollie payment');
          return redirect()->back()->withInput();
        }
        $controller = new MollieController();
        break;
      case 'stripe':
        $arrData['stripeToken'] = $request['stripeToken'];
        $controller = new StripeController();
        break;
      case 'paytm':
        $available_currency = array('INR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for paytm payment');
          return redirect()->back()->withInput();
        }
        $controller = new PaytmController();
        break;
      case 'authorize.net':
        $available_currency = array('USD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 'NOK', 'PLN', 'SEK', 'AUD', 'NZD');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for authorize payment');
          return redirect()->back()->withInput();
        }
        $arrData['opaqueDataValue'] = $request['opaqueDataValue'];
        $arrData['opaqueDataDescriptor'] = $request['opaqueDataDescriptor'];
        $controller = new AuthorizenetController();
        break;
      case 'myfatoorah':
        $available_currency = array('KWD', 'SAR', 'BHD', 'AED', 'QAR', 'OMR', 'JOD');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for myfatoorah payment');
          return redirect()->back()->withInput();
        }
        $controller = new MyFatoorahController();
        break;
      case 'phonepe':
        $available_currency = array('INR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for phonepe payment');
          return redirect()->back()->withInput();
        }
        $controller = new PhonePeController();
        break;
      case 'yoco':
        $available_currency = array('ZAR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for yoco payment');
          return redirect()->back()->withInput();
        }
        $controller = new YocoController();
        break;
      case 'xendit':
        $available_currency = array('IDR', 'PHP', 'USD', 'SGD', 'MYR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for xendit payment');
          return redirect()->back()->withInput();
        }
        $controller = new XenditController();
        break;
      case 'midtrans':
        $available_currency = array('IDR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for midtrans payment');
          return redirect()->back()->withInput();
        }
        $controller = new MidtransController();
        break;
      case 'toyyibpay':
        $available_currency = array('RM');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for toyyibpay payment');
          return redirect()->back()->withInput();
        }
        $controller = new ToyyibpayController();
        break;
      case 'paytabs':
        $paytabInfo = paytabInfo();
        if ($currencyInfo->base_currency_text != $paytabInfo['currency']) {
          session()->flash('error', 'Invalid currency for paytabs payment');
          return redirect()->back()->withInput();
        }
        $controller = new PaytabsController();
        break;
      case 'perfect_money':
        $available_currency = array('USD');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for perfect money payment');
          return redirect()->back()->withInput();
        }
        $controller = new PerfectMoneyController();
        break;
      case 'iyzico':
        $available_currency = array('TRY');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          session()->flash('error', 'Invalid currency for iyzico payment');
          return redirect()->back()->withInput();
        }
        $arrData['paymentStatus'] = 'pending';
        $arrData['zip_code'] = $request['zip_code'];
        $arrData['identity_number'] = $request['identity_number'];
        $controller = new IyzicoController();
        break;
      default:
        $offlineGateway = OfflineGateway::query()->findOrFail($request['gateway']);
        if ($offlineGateway->has_attachment == 1) {
          $rules = ['attachment' => ['required', new ImageMimeTypeRule()]];
          $message = [
            'attachment.required' => 'Please attach your payment receipt.'
          ];
          $validator = Validator::make($request->only('attachment'), $rules, $message);
          if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
          }
        }
        $arrData['offline_gateway'] = $request['gateway'];
        $arrData['attachment'] = $request['attachment'];
        $arrData['gatewayType'] = 'offline';
        $arrData['paymentStatus'] = 'pending';
        $arrData['paymentMethod'] = $offlineGateway->name;
        $controller = new OfflineController();
        break;
    }
    return $controller->index($arrData, $productList);
  }

  public function calculation(Request $request, $products)
  {
    $total = 0.00;

    foreach ($products as $key => $item) {
      $price = floatval($item['price']);
      $total += $price;
    }

    if ($request->session()->has('discount')) {
      $discountVal = $request->session()->get('discount');
    }

    $discount = isset($discountVal) ? floatval($discountVal) : 0.00;
    $subtotal = $total - $discount;
    $chargeId = $request->exists('shipping_method') ? $request['shipping_method'] : null;

    if (!is_null($chargeId)) {
      $shippingCharge = ShippingCharge::where('id', $request->shipping_method)->first();
      $shippingCharge = $shippingCharge->shipping_charge;
    } else {
      $shippingCharge = 0.00;
    }

    $taxData = Basic::select('product_tax_amount')->first();

    $taxAmount = floatval($taxData->product_tax_amount);
    $calculatedTax = $subtotal * ($taxAmount / 100);
    $grandTotal = $subtotal + floatval($shippingCharge) + $calculatedTax;

    $calculatedData = array(
      'total' => $total,
      'discount' => $discount,
      'subtotal' => $subtotal,
      'shippingCharge' => $request->exists('shipping_method') ? $shippingCharge : null,
      'tax' => $calculatedTax,
      'grandTotal' => $grandTotal
    );

    return $calculatedData;
  }

  public function storeData($productList, $arrData)
  {
    $orderInfo = ProductOrder::query()->create([
      'user_id' => Auth::guard('web')->check() == true ? Auth::guard('web')->user()->id : null,
      'order_number' => uniqid(),
      'billing_name' => $arrData['billing_name'],
      'billing_phone' => $arrData['billing_phone'],
      'billing_email' => $arrData['billing_email'],
      'billing_address' => $arrData['billing_address'],
      'billing_city' => $arrData['billing_city'],
      'billing_state' => $arrData['billing_state'],
      'billing_country' => $arrData['billing_country'],
      'shipping_name' => $arrData['shipping_name'],
      'shipping_email' => $arrData['shipping_email'],
      'shipping_phone' => $arrData['shipping_phone'],
      'shipping_address' => $arrData['shipping_address'],
      'shipping_city' => $arrData['shipping_city'],
      'shipping_state' => $arrData['shipping_state'],
      'shipping_country' => $arrData['shipping_country'],

      'total' => $arrData['total'],
      'discount' => $arrData['discount'],
      'product_shipping_charge_id' => $arrData['productShippingChargeId'],
      'shipping_cost' => $arrData['shippingCharge'],
      'tax' => $arrData['tax'],
      'grand_total' => $arrData['grandTotal'],
      'currency_text' => $arrData['currencyText'],
      'currency_text_position' => $arrData['currencyTextPosition'],
      'currency_symbol' => $arrData['currencySymbol'],
      'currency_symbol_position' => $arrData['currencySymbolPosition'],
      'payment_method' => $arrData['paymentMethod'],
      'gateway_type' => $arrData['gatewayType'],
      'payment_status' => $arrData['paymentStatus'],
      'order_status' => $arrData['orderStatus'],
      'attachment' => array_key_exists('attachment', $arrData) ? $arrData['attachment'] : null,
      'conversation_id' => @$arrData['conversation_id']
    ]);

    foreach ($productList as $key => $item) {
      ProductPurchaseItem::create([
        'product_order_id' => $orderInfo->id,
        'product_id' => $key,
        'title' => $item['title'],
        'quantity' => intval($item['quantity'])
      ]);
    }

    return $orderInfo;
  }

  public function generateInvoice($orderInfo, $productList)
  {
    $fileName = $orderInfo->order_number . '.pdf';

    $data['orderInfo'] = $orderInfo;
    $data['productList'] = $productList;

    $directory = public_path('assets/file/invoices/product/');
    @mkdir($directory, 0775, true);

    $fileLocated = $directory . $fileName;

    $data['taxData'] = Basic::select('product_tax_amount')->first();

    PDF::loadView('frontend.shop.invoice', $data)->save($fileLocated);

    return $fileName;
  }

  public function prepareMail($orderInfo)
  {
    // get the mail template info from db
    $mailTemplate = MailTemplate::query()->where('mail_type', '=', 'product_order')->first();
    $mailData['subject'] = $mailTemplate->mail_subject;
    $mailBody = $mailTemplate->mail_body;

    // get the website title info from db
    $info = Basic::select('website_title')->first();

    $customerName = $orderInfo->billing_first_name . ' ' . $orderInfo->billing_last_name;
    $orderNumber = $orderInfo->order_number;
    $websiteTitle = $info->website_title;

    if (Auth::guard('web')->check() == true) {
      $orderLink = '<p>Order Details: <a href=' . url("user/order/details/" . $orderInfo->id) . '>Click Here</a></p>';
    } else {
      $orderLink = '';
    }

    // replacing with actual data
    $mailBody = str_replace('{customer_name}', $customerName, $mailBody);
    $mailBody = str_replace('{order_number}', $orderNumber, $mailBody);
    $mailBody = str_replace('{website_title}', $websiteTitle, $mailBody);
    $mailBody = str_replace('{order_link}', $orderLink, $mailBody);

    $mailData['body'] = $mailBody;

    $mailData['recipient'] = $orderInfo->billing_email;

    $mailData['invoice'] = public_path('assets/file/invoices/product/') . $orderInfo->invoice;

    BasicMailer::sendMail($mailData);

    return;
  }

  public function complete($type = null)
  {
    $misc = new MiscellaneousController();

    $queryResult['bgImg'] = $misc->getBreadcrumb();

    $queryResult['purchaseType'] = $type;

    return view('frontend.payment.purchase-success', $queryResult);
  }

  public function cancel(Request $request)
  {
    $notification = array('message' => 'Something went wrong', 'alert-type' => 'error');
    return redirect()->route('shop.products')->with($notification);
  }
}
