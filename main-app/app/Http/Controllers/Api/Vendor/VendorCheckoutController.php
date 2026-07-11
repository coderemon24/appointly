<?php

namespace App\Http\Controllers\Api\Vendor;

use PDF;
use Carbon\Carbon;
use App\Models\Vendor;
use App\Models\Package;
use App\Models\Membership;
use Illuminate\Http\Request;
use App\Http\Helpers\MegaMailer;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\VendorPermissionHelper;

class VendorCheckoutController extends Controller
{
  /**
   * Validates the selected payment gateway and calculates the payable amount based on currency rules.
   */
  public function verfiyPayment(Request $request)
  {
    $rules = ['package_id' => 'required', 'gateway' => 'required'];
    $validator = Validator::make(request()->all(), $rules);
    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $package = Package::where('id', $request->package_id)->first();
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
    $amount = $package->price;
    $status = 1;
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
        $status = 0;
        break;
    }
    return response()->json([
      'status' => 'success',
      'paidAmount' => $paidAmount,
      'payment_method' => $gateway,
      'status' => $status,
      'currency' => $base_currency_text
    ]);
  }

  /**
   * Handles the payment execution and initiates the transaction process.
   */
  public function paymentProcess(Request $request)
  {
    $rules = [
      'package_id' => 'required',
      'payment_method' => 'required',
      'status' => 'required',
      'payment_for' => 'required',
    ];
    $validator = Validator::make(request()->all(), $rules);
    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $package_id = $request->package_id;
    $paymentFor = $request->payment_for;
    $package = Package::where('id', $package_id)->first();
    $vendor = Auth::guard('sanctum_vendor')->user();
    $bs = Basic::first();
    $transaction_id = VendorPermissionHelper::uniqidReal(8);
    $transaction_details = null;


    if ($paymentFor == 'membership') {
      $storeData = [];
      $latestMembership = Membership::query()
        ->where('vendor_id', $vendor->id)
        ->where('status', '<>', 2)
        ->whereYear('start_date', '<>', 9999)
        ->whereDate('expire_date', '>=', now()->toDateString())
        ->latest('id')
        ->first();

      $previousPackage = null;
      if ($latestMembership) {
        $previousPackage = Package::find($latestMembership->package_id);
      }

      if (
        ($previousPackage && $previousPackage->term === 'lifetime') ||
        ($latestMembership && (int)$latestMembership->is_trial === 1)
      ) {
        $start = now()->startOfDay();
      } else {
        $baseExpire = $latestMembership?->expire_date ?? now()->subDay();
        $start = Carbon::parse($baseExpire)->addDay()->startOfDay();
      }

      if ($package->term === 'monthly') {
        $expire = $start->copy()->addMonth()->subDay();
      } elseif ($package->term === 'lifetime') {
        $expire = Carbon::maxValue();
      } else {
        $expire = $start->copy()->addYear()->subDay();
      }
      $storeData['start_date'] = $start->toDateString();
      $storeData['expire_date'] = $expire->toDateString();
      $storeData['package_id'] = $request->package_id;
      $storeData['vendor_id'] = $vendor->id;
      $storeData['price'] = $package->price;
      $storeData['phone'] = $vendor->phone;
      $storeData['payment_method'] = $request->payment_method;
      $storeData['status'] = $request->status;

      $amount = $package->price;
      $storeVendor = $this->storeMembership($storeData, $transaction_id, $transaction_details);

      $lastMemb = $storeVendor->memberships()->orderBy('id', 'DESC')->first();

      $activation = Carbon::parse($lastMemb->start_date);
      $expire = Carbon::parse($lastMemb->expire_date);


      $file_name = $this->makeInvoice($storeData, "membership", $storeVendor, 12345678, $amount, "Paypal", $storeData['phone'], $bs->base_currency_symbol_position, $bs->base_currency_symbol, $bs->base_currency_text, $transaction_id, $package->title, $lastMemb);

      $mailer = new MegaMailer();
      $data = [
        'toMail' => $storeVendor->email,
        'toName' => $storeVendor->fname,
        'username' => $storeVendor->username,
        'package_title' => $package->title,
        'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
        'discount' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $lastMemb->discount . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
        'total' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $lastMemb->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
        'activation_date' => $activation->toFormattedDateString(),
        'expire_date' => Carbon::parse($expire->toFormattedDateString())->format('Y') == '9999' ? 'Lifetime' : $expire->toFormattedDateString(),
        'membership_invoice' => $file_name,
        'website_title' => $bs->website_title,
        'templateType' => 'package_purchase',
        'type' => 'registrationWithPremiumPackage'
      ];
      $mailer->mailFromAdmin($data);
      @unlink(public_path('assets/front/invoices/' . $file_name));

      return response()->json(['status' => 'success', 'message' => 'Your payment has been completed.'], 200);
    } elseif ($paymentFor === "extend") {

      $amount = $package->price;
      $password = uniqid('qrcode');

      $lastMemb = Membership::where('vendor_id', $vendor->id)
        ->where('status', '<>', 2)
        ->orderBy('id', 'DESC')
        ->first();

      if (!$lastMemb) {
        return response()->json([
          'status' => 'error',
          'message' => 'No previous membership found to extend.'
        ], 422);
      }

      $lastStart = Carbon::parse($lastMemb->start_date)->startOfDay();
      $lastExpire = Carbon::parse($lastMemb->expire_date)->startOfDay();
      if ($lastExpire->format('Y') == '9999') {
        return response()->json([
          'status' => 'error',
          'message' => 'Lifetime membership cannot be extended.'
        ], 422);
      }


      if ($lastExpire->gte(now()->startOfDay())) {
        $start = $lastStart;
        $base = $lastExpire;
      } else {
        $start = now()->startOfDay();
        $base = $start;
      }

      if ($package->term === 'monthly') {
        $newExpire = $base->copy()->addMonth();
      } elseif ($package->term === 'lifetime') {
        $newExpire = Carbon::maxValue();
      } else {
        $newExpire = $base->copy()->addYear();
      }

      $storeData = [];
      $storeData['start_date'] = $start->toDateString();
      $storeData['expire_date'] = $newExpire->toDateString();
      $storeData['package_id'] = $request->package_id;
      $storeData['vendor_id'] = $vendor->id;
      $storeData['price'] = $package->price;
      $storeData['phone'] = $vendor->phone;
      $storeData['payment_method'] = $request->payment_method;
      $storeData['status'] = $request->status;

      $vendorStored = $this->storeMembership($storeData, $transaction_id, $transaction_details);

      $newMemb = $vendorStored->memberships()->orderBy('id', 'DESC')->first();

      $activation = Carbon::parse($newMemb->start_date);
      $expire = Carbon::parse($newMemb->expire_date);

      $file_name = $this->makeInvoice(
        $storeData,
        "extend",
        $vendorStored,
        $password,
        $amount,
        $storeData["payment_method"],
        $vendorStored->phone,
        $bs->base_currency_symbol_position,
        $bs->base_currency_symbol,
        $bs->base_currency_text,
        $transaction_id,
        $package->title,
        $newMemb
      );

      $mailer = new MegaMailer();
      $data = [
        'toMail' => $vendorStored->email,
        'toName' => $vendorStored->fname,
        'username' => $vendorStored->username,
        'package_title' => $package->title,
        'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '')
          . $package->price
          . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
        'activation_date' => $activation->toFormattedDateString(),
        'expire_date' => ($expire->format('Y') == '9999') ? 'Lifetime' : $expire->toFormattedDateString(),
        'membership_invoice' => $file_name,
        'website_title' => $bs->website_title,
        'templateType' => 'package_purchase',
        'type' => 'membershipExtend'
      ];

      $mailer->mailFromAdmin($data);
      @unlink(public_path('assets/front/invoices/' . $file_name));

      return response()->json(['status' => 'success', 'message' => 'Your payment has been completed.'], 200);
    }
  }

  /**
   * Stores the membership details after successful payment transaction.
   */
  private function storeMembership($storeData, $transaction_id, $transaction_details)
  {
    $abs = Basic::first();
    Config::set('app.timezone', $abs->timezone);

    $vendor = Vendor::query()->find($storeData['vendor_id']);
    $previousMembership = Membership::query()
      ->select('id', 'package_id', 'is_trial')
      ->where([
        ['vendor_id', $vendor->id],
        ['start_date', '<=', Carbon::now()->toDateString()],
        ['expire_date', '>=', Carbon::now()->toDateString()]
      ])
      ->where('status', 1)
      ->orderBy('created_at', 'DESC')
      ->first();
    if (!is_null($previousMembership)) {
      $previousPackage = Package::query()
        ->select('term')
        ->where('id', $previousMembership->package_id)
        ->first();

      if (($previousPackage->term === 'lifetime' || $previousMembership->is_trial == 1) && $transaction_details != '"offline"') {
        $membership = Membership::find($previousMembership->id);
        $membership->expire_date = Carbon::parse($storeData['start_date']);
        $membership->save();
      }
    }
    if ($vendor) {
      $membership = Membership::create([
        'price' => $storeData['price'],
        'currency' => $abs->base_currency_text,
        'currency_symbol' => $abs->base_currency_symbol,
        'payment_method' => $storeData["payment_method"],
        'transaction_id' => $transaction_id,
        'status' => ($transaction_details != '"offline"') ? $storeData["status"] : 0,
        // 'receipt' => $storeData["receipt_name"], //kaj kora hoy nai
        'transaction_details' => $transaction_details,
        'settings' => json_encode($abs),
        'package_id' => $storeData['package_id'],
        'vendor_id' => $vendor->id,
        'start_date' => Carbon::parse($storeData['start_date']),
        'expire_date' => Carbon::parse($storeData['expire_date']),
        'is_trial' => 0,
        'trial_days' => 0,
        'conversation_id' => $storeData['payment_method'] == 'Iyzico' ? $storeData['conversation_id'] : NULL
      ]);

      //update vendor appointment limit number
      $number_of_appointment = Package::where('id', $membership->package_id)
        ->value('number_of_appointment');
      $vendorUpdate = Vendor::find($membership->vendor_id);
      $vendorUpdate->update(['total_appointment' => $number_of_appointment]);
    }
    return $vendor;
  }


  public function makeInvoice($request, $key, $member, $password, $amount, $payment_method, $phone, $base_currency_symbol_position, $base_currency_symbol, $base_currency_text, $order_id, $package_title, $membership)
  {
    $file_name = uniqid($key) . ".pdf";
    $pdf = PDF::loadView('pdf.membership', compact('request', 'member', 'password', 'amount', 'payment_method', 'phone', 'base_currency_symbol_position', 'base_currency_symbol', 'base_currency_text', 'order_id', 'package_title', 'membership'));
    $output = $pdf->output();
    @mkdir(public_path('assets/front/invoices/'), 0775, true);
    file_put_contents(public_path('assets/front/invoices/') . $file_name, $output);
    return $file_name;
  }
}
