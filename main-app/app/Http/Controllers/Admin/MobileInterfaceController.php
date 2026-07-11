<?php

namespace App\Http\Controllers\Admin;

use App\Models\Language;
use Illuminate\Http\Request;
use App\Http\Helpers\UploadFile;
use App\Rules\ImageMimeTypeRule;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\MobileSection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\PaymentGateway\OnlineGateway;

class MobileInterfaceController extends Controller
{
  //mobile interface main page
  public function index(Request $request)
  {
    return view('admin.mobile-interface.index');
  }

  //home page content view and update function
  public function content(Request $request)
  {
    $Language = Language::where('code', $request->language)->firstOrFail();

    $data['data'] = MobileSection::where('language_id', $Language->id)->first();
    return view('admin.mobile-interface.content', $data);
  }

  public function update(Request $request)
  {
    $rules = [
      'hero_section_title' => 'max:255',
      'hero_section_subtitle' => 'max:255',
      'category_section_title' => 'max:255',
      'featured_service_section_title' => 'max:255',
      'vendor_section_title' => 'max:255',
      'latest_service_section_title' => 'max:255',
    ];
    if ($request->hasFile('hero_section_background_img')) {
      $rules['hero_section_background_img'] = new ImageMimeTypeRule();
    }
    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
      return response()->json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }

    $language = Language::where('code', $request->language)->firstOrFail();
    $content = MobileSection::where('Language_id', $language->id)->first();

    if ($request->hasFile('hero_section_background_img')) {
      $newHeroImage = $request->file('hero_section_background_img');
      if (!empty($content->hero_section_background_img)) {
        $oldHeroImage = $content->hero_section_background_img;
        $heroImageName = UploadFile::update(public_path('assets/img/hero/'), $newHeroImage, $oldHeroImage);
      } else {
        $heroImageName = UploadFile::store(public_path('assets/img/hero/'), $newHeroImage);
      }
    }


    if (!empty($content)) {
      $content->Language_id = $language->id;
    } else {
      $content = new MobileSection();
      $content->Language_id = $language->id;
    }

    $content->hero_section_title = $request->hero_section_title;
    $content->hero_section_subtitle = $request->hero_section_subtitle;
    $content->hero_section_text = $request->hero_section_text;
    $content->category_section_title = $request->category_section_title;
    $content->featured_service_section_title = $request->featured_service_section_title;
    $content->vendor_section_title = $request->vendor_section_title;
    $content->latest_service_section_title = $request->latest_service_section_title;

    if (isset($heroImageName)) {
      $content->hero_section_background_img = $heroImageName;
    }
    $content->save();

    session()->flash('success', __('Updated successfully'));
    return response()->json(['status' => 'success']);
  }

  //general setting view and update function
  public function setting(Request $request)
  {
    $data['data'] = DB::table('basic_settings')->select('mobile_favicon', 'mobile_app_logo')
      ->first();
    $data['config'] = include(public_path('config.php'));
    return view('admin.mobile-interface.general-settings', $data);
  }

  public function settingUpdate(Request $request)
  {
    $bs = DB::table('basic_settings')->select('mobile_favicon', 'mobile_app_logo')->first();

    $rules = [
      'api_base_url' => 'required|url',
    ];

    if (is_null($bs->mobile_favicon)) {
      $rules['mobile_favicon'] = 'required|mimes:png,jpg,jpeg,svg';
    }
    if (is_null($bs->mobile_favicon)) {
      $rules['mobile_app_logo'] = 'required|mimes:png,jpg,jpeg,svg';
    }

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors())->withInput();
    }

    $publicConfig = include base_path('public/config.php');
    $publicConfig['PUBLIC_API_BASE'] = $request->api_base_url;
    $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
    file_put_contents(base_path('public/config.php'), $configContent);


    if ($request->hasFile('mobile_favicon')) {
      if (isset($bs->mobile_favicon)) {
        $favicon = UploadFile::update(public_path('assets/img/mobile-interface/'), $request->file('mobile_favicon'), $bs->mobile_favicon);
      } else {
        $favicon = UploadFile::store(public_path('assets/img/mobile-interface/'), $request->file('mobile_favicon'));
      }
    }
    if ($request->hasFile('mobile_app_logo')) {
      if (isset($bs->mobile_app_logo)) {
        $logo = UploadFile::update(public_path('assets/img/mobile-interface/'), $request->file('mobile_app_logo'), $bs->mobile_app_logo);
      } else {
        $logo = UploadFile::store(public_path('assets/img/mobile-interface/'), $request->file('mobile_app_logo'));
      }
    }

    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      [
        'mobile_favicon' => $favicon ?? $bs->mobile_favicon,
        'mobile_app_logo' => $logo ?? $bs->mobile_app_logo,
      ]
    );

    return redirect()->back()->with('success', __('Updated Successfully'));
  }

  //payment gateways view and update function
  public function paymentGateways()
  {
    $data['data'] = include(public_path('config.php'));

    $gateways = [
      'paypal',
      'paystack',
      'flutterwave',
      'mercadopago',
      'mollie',
      'stripe',
      'authorize.net',
      'phonepe',
      'paytabs',
      'midtrans',
      'toyyibpay',
      'myfatoorah',
      'xendit',
      'monnify',
      'now_payments',
      'razorpay'
    ];

    foreach ($gateways as $gateway) {
      $key = str_replace('.', '_', $gateway);

      $data[$key] = OnlineGateway::where('keyword', $gateway)
        ->select('mobile_status', 'mobile_information')
        ->first();
    }
    return view('admin.mobile-interface.gateway', $data);
  }

  //plugins view function
  public function plugins()
  {
    $data = DB::table('basic_settings')->select('firebase_admin_json')
      ->first();
    return view('admin.mobile-interface.plugins', compact('data'));
  }
}
