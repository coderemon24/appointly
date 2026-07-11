<?php

namespace App\Http\Controllers\Api;

use DB;
use Carbon\Carbon;
use App\Models\Admin;
use App\Models\Vendor;
use App\Models\Language;
use Illuminate\Http\Request;
use App\Models\CustomPage\Page;
use App\Models\HomePage\Section;
use App\Models\Services\Services;
use App\Models\Admin\MobileSection;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceCategory;
use App\Models\PaymentGateway\OnlineGateway;

class HomeController extends Controller
{
  public function getBasic()
  {
    $basicData = DB::table('basic_settings')
      ->select('primary_color', 'secondary_color', 'mobile_app_logo', 'mobile_favicon', 'base_currency_text', 'base_currency_rate')
      ->first();

    $basicData->mobile_app_logo = asset('assets/img/mobile-interface/' . $basicData->mobile_app_logo);
    $basicData->mobile_favicon = asset('assets/img/mobile-interface/' . $basicData->mobile_favicon);

    $data['basic_data'] = $basicData;
    $data['languages'] = Language::all();

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
      'success' => true,
      'data' => $data
    ]);
  }

  /**
   * Get all plugin related information for the mobile app
   * service_view - mapview
   */
  public function getAllPlugins()
  {

    $basicData = DB::table('basic_settings')
      ->select('google_map_status', 'google_map_api_key', 'google_map_radius', 'service_view')
      ->first();

    return response()->json([
      'success' => true,
      'data' => $basicData
    ]);
  }

  public function index(Request $request)
  {
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    //home page section content
    $sectionContent = [];
    $sectionContent = MobileSection::where('language_id', $language->id)
      ->first();
    if (!is_null($sectionContent)) {
      $sectionContent->hero_section_background_img = isset($sectionContent->hero_section_background_img) ? asset('assets/img/hero/' . $sectionContent->hero_section_background_img) : null;
    }
    $data['sectionContent'] = $sectionContent;

    $vendorStatus = Vendor::where('status', 1)->select('id')->get()->toArray();

    $data['featured_services'] = Services::join('service_promotions', 'service_promotions.service_id', '=', 'services.id')
      ->join('service_contents', 'services.id', '=', 'service_contents.service_id')
      ->join('service_categories', 'service_categories.id', '=', 'service_contents.category_id')
      ->where('service_contents.language_id', $language->id)
      ->where('services.status', 1)
      ->where(function ($query) use ($vendorStatus) {
        $query->whereIn('services.vendor_id', $vendorStatus)
          ->orWhere('services.vendor_id', 0);
      })
      ->where('service_promotions.order_status', '=', 'approved')
      ->whereDate('service_promotions.end_date', '>=', Carbon::now()->format('Y-m-d'))
      ->when('services.vendor_id' != "0", function ($query) {
        return $query->leftJoin('memberships', 'services.vendor_id', '=', 'memberships.vendor_id')
          ->where(function ($query) {
            $query->where([
              ['memberships.status', '=', 1],
              ['memberships.start_date', '<=', now()->format('Y-m-d')],
              ['memberships.expire_date', '>=', now()->format('Y-m-d')],
            ])->orWhere('services.vendor_id', '=', 0);
          });
      })
      ->select(
        'services.*',
        'service_contents.name',
        'service_contents.slug',
        'service_contents.address',
        'service_categories.name as categoryName',
        'service_categories.slug as categoryslug',
        'service_categories.id as categoryId',
      )
      ->inRandomOrder()
      ->get()
      ->map(function ($featured_service) {
        $featured_service->service_image = asset('assets/img/services/' . $featured_service->service_image);
        $featured_service->formatted_price = symbolPrice($featured_service->price);
        $featured_service->formatted_prev_price = symbolPrice($featured_service->prev_price);
        return $featured_service;
      });

    //services
    // $perPage = ($themeVersion != 3) ? 8 : 6;
    $data['services'] = Services::join('service_contents', 'services.id', '=', 'service_contents.service_id')
      ->join('service_categories', 'service_categories.id', '=', 'service_contents.category_id')
      ->where('service_contents.language_id', $language->id)
      ->where('service_categories.language_id', $language->id)
      ->where('services.status', 1)
      ->where(function ($query) use ($vendorStatus) {
        $query->whereIn('services.vendor_id', $vendorStatus)
          ->orWhere('services.vendor_id', 0);
      })
      ->when('services.vendor_id' != "0", function ($query) {
        return $query->leftJoin('memberships', 'services.vendor_id', '=', 'memberships.vendor_id')
          ->where(function ($query) {
            $query->where([
              ['memberships.status', '=', 1],
              ['memberships.start_date', '<=', now()->format('Y-m-d')],
              ['memberships.expire_date', '>=', now()->format('Y-m-d')],
            ])->orWhere('services.vendor_id', '=', 0);
          });
      })
      ->select(
        'services.*',
        'service_contents.name',
        'service_contents.slug',
        'service_contents.address',
        'service_categories.name as categoryName',
        'service_categories.slug as categoryslug',
        'service_categories.id as categoryId'
      )
      ->orderByDesc('services.id')
      ->take(8)
      ->get()
      ->map(function ($service) {
        $service->service_image = asset('assets/img/services/' . $service->service_image);
        $service->formatted_price = symbolPrice($service->price);
        $service->formatted_prev_price = symbolPrice($service->prev_price);
        return $service;
      });


    //admin info
    $admin = Admin::whereNull('role_id')->first();

    if (!$admin) {
      return response()->json([
        'success' => false,
        'message' => 'Admin not found'
      ], 404);
    }
    $admin->image = asset('assets/img/admins/' . $admin->image);
    $data['admin'] = $admin;


    $categories = ServiceCategory::has('service_content')
      ->where('language_id', $language->id)
      ->where('status', 1)
      ->orderBy('serial_number', 'asc')
      ->get()
      ->map(function ($category) {
        $category->image = $category->image ? asset('assets/img/category/' . $category->image) : null;
        $category->mobail_image = $category->mobail_image ? asset('assets/img/category/' . $category->mobail_image) : null;
        return $category;
      });


    //home page category wise service total count
    $vendorStatus = \App\Models\Vendor::where('status', 1)->select('id')->get()->toArray();
    foreach ($categories as $category) {
      $serviceCount = Services::join(
        'service_contents',
        'service_contents.service_id',
        '=',
        'services.id'
      )
        ->where(function ($query) use ($vendorStatus) {
          $query->whereIn('services.vendor_id', $vendorStatus)->orWhere('services.vendor_id', 0);
        })
        ->where('services.status', 1)
        ->where('service_contents.category_id', $category->id)
        ->count();

      // Add service count to the category object
      $category->service_count = $serviceCount;
    }

    $data['categories'] = $categories;


    //all vendors
    $featuredVendors = Vendor::with(['vendor_info' => function ($q) use ($language) {
      $q->where('language_id', $language->id);
    }])
      ->withCount(['service as total_service' => function ($q) {
        $q->where('status', 1);
      }])
      ->withAvg('serviceReview as avg_rating', 'rating')
      ->whereHas('memberships', function ($q) {
        $q->where('status', 1)
          ->where('start_date', '<=', today())
          ->where('expire_date', '>=', today());
      })
      ->where([
        ['status', 1],
        ['id', '!=', 0],
        ['featured', 1]
      ])
      ->get()
      ->map(function ($vendor) {
        $vendor->photo = $vendor->photo
          ? asset('assets/admin/img/vendor-photo/' . $vendor->photo)
          : null;

        // format avg_rating
        $vendor->avg_rating = $vendor->avg_rating ? number_format($vendor->avg_rating, 1) : 0;

        return $vendor;
      });

    $data['featuredVendors'] = $featuredVendors;

    $sectionInfo = Section::select('custom_section_status')->first();
    if (!empty($sectionInfo->custom_section_status)) {
      $info = json_decode($sectionInfo->custom_section_status, true);
      $data['homecusSec'] = $info;
    }

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }
  public function currency()
  {
    $information['currencyInfo'] = $this->getCurrencyInfo();
    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }
}
