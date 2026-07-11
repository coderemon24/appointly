<?php

namespace App\Http\Controllers\Api\Vendor;

use Carbon\Carbon;
use App\Models\Package;
use App\Models\Language;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\PaymentGateway\OfflineGateway;

class BuyPlanController extends Controller
{
  public function index()
  {
    $membership = Membership::first();
    $abs = Basic::first();
    Config::set('app.timezone', $abs->timezone);

    $vendorId = Auth::guard('sanctum_vendor')->user()->id;



    if (session()->has('lang')) {
      $currentLang = Language::where('code', session()->get('lang'))->first();
    } else {
      $currentLang = Language::where('is_default', 1)->first();
    }
    $data['bex'] = $currentLang->basic_extended;
    $data['packages'] = Package::where('status', '1')->get();

    $nextPackageCount = Membership::query()->where([
      ['vendor_id', $vendorId],
      ['expire_date', '>=', Carbon::now()->toDateString()]
    ])->whereYear('start_date', '<>', '9999')->where('status', '<>', 2)->count();
    //current package
    $data['current_membership'] = Membership::query()->where([
      ['vendor_id', $vendorId],
      ['start_date', '<=', Carbon::now()->toDateString()],
      ['expire_date', '>=', Carbon::now()->toDateString()]
    ])->where('status', 1)->whereYear('start_date', '<>', '9999')->first();
    if ($data['current_membership'] != null) {
      $countCurrMem = Membership::query()->where([
        ['vendor_id', $vendorId],
        ['start_date', '<=', Carbon::now()->toDateString()],
        ['expire_date', '>=', Carbon::now()->toDateString()]
      ])->where('status', 1)->whereYear('start_date', '<>', '9999')->count();
      if ($countCurrMem > 1) {
        $data['next_membership'] = Membership::query()->where([
          ['vendor_id', $vendorId],
          ['start_date', '<=', Carbon::now()->toDateString()],
          ['expire_date', '>=', Carbon::now()->toDateString()]
        ])->where('status', '<>', 2)->whereYear('start_date', '<>', '9999')->orderBy('id', 'DESC')->first();
      } else {
        $data['next_membership'] = Membership::query()->where([
          ['vendor_id', $vendorId],
          ['start_date', '>', $data['current_membership']->expire_date]
        ])->whereYear('start_date', '<>', '9999')->where('status', '<>', 2)->first();
      }
      $data['next_package'] = $data['next_membership'] ? Package::query()->where('id', $data['next_membership']->package_id)->first() : null;
    } else {
      $data['next_package'] = null;
    }
    $data['current_package'] = $data['current_membership'] ? Package::query()->where('id', $data['current_membership']->package_id)->first() : null;
    $data['package_count'] = $nextPackageCount;

    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }

  public function checkout($package_id)
  {
    $vendorId = Auth::guard('sanctum_vendor')->user()->id;
    $packageCount = Membership::query()->where([
      ['vendor_id', $vendorId],
      ['expire_date', '>=', Carbon::now()->toDateString()]
    ])->whereYear('start_date', '<>', '9999')->where('status', '<>', 2)->count();

    $hasPendingMemb = VendorPermissionHelper::hasPendingMembership($vendorId);


    if ($hasPendingMemb) {
      return response()->json([
        'status' => false,
        'message' => __('You already have a Pending Membership Request.')
      ], 400);
    }
    if ($packageCount >= 2) {
      return response()->json([
        'status' => false,
        'message' => __('You have another package to activate after the current package expires. You cannot purchase / extend any package, until the next package is activated')
      ], 400);
    }


    $currentLang = Language::where('is_default', 1)
      ->first();

    $be = $currentLang->basic_extended;


    $data['package'] = Package::query()->find($package_id);
    $data['membership'] = Membership::query()->where([
      ['vendor_id', $vendorId],
      ['expire_date', '>=', \Carbon\Carbon::now()->format('Y-m-d')]
    ])->where('status', '<>', 2)->whereYear('start_date', '<>', '9999')
      ->latest()
      ->first();
    $data['previousPackage'] = null;
    if (!is_null($data['membership'])) {
      $data['previousPackage'] = Package::query()
        ->where('id', $data['membership']->package_id)
        ->first();
    }
    $data['bex'] = $be;

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


    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }
}
