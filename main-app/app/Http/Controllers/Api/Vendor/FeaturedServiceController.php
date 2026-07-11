<?php

namespace App\Http\Controllers\Api\Vendor;

use PDF;
use App\Models\Language;
use App\Models\VendorInfo;
use Illuminate\Http\Request;
use App\Http\Helpers\BasicMailer;
use Illuminate\Support\Facades\DB;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Services\ServiceContent;
use Illuminate\Support\Facades\Validator;
use App\Models\BasicSettings\MailTemplate;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\FeaturedService\ServicePromotion;
use App\Models\FeaturedService\FeaturedServiceCharge;

class FeaturedServiceController extends Controller
{

  public function index()
  {
    $data['promotionLists'] = FeaturedServiceCharge::all();

    $data['online_gateways'] = DB::table('online_gateways')
      ->where('mobile_status', 1)
      ->whereIn('keyword', [
        'phonepe',
        'mercadopago',
        'myfatoorah',
        'midtrans',
        'authorize.net',
        'toyyibpay',
        'xendit',
        'mollie',
        'paystack',
        'flutterwave',
        'stripe',
        'paypal',
        'razorpay',
        'monnify',
        'now_payments',
        'razorpay'
      ])
      ->select('id', 'name', 'keyword')
      ->get();

    $data['offline_gateways'] = DB::table('offline_gateways')
      ->where('status', 1)
      ->orderBy('serial_number', 'asc')
      ->select('id', 'name', 'short_description', 'instructions', 'has_attachment')
      ->get();

    $stripe = OnlineGateway::where('keyword', 'stripe')->first();
    $stripeInfo = $stripe ? json_decode($stripe->mobile_information, true) : null;
    $data['stripe_public_key'] = $stripeInfo['key'] ?? null;

    $razorpay = OnlineGateway::where('keyword', 'razorpay')->first();
    $data['razorpayInfo'] = $razorpay ? json_decode($razorpay->mobile_information, true) : null;


    return response()->json(['status' => 'success', 'data' => $data], 200);
  }
  /**
   * Validates the selected payment gateway and calculates the payable amount based on currency rules.
   */
  public function verfiyPayment(Request $request)
  {
    $rules = ['promotion_id' => 'required', 'gateway' => 'required'];
    $validator = Validator::make(request()->all(), $rules);
    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $chargeId = FeaturedServiceCharge::find($request->promotion_id);
    $amount = $chargeId->amount;


    $bs  = Basic::select(
      'base_currency_symbol',
      'base_currency_symbol_position',
      'base_currency_text',
      'base_currency_text_position',
      'base_currency_rate'
    )
      ->first();

    $gateway = $request['gateway'];
    $gateway = strtolower($gateway);
    $gateway_type = 'online';
    $payment_status = 'completed';
    $base_currency_text = $bs->base_currency_text;
    switch ($gateway) {
      case 'paypal':
        if ($bs->base_currency_text !== 'USD') {
          $rate = floatval($bs->base_currency_rate);
          $convertedTotal = $amount / $rate;
        }
        $paidAmount = $bs->base_currency_text === 'USD' ? $amount : $convertedTotal;
        $base_currency_text = 'USD';
        break;
      case 'paystack':
        if ($bs->base_currency_text !== 'NGN') {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for paystack payment.'], 422);
        }
        $paidAmount = $amount * 100;
        break;
      case 'flutterwave':
        $allowedCurrencies = array('BIF', 'CAD', 'CDF', 'CVE', 'EUR', 'GBP', 'GHS', 'GMD', 'GNF', 'KES', 'LRD', 'MWK', 'MZN', 'NGN', 'RWF', 'SLL', 'STD', 'TZS', 'UGX', 'USD', 'XAF', 'XOF', 'ZMK', 'ZMW', 'ZWD');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for flutterwave payment.'], 422);
        }
        $paidAmount = intval($amount);
        break;
      case 'razorpay':
        if ($bs->base_currency_text !== 'INR') {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for razorpay payment.'], 422);
        }
        $paidAmount = $amount * 100;
        break;
      case 'mercadopago':
        $allowedCurrencies = array('ARS', 'BOB', 'BRL', 'CLF', 'CLP', 'COP', 'CRC', 'CUC', 'CUP', 'DOP', 'EUR', 'GTQ', 'HNL', 'MXN', 'NIO', 'PAB', 'PEN', 'PYG', 'USD', 'UYU', 'VEF', 'VES');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for mercadopago payment.'], 422);
        }
        $paidAmount = intval($amount);
        break;
      case 'mollie':
        $allowedCurrencies = array('AED', 'AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HRK', 'HUF', 'ILS', 'ISK', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'USD', 'ZAR');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for mollie payment.'], 422);
        }
        $paidAmount = sprintf('%0.2f', $amount);
        break;
      case 'stripe':
        if ($bs->base_currency_text !== 'USD') {
          $rate = floatval($bs->base_currency_rate);
          $convertedTotal = round(($amount / $rate), 2);
        }

        $paidAmount = $bs->base_currency_text === 'USD' ? $amount : $convertedTotal;
         $base_currency_text = 'USD';
        break;
      case 'authorize.net':
        $allowedCurrencies = array('USD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 'NOK', 'PLN', 'SEK', 'AUD', 'NZD');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for authorize.net payment.'], 422);
        }
        $paidAmount = sprintf('%0.2f', $amount);
        break;
      case 'phonepe':
        $allowedCurrencies = array('INR');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for phonepe payment.'], 422);
        }
        $paidAmount = $amount * 100;
        break;
      case 'myfatoorah':
        $allowedCurrencies = array('KWD', 'SAR', 'BHD', 'AED', 'QAR', 'OMR', 'JOD');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for myfatoorah payment.'], 422);
        }
        $paidAmount = intval($amount);
        break;
      case 'midtrans':
        $allowedCurrencies =  array('IDR');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for midtrans payment.'], 422);
        }
        $paidAmount = (int)round($amount);
        break;
      case 'toyyibpay':
        $allowedCurrencies =  array('RM');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for toyyibpay payment.'], 422);
        }
        $paidAmount = $amount * 100;
        break;
      case 'xendit':
        $allowedCurrencies =  array('IDR', 'PHP', 'USD', 'SGD', 'MYR');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for xendit payment.'], 422);
        }
        $paidAmount = $amount;
        break;
      case 'monnify':
        $allowedCurrencies =  array('NGN');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for monnify payment.'], 422);
        }
        $paidAmount = $amount;
        break;
      case 'now_payments':
        $allowedCurrencies =  array('USD', 'EUR', 'GBP', 'USDT', 'BTC', 'ETH');
        if (!in_array($bs->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for now_payments payment.'], 422);
        }
        $paidAmount = $amount;
        break;
      default:
        $paidAmount = $amount;
        $gateway_type = 'offline';
        $payment_status = 'pending';
        break;
    }
    return response()->json([
      'status' => 'success',
      'amount' => $paidAmount,
      'promotion_id' => $request->promotion_id,
      'gateway_type' => $gateway_type,
      'payment_status' => $payment_status,
      'currency' => $base_currency_text
    ], 200);
  }


  public function paymentProcess(Request $request)
  {
    $rules = [
      'promotion_id' => 'required',
      'gateway' => 'required',
      'gateway_type' => 'required',
      'service_id' => 'required',
      'payment_status' => 'required',
      'language_code' => 'required'
    ];
    $validator = Validator::make(request()->all(), $rules);
    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $promotionList = FeaturedServiceCharge::find($request->promotion_id);
    $vendorId = Auth::guard('sanctum_vendor')->user()->id;
    $bs  = Basic::select(
      'base_currency_symbol',
      'base_currency_symbol_position',
      'base_currency_text',
      'base_currency_text_position',
      'base_currency_rate'
    )
      ->first();


    $orderInfo = ServicePromotion::create([
      'order_number' => uniqid(),
      'amount' => $request->amount,
      'day' => $promotionList->day,
      'service_id' => $request->service_id,
      'vendor_id' => $vendorId,
      'currency_text'             => $bs->base_currency_text,
      'currency_text_position'    => $bs->base_currency_text_position,
      'currency_symbol'           => $bs->base_currency_symbol,
      'currency_symbol_position'  => $bs->base_currency_symbol_position,
      'payment_method' => $request->gateway,
      'gateway_type' => $request->gateway_type,
      'payment_status' => $request->payment_status,
      'order_status' => 'pending',
      'attachment' => $request->attachment ? $request->attachment : null,
    ]);
    //transaction create
    $after_balance = NULL;
    $pre_balance = NULL;
    $transactionData = [
      'vendor_id' => Auth::guard('sanctum_vendor')->user()->id,
      'transaction_type' => 'featured_service',
      'pre_balance' => $pre_balance,
      'actual_total' => $request->amount,
      'after_balance' => $after_balance,
      'admin_profit' => $request->amount,
      'payment_method' => $request->gateway,
      'currency_symbol'           => $bs->base_currency_symbol,
      'currency_symbol_position'  => $bs->base_currency_symbol_position,
      'payment_status' => $request->payment_status,
    ];
    store_transaction($transactionData);

    $languageId = Language::where('code', $request->language_code)->first()->id;

    $invoice = $this->generateInvoice($orderInfo);
    $orderInfo->update(['invoice' => $invoice]);
    $this->sendFaturedMail($orderInfo, $languageId);
    return $orderInfo;
  }

  private function generateInvoice($orderInfo)
  {
    $fileName = $orderInfo->order_number . '.pdf';
    $data['orderInfo'] = $orderInfo;

    $directory = public_path('assets/file/invoices/featured/service/');
    @mkdir($directory, 0775, true);

    $fileLocated = $directory . $fileName;
    PDF::loadView('frontend.services.featured-service.invoice', $data)->save($fileLocated);
    return $fileName;
  }

  private function sendFaturedMail($featuredInfo, $languageId)
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
}
