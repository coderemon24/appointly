<?php

namespace App\Http\Controllers\Api;

use Config;
use Carbon\Carbon;
use App\Models\Admin;
use App\Models\Vendor;
use App\Models\Language;
use App\Models\VendorInfo;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use App\Models\HomePage\Section;
use App\Models\Services\Services;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Models\Services\ServiceReview;
use App\Models\Services\ServiceCategory;
use Illuminate\Support\Facades\Validator;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\FrontEnd\MiscellaneousController;
use App\Http\Controllers\FrontEnd\VendorDistanceController;

class VendorController extends Controller
{
  //index
  public function index(Request $request)
  {
    $misc = new MiscellaneousController();
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();
    $data['language'] = $language;

    $data['pageHeading'] = $misc->getPageHeading($language);


    $name = $location = null;
    $vendorIds = [];
    if ($request->filled('name')) {
      $name = $request->name;
      $u_infos = Vendor::where('vendors.username', 'like', '%' . $name . '%')->get();
      $v_infos = VendorInfo::where([['vendor_infos.name', 'like', '%' . $name . '%'], ['language_id', $language->id]])->get();

      foreach ($u_infos as $info) {
        if (!in_array($info->id, $vendorIds)) {
          array_push($vendorIds, $info->id);
        }
      }
      foreach ($v_infos as $v_info) {
        if (!in_array($v_info->vendor_id, $vendorIds)) {
          array_push($vendorIds, $v_info->vendor_id);
        }
      }
    }
    if ($request->filled('location')) {
      $location = $request->location;
    }

    $secInfo = Section::query()->select('subscribe_section_status')->first();
    $data['secInfo'] = $secInfo;

    if ($request->filled('location')) {
      $bs = Basic::select('google_map_status')->first();
      if ($bs->google_map_status == 1) {
        $distanceAddress = new VendorDistanceController;
        $vendorIds = $distanceAddress->index($location, $language->id);
      } else {
        $vendor_contents = VendorInfo::where('country', 'like', '%' . $location . '%')
          ->orWhere('city', 'like', '%' . $location . '%')
          ->orWhere('state', 'like', '%' . $location . '%')
          ->orWhere('zip_code', 'like', '%' . $location . '%')
          ->orWhere('address', 'like', '%' . $location . '%')
          ->get();
        foreach ($vendor_contents as $vendor_content) {
          if (!in_array($vendor_content->vendor_id, $vendorIds)) {
            array_push($vendorIds, $vendor_content->vendor_id);
          }
        }
      }
    }

    $data['bgImg'] = asset('assets/img/' . $misc->getBreadcrumb()->breadcrumb);

    // Modified query to include admins
    $admin = Admin::whereNull('role_id')
      ->when($name, function ($query) use ($name) {
        return $query->where('username', 'like', '%' . $name . '%')
          ->orWhere('first_name', 'like', '%' . $name . '%')
          ->orWhere('last_name', 'like', '%' . $name . '%');
      })
      ->when($location, function ($query) use ($location) {
        return $query->where('address', 'like', '%' . $location . '%');
      })
      ->first();
    $admin->image = asset('assets/img/admins/' . $admin->image);
    $data['admin'] = $admin;

    $vendors = Vendor::with(['serviceReview' => function ($query) {
      $query->select('id', 'vendor_id', 'rating');
    }])->join('memberships', 'memberships.vendor_id', 'vendors.id')
      ->join('vendor_infos', 'vendors.id', '=', 'vendor_infos.vendor_id')
      ->where([
        ['memberships.status', 1],
        ['memberships.start_date', '<=', Carbon::now()->format('Y-m-d')],
        ['memberships.expire_date', '>=', Carbon::now()->format('Y-m-d')],
      ])
      ->where('vendors.status', 1)
      ->when($name, function ($query) use ($vendorIds) {
        return $query->whereIn('vendors.id', $vendorIds);
      })
      ->when($location, function ($query) use ($vendorIds) {
        return $query->whereIn('vendors.id', $vendorIds);
      })
      ->where('vendor_infos.language_id', $language->id)
      ->where('vendors.id', '!=', 0)
      ->select('vendors.*', 'vendors.id as vendorId')
      ->orderBy('vendors.created_at', 'desc')
      ->get()
      ->map(function ($vendor) {
        $vendor->photo = asset('assets/admin/img/vendor-photo/' . $vendor->photo);

        // Calculate average rating
        $vendor->averageRating = $vendor->serviceReview->count()
          ? number_format($vendor->serviceReview->avg('rating'), 1)
          : 0;

        return $vendor;
      });

    $data['vendors'] = $vendors;
    $data['admin'] = $admin;

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }

  //details
  public function details(Request $request, $d)
  {
    $misc = new MiscellaneousController();
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $data['language'] = $language;

    $data['bgImg'] = asset('assets/img/' . $misc->getBreadcrumb()->breadcrumb);
    $data['pageHeading'] = $misc->getPageHeading($language);

    if ($d == 'admin') {
      $vendor = Admin::first();
      $vendor_id = 0;
      $data['total_service'] = Services::where('vendor_id', 0)
        ->where('status', 1)
        ->count();
    } else {

      $vendor = Vendor::join('memberships', 'memberships.vendor_id', 'vendors.id')
        ->where([
          ['memberships.status', 1],
          ['memberships.start_date', '<=', Carbon::now()->format('Y-m-d')],
          ['memberships.expire_date', '>=', Carbon::now()->format('Y-m-d')],
        ])
        ->where('vendors.status', 1)
        ->where('vendors.status', 1)
        ->where('vendors.username', $request->username)
        ->select('vendors.*')
        ->first();
      $vendor->photo = asset('assets/admin/img/vendor-photo/' . $vendor->photo);

      if (!$vendor) {
        return response()->json([
          'success' => false,
          'message' => 'Vendor not found'
        ]);
      }

      $currentLang =  Language::where('is_default', 1)->first();
      $vendorInfo = VendorInfo::where('vendor_id', $vendor->id)->where('language_id', $language->id)->first();

      if (!$vendorInfo) {
        return response()->json([
          'success' => false,
          'message' => 'Vendor not found'
        ]);
      }


      if (empty($vendorInfo)) {
        session()->put('currentLocaleCode', $currentLang->code);

        return response()->json([
          'success' => false,
          'message' => 'Content not available. Please try another language.'
        ]);
      }
      $vendorInfo = VendorInfo::where([['vendor_id', $vendor->id], ['language_id', $language->id]])->first();
      $data['vendorInfo'] = $vendorInfo;
      $vendor_id = $vendor->id;
      $data['total_service'] = Services::where('vendor_id', $vendor_id)
        ->where('status', 1)
        ->count();
    }
    $data['vendor'] = $vendor;
    $data['vendor_details'] = ($d == 'admin') ? $vendor->details : $vendorInfo->details;
    $data['vendor_address'] = ($d == 'admin') ? $vendor->address : $vendorInfo->address;


    $reviews = ServiceReview::where('vendor_id', $vendor_id)->get();

    if ($reviews != '[]') {
      $totalRating = 0;
      foreach ($reviews as $review) {
        $totalRating += $review->rating;
      }

      $numOfReview = count($reviews);
      $data['averageRating'] = number_format($totalRating / $numOfReview, 1);
    }

    //service category
    $data['categories'] = ServiceCategory::has('service_content')->where([['language_id', $language->id], ['status', 1]])->get();

    //services
    $services = Services::join('service_contents', 'services.id', '=', 'service_contents.service_id')
      ->join('service_categories', 'service_categories.id', '=', 'service_contents.category_id')
      ->where('services.vendor_id', $vendor_id)
      ->where('service_contents.language_id', $language->id)
      ->where('services.status', 1)
      ->select(
        'services.*',
        'service_contents.name',
        'service_contents.slug',
        'service_categories.name as categoryName',
        'service_categories.id as categoryid',
        'service_contents.address'
      )
      ->orderBy('id', 'desc')
      ->get();
    $data['services'] = $services->transform(function ($service) {
      $service->service_image = asset('assets/img/services/' . $service->service_image);
      $service->formatted_price = symbolPrice($service->price);
      $service->formatted_prev_price = symbolPrice($service->prev_price);
      return $service;
    });

    $secInfo = Section::query()->select('subscribe_section_status')->first();
    $data['secInfo'] = $secInfo;
    $data['currencyInfo'] = $this->getCurrencyInfo();
    $data['info'] = Basic::select('google_recaptcha_status')->first();


    //Strip payment
    $stripe = OnlineGateway::where('keyword', 'stripe')->first();
    $stripe_info = json_decode($stripe->information, true);
    $data['stripe_key'] = $stripe_info['key'];

    //Authorize.Net payment
    $authorizeNet = OnlineGateway::where('keyword', 'authorize.net')->first();
    $authorizeInfo = json_decode($authorizeNet->information, true);

    if ($authorizeInfo['sandbox_check'] == 1) {
      $data['authorizeUrl'] = 'https://jstest.authorize.net/v1/Accept.js';
    } else {
      $data['authorizeUrl'] = 'https://js.authorize.net/v1/Accept.js';
    }

    $data['authorize_login_id'] = $authorizeInfo['login_id'];
    $data['authorize_public_key'] = $authorizeInfo['public_key'];

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }

  /**
   * send email to vendor
   */
  public function contact(Request $request)
  {
    $rules = [
      'name' => 'required',
      'email' => 'required|email:rfc,dns',
      'subject' => 'required',
      'message' => 'required'
    ];
    $info = Basic::select('google_recaptcha_status')->first();
    if ($info->google_recaptcha_status == 1) {
      $rules['g-recaptcha-response'] = 'required|captcha';
    }
    $messageArray = [];

    if ($info->google_recaptcha_status == 1) {
      $messageArray['g-recaptcha-response.required'] = 'Please verify that you are not a robot.';
      $messageArray['g-recaptcha-response.captcha'] = 'Captcha error! try again later or contact site admin.';
    }

    $validator = Validator::make($request->all(), $rules, $messageArray);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'errors' => $validator->errors()
      ], 422);
    }


    $be = Basic::select(
      'smtp_status',
      'smtp_host',
      'smtp_port',
      'encryption',
      'smtp_username',
      'smtp_password',
      'from_mail',
      'from_name'
    )->first();

    $c_message = nl2br($request->message);
    $msg = "<h4>Name : $request->name</h4>
            <h4>Email : $request->email</h4>
            <p>Message : </p>
            <p>$c_message</p>";

    $data = [
      'to' => $request->vendor_email,
      'subject' => $request->subject,
      'message' => $msg,
    ];

    if ($be->smtp_status == 1) {
      try {
        $smtp = [
          'transport' => 'smtp',
          'host' => $be->smtp_host,
          'port' => $be->smtp_port,
          'encryption' => $be->encryption,
          'username' => $be->smtp_username,
          'password' => $be->smtp_password,
          'timeout' => null,
          'auth_mode' => null,
        ];
        Config::set('mail.mailers.smtp', $smtp);
      } catch (\Exception $e) {
        return response()->json([
          'status' => 'error',
          'message' => __("Something went wrong!")
        ]);
      }
    }
    try {
      if ($be->smtp_status == 1) {
        Mail::send([], [], function (Message $message) use ($data, $be) {
          $fromMail = $be->from_mail;
          $fromName = $be->from_name;
          $message->to($data['to'])
            ->subject($data['subject'])
            ->from($fromMail, $fromName)
            ->html($data['message'], 'text/html');
        });
      }
      return response()->json([
        'status' => 'success',
        'message' => __('Message sent successfully')
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'status' => 'error',
        'message' => __("Something went wrong!")
      ]);
    }
  }
}
