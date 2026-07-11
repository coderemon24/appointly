<?php

namespace App\Http\Controllers\Api\StaffAdmin;

use Auth;
use Session;
use Response;
use Validator;
use App\Models\Language;
use App\Models\Staff\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Helpers\UploadFile;
use App\Models\Staff\StaffContent;
use App\Models\Staff\StaffService;
use App\Models\BasicSettings\Basic;
use App\Rules\MatchOldPasswordRule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\Services\ServiceBooking;
use App\Http\Controllers\FrontEnd\MiscellaneousController;

class StaffController extends Controller
{
  public function login()
  {
    $misc = new MiscellaneousController();

    $language = $misc->getLanguage();

    $queryResult['seoInfo'] = $language->seoInfo()->select('meta_keywords_staff_login_page', 'meta_description_staff_login_page')->first();

    $queryResult['pageHeading'] = $misc->getPageHeading($language);

    $queryResult['bgImg'] = $misc->getBreadcrumb();
    $queryResult['bs'] = Basic::query()->select('google_recaptcha_status', 'facebook_login_status', 'google_login_status')->first();

    return response()->json(['status' => 'success', 'data' => $queryResult], 200);
  }

  public function loginSubmit(Request $request)
  {
    $rules = [
      'username' => 'required',
      'password' => 'required'
    ];

    $messages = [];
    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
    }
    $staff = Staff::where('username', $request->username)->first();
    $staff->image = asset('assets/img/staff/' . $staff->image);

    if (!$staff || !Hash::check($request->password, $staff->password)) {
      return response()->json([
        'success' => false,
        'message' => __('Incorrect username or password')
      ], 422);
    }
    // Clean old tokens
    $staff->tokens()->where('name', 'staff-login')->delete();

    // Create Sanctum Token
    $token = $staff->createToken('staff-login')->plainTextToken;

    return response()->json([
      'status' => 'success',
      'user' => $staff,
      'token' => $token
    ], 200);
  }

  public function index()
  {
    $language = Language::where('is_default', 1)->first();
    $language_id = $language->id;

    $staffId = Auth::guard('sanctum_staff')->user()->id;
    $information['totalServices'] = StaffService::query()->where('staff_id', $staffId)->count();

    $information['totalAppointment'] = ServiceBooking::query()->where('staff_id', $staffId)->count();
    $information['totalPendingAppointment'] = ServiceBooking::query()
      ->where('order_status', 'pending')
      ->where('staff_id', $staffId)->count();
    $information['totalCompleteAppointment'] = ServiceBooking::query()
      ->where('order_status', 'rejected')
      ->where('staff_id', $staffId)->count();

    $information['totalRejectedAppointment'] = ServiceBooking::query()
      ->where('order_status', 'complete')
      ->where('staff_id', $staffId)->count();

    $information['recent_appointments'] = ServiceBooking::with(['serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->where('staff_id', $staffId)
      ->where('order_status', '=', 'pending')
      ->take(5)
      ->latest()
      ->get();

    return response()->json(['status' => 'success', 'data' => $information], 200);
  }

  //edit_profile
  public function edit_profile(Request $request)
  {
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $information['languages'] = Language::get();

    $staff_id = Auth::guard('sanctum_staff')->user()->id;

    $staff = Staff::with(['StaffContent.language'])->findOrFail($staff_id);
    $staff->image = asset('assets/img/staff/' . $staff->image);

    $information['staff'] = $staff;

    return response()->json(['status' => 'success', 'data' => $information], 200);
  }

  //update_profile
  public function update_profile(Request $request,)
  {
    $rules = [
      'phone' => 'required',
      'username' => [
        'required',
        'not_in:admin,vendor',
        Rule::unique('staff', 'username')->ignore(Auth::guard('sanctum_staff')->user()->id),
      ],
      'email' => [
        'required',
        'email',
        Rule::unique('staff', 'email')->ignore(Auth::guard('sanctum_staff')->user()->id)
      ]
    ];

    $defaultLanguage = Language::where('is_default', 1)->first();
    $rules[$defaultLanguage->code . '_name'] = 'required|max:255';


    $languages = Language::get();
    foreach ($languages as $language) {
      $code = $language->code;
      // Skip the default language as it's always required
      if ($language->id == $defaultLanguage->id) {
        continue;
      }
      if (
        $request->filled($code . '_city') ||
        $request->filled($code . '_country') ||
        $request->filled($code . '_zip_code') ||
        $request->filled($code . '_state') ||
        $request->filled($code . '_details') ||
        $request->filled($code . '_address')
      ) {
        $rules[$language->code . '_name'] = 'required';
      }
    }
    $validator = Validator::make($request->all(), $rules);

    if ($request->hasFile('image')) {
      $rules['image'] = 'mimes:png,jpeg,jpg|dimensions:min_width=80,max_width=80,min_width=80,min_height=80';
    }
    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }

    $staff = Staff::findOrFail(Auth::guard('sanctum_staff')->user()->id);
    $vendor_id = $staff->vendor_id;

    if ($request->hasFile('image')) {
      $staffImage = UploadFile::update(public_path('assets/img/staff/'), $request->file('image'), $staff->getRawOriginal('image'));
    }

    $staff->update([
      'username' =>  $request->username !== null ? $request->username : null,
      'vendor_id' => $vendor_id,
      'email' => $request->email,
      'phone' => $request->phone,
      'email_status' => $request->email_status ?? 0,
      'info_status' => $request->info_status ?? 0,
      'phone_status' => $request->phone_status ?? 0,
      'image' => $request->hasFile('image') ? $staffImage : $staff->getRawOriginal('image'),
    ]);

    $languages = Language::all();

    foreach ($languages as $language) {
      $code = $language->code;
      $staff_content = StaffContent::where('language_id', $language->id)->where('staff_id', $staff->id)->first();
      if (empty($staff_content)) {
        $staff_content = new StaffContent();
      }
      if (
        $language->is_default == 1 ||
        $request->filled($code . '_address') ||
        $request->filled($code . '_name') ||
        $request->filled($code . '_details')
      ) {
        $staff_content->language_id = $language->id;
        $staff_content->staff_id = $staff->id;
        $staff_content->name = $request[$language->code . '_name'];
        $staff_content->location = $request[$language->code . '_address'];
        $staff_content->information = $request[$language->code . '_details'];
        $staff_content->save();
      }
    }
    return response()->json(['status' => 'success', 'message' => __('Staff update successfully!')], 200);
  }

  public function change_password()
  {
    return response()->json(['status' => 'success'], 200);
  }
  //update_password
  public function updated_password(Request $request)
  {
    $rules = [
      'current_password' => [
        'required'
      ],
      'new_password' => 'required|confirmed',
      'new_password_confirmation' => 'required'
    ];

    $messages = [
      'new_password.confirmed' => __('Password confirmation does not match.')
    ];


    $staff = Auth::guard('sanctum_staff')->user();
        if (!Hash::check($request->current_password, $staff->password)) {
      return response()->json([
        'success' => false,
        'errors' => [
          'current_password' => [__('The current password is incorrect.')]
        ]
      ], 422);
    }

    $staff = Auth::guard('sanctum_staff')->user();

    $staff->update([
      'password' => Hash::make($request->new_password)
    ]);

    Session::flash('success', __('Password updated successfully!'));

    return response()->json(['status' => 'success', 'message' => __('Password updated successfully!')], 200);
  }

  public function changeTheme(Request $request)
  {
    // Session::put('staff_theme_version', $request->staff_theme_version);

    return response()->json(['status' => 'success', 'data' => $request->all()], 200);
  }

  public function logout(Request $request)
  {
    Auth::guard('sanctum_staff')->logout();
    Session::forget('secret_login');
    return response()->json(['status' => 'success'], 200);
  }

  public function languageChange($lang)
  {
    session()->put('staff_lang', 'admin_' . $lang);
    app()->setLocale('admin_' . $lang);
    return response()->json(['status' => 'success'], 200);
  }
}
