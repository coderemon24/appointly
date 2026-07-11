<?php

namespace App\Http\Controllers\Vendor\ServicePromotion;

use PDF;
use Auth;
use Response;
use Validator;
use App\Models\Language;
use App\Models\VendorInfo;
use Illuminate\Http\Request;
use App\Rules\ImageMimeTypeRule;
use App\Http\Helpers\BasicMailer;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceContent;
use App\Models\BasicSettings\MailTemplate;
use App\Models\PaymentGateway\OfflineGateway;
use App\Models\FeaturedService\ServicePromotion;
use App\Models\FeaturedService\FeaturedServiceCharge;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\YocoController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\PaytmController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\IyzicoController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\MollieController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\PayPalController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\StripeController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\XenditController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\OfflineController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\PaytabsController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\PhonePeController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\MidtransController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\PaystackController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\RazorpayController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\InstamojoController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\ToyyibpayController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\MyFatoorahController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\FlutterwaveController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\MercadoPagoController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\AuthorizenetController;
use App\Http\Controllers\Vendor\ServicePromotion\Payment\PerfectMoneyController;

class ServicePromotionController extends Controller
{
  public function index(Request $request)
  {
    $rules = [
      'promotion_id' => 'required',
      'gateway' => 'required',
    ];

    if ($request->gateway === 'iyzico') {
      $rules = [
        'identity_number' => 'required',
        'zip_code'        => 'required',
        'address'         => 'required|string|max:255',
        'city'            => 'required',
        'country'         => 'required',
        'phone'           => 'required',
      ];
    }
    if ($request->gateway === 'stripe') {
      $rules = [
        'stripeToken' => 'required',
      ];
    }
    $messages = [
      'promotion_id.required' => 'Please select a promotion.',
      'gateway.required'      => 'Please select a payment gateway.',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);
    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $currencyInfo = $this->getCurrencyInfo();
    // amount calculation
    $chargeId = FeaturedServiceCharge::find($request->promotion_id);
    $amount = $chargeId->amount;
    $day = intval($chargeId->day);
    $success_url = route('featured.service.online.success.page');


    $arrData = array(
      'amount' => $amount,
      'day' => $day,
      'service_id' => $request['service_id'],
      'language_id' => $request['language_id'],
      'vendor_id' => $request['vendor_id'],
      'invoice' => $request['invoice'],
      'currencyText' => $currencyInfo->base_currency_text,
      'currencyTextPosition' => $currencyInfo->base_currency_text_position,
      'currencySymbol' => $currencyInfo->base_currency_symbol,
      'currencySymbolPosition' => $currencyInfo->base_currency_symbol_position,
      'paymentMethod' => ucfirst($request['gateway']),
      'gatewayType' => 'online',
      'paymentStatus' => 'completed',
      'orderStatus' => 'pending',
    );

    switch ($request['gateway']) {
      case 'paypal':
        $controller = new PayPalController();
        break;
      case 'instamojo':
        $available_currency = array('INR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for instamojo payment')], 422);
        }
        $controller = new InstamojoController();
        break;
      case 'paystack':
        $available_currency = array('NGN');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for paystack payment')], 422);
        }
        $controller = new PaystackController();
        break;
      case 'flutterwave':
        $available_currency = array('BIF', 'CAD', 'CDF', 'CVE', 'EUR', 'GBP', 'GHS', 'GMD', 'GNF', 'KES', 'LRD', 'MWK', 'MZN', 'NGN', 'RWF', 'SLL', 'STD', 'TZS', 'UGX', 'USD', 'XAF', 'XOF', 'ZMK', 'ZMW', 'ZWD');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for mollie payment')], 422);
        }
        $controller = new FlutterwaveController();
        break;
      case 'razorpay':
        $available_currency = array('INR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for instamojo payment')], 422);
        }
        $controller = new RazorpayController();
        break;
      case 'mercadopago':
        $available_currency = array('ARS', 'BOB', 'BRL', 'CLF', 'CLP', 'COP', 'CRC', 'CUC', 'CUP', 'DOP', 'EUR', 'GTQ', 'HNL', 'MXN', 'NIO', 'PAB', 'PEN', 'PYG', 'USD', 'UYU', 'VEF', 'VES');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for mercadopago payment')], 422);
        }
        $controller = new MercadoPagoController();
        break;
      case 'mollie':
        $available_currency = array('AED', 'AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HRK', 'HUF', 'ILS', 'ISK', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'USD', 'ZAR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for mollie payment')], 422);
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
          return Response::json(['error' => __('Invalid currency for instamojo payment')], 422);
        }
        $controller = new PaytmController();
        break;
      case 'authorize.net':
        $available_currency = array('USD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 'NOK', 'PLN', 'SEK', 'AUD', 'NZD');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for myfatoorah payment')], 422);
        }
        $arrData['opaqueDataValue'] = $request['opaqueDataValue'];
        $arrData['opaqueDataDescriptor'] = $request['opaqueDataDescriptor'];
        $controller = new AuthorizenetController();
        break;
      case 'myfatoorah':
        $available_currency = array('KWD', 'SAR', 'BHD', 'AED', 'QAR', 'OMR', 'JOD');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for myfatoorah payment')], 422);
        }
        $controller = new MyFatoorahController();
        break;
      case 'phonepe':
        $available_currency = array('INR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for phonepe payment')], 422);
        }
        $controller = new PhonePeController();
        break;
      case 'yoco':
        $available_currency = array('ZAR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for yoco payment')], 422);
        }
        $controller = new YocoController();
        break;
      case 'xendit':
        $available_currency = array('IDR', 'PHP', 'USD', 'SGD', 'MYR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for xendit payment')], 422);
        }
        $controller = new XenditController();
        break;
      case 'midtrans':
        $available_currency = array('IDR');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for midtrans payment')], 422);
        }
        $controller = new MidtransController();
        break;
      case 'toyyibpay':
        $available_currency = array('RM');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for toyyibpay payment')], 422);
        }
        $controller = new ToyyibpayController();
        break;
      case 'paytabs':
        $paytabInfo = paytabInfo();
        if ($currencyInfo->base_currency_text != $paytabInfo['currency']) {
          return Response::json(['error' => __('Invalid currency for paytabs payment')], 422);
        }
        $controller = new PaytabsController();
        break;
      case 'perfect_money':
        $available_currency = array('USD');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for perfect money payment')], 422);
        }
        $controller = new PerfectMoneyController();
        break;
      case 'iyzico':
        $available_currency = array('TRY');
        if (!in_array($currencyInfo->base_currency_text, $available_currency)) {
          return Response::json(['error' => __('Invalid currency for iyzico payment')], 422);
        }
        $arrData['name'] = Auth::guard('vendor')->user()->username;
        $arrData['email'] = Auth::guard('vendor')->user()->email;
        $arrData['address'] = $request->address;
        $arrData['city'] = $request->city;
        $arrData['country'] = $request->country;
        $arrData['phone'] = $request->phone;
        $arrData['identity_number'] = $request->identity_number;
        $arrData['zip_code'] = $request->zip_code;
        $arrData['paymentStatus'] = 'pending';
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
            return Response::json(['errors' => $validator->errors()], 422);
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
    return $controller->index($arrData, 'Service Featured', $success_url, $amount);
  }

  public function storeData($arrData)
  {
    $orderInfo = ServicePromotion::create([
      'order_number' => uniqid(),
      'amount' => $arrData['amount'],
      'day' => $arrData['day'],
      'service_id' => $arrData['service_id'],
      'vendor_id' => $arrData['vendor_id'],
      'invoice' => $arrData['invoice'],
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

    return $orderInfo;
  }

  public function generateInvoice($orderInfo)
  {
    $fileName = $orderInfo->order_number . '.pdf';
    $data['orderInfo'] = $orderInfo;

    $directory = public_path('assets/file/invoices/featured/service/');
    @mkdir($directory, 0775, true);

    $fileLocated = $directory . $fileName;
    PDF::loadView('frontend.services.featured-service.invoice', $data)->save($fileLocated);
    return $fileName;
  }

  public function prepareMail($featuredInfo, $languageId)
  {
    // get the mail template info from db
    $mailTemplate = MailTemplate::query()->where('mail_type', '=', 'featured_request_send')->first();
    $mailData['subject'] = $mailTemplate->mail_subject;
    $mailBody = $mailTemplate->mail_body;

    // get the website title info from db
    $info = Basic::select('website_title')->first();

    //service info
    $service = ServiceContent::where('service_id', $featuredInfo->service_id)
      ->where('language_id', $languageId)
      ->select('name', 'slug')
      ->first();
    $url = route('frontend.service.details', ['slug' => $service->slug, 'id' => $featuredInfo->service_id]);
    $serviceName = truncateString($service->name, 50);

    //vendor info
    $vendorName = VendorInfo::where('vendor_id', $featuredInfo->vendor_id)
      ->where('language_id', $languageId)
      ->first()->name;

    // replacing with actual data
    $mailBody = str_replace('{service_title}', "<a href=" . $url . ">$serviceName</a>", $mailBody);
    $mailBody = str_replace('{amount}', symbolPrice($featuredInfo->amount), $mailBody);
    $mailBody = str_replace('{username}', $vendorName, $mailBody);
    $mailBody = str_replace('{website_title}', $info->website_title, $mailBody);

    $mailData['body'] = $mailBody;
    $mailData['recipient'] = $featuredInfo->vendor->email;
    $mailData['invoice'] = public_path('assets/file/invoices/featured/service/') . $featuredInfo->invoice;
    BasicMailer::sendMail($mailData);
    return;
  }

  public function cancel(Request $request)
  {
    $language = Language::where('is_default', 1)->first();
    session()->flash('warning', 'Something went wrong !');
    return redirect()->route('vendor.service_managment', ['language' => $language->code]);
  }
}
