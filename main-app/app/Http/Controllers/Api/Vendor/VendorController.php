<?php

namespace App\Http\Controllers\Api\Vendor;

use Config;
use DateTime;
use Carbon\Carbon;
use App\Models\Vendor;
use App\Models\Package;
use App\Models\Language;
use App\Models\Membership;
use App\Models\VendorInfo;
use App\Models\Staff\Staff;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use App\Models\SupportTicket;
use App\Rules\MatchEmailRule;
use Illuminate\Validation\Rule;
use App\Models\Admin\Transaction;
use App\Models\Services\Services;
use App\Models\Staff\StaffContent;
use Illuminate\Support\Facades\DB;
use App\Models\BasicSettings\Basic;
use App\Rules\MatchOldPasswordRule;
use App\Http\Controllers\Controller;
use App\Models\Staff\StaffGlobalDay;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\BasicSettings\MailTemplate;
use App\Http\Controllers\FrontEnd\MiscellaneousController;

class VendorController extends Controller
{
  //signup
  public function signup(Request $request)
  {
    $misc = new MiscellaneousController();

    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $data['pageHeading'] = $misc->getPageHeading($language);

    $data['recaptchaInfo'] = Basic::select('google_recaptcha_status')->first();

    $data['bs'] = Basic::query()->select('google_recaptcha_status', 'facebook_login_status', 'google_login_status')->first();
    $data['bgImg'] = asset('assets/img/' . @$misc->getBreadcrumb()->breadcrumb);

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }

  //create
  public function create(Request $request)
  {
    $rules = [
      'username' => 'required|unique:vendors',
      'email' => 'required|email|unique:vendors',
      'password' => 'required|confirmed|min:6',
      'password_confirmation' => 'required'
    ];

    $info = Basic::select('google_recaptcha_status')->first();
    if ($info->google_recaptcha_status == 1) {
      $rules['g-recaptcha-response'] = 'required|captcha';
    }

    $messages = [];

    if ($info->google_recaptcha_status == 1) {
      $messages['g-recaptcha-response.required'] = 'Please verify that you are not a robot.';
      $messages['g-recaptcha-response.captcha'] = 'Captcha error! try again later or contact site admin.';
    }

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'errors' => $validator->errors()
      ], 422);
    }

    if ($request->username == 'admin') {
      return response()->json([
        'success' => false,
        'message' => __('You can not use admin as a username!')
      ], 422);
    }

    $in = $request->all();
    $setting = DB::table('basic_settings')->where('uniqid', 12345)->select('vendor_email_verification', 'vendor_admin_approval')->first();

    if ($setting->vendor_email_verification == 1) {
      // first, get the mail template information from db
      $mailTemplate = MailTemplate::where('mail_type', 'verify_email')->first();

      $mailSubject = $mailTemplate->mail_subject;
      $mailBody = $mailTemplate->mail_body;

      // second, send a password reset link to user via email
      $info = DB::table('basic_settings')
        ->select('website_title', 'smtp_status', 'smtp_host', 'smtp_port', 'encryption', 'smtp_username', 'smtp_password', 'from_mail', 'from_name')
        ->first();

      $token =  urlencode($request->email);

      $link = '<a href=' . url("vendor/email/verify?token=" . $token) . '>Click Here</a>';

      $mailBody = str_replace('{username}', $request->username, $mailBody);
      $mailBody = str_replace('{verification_link}', $link, $mailBody);
      $mailBody = str_replace('{website_title}', $info->website_title, $mailBody);

      $data = [
        'subject' => $mailSubject,
        'to' => $request->email,
        'body' => $mailBody,
      ];

      // if smtp status == 1, then set some value for PHPMailer
      if ($info->smtp_status == 1) {
        try {
          $smtp = [
            'transport' => 'smtp',
            'host' => $info->smtp_host,
            'port' => $info->smtp_port,
            'encryption' => $info->encryption,
            'username' => $info->smtp_username,
            'password' => $info->smtp_password,
            'timeout' => null,
            'auth_mode' => null,
          ];
          Config::set('mail.mailers.smtp', $smtp);
        } catch (\Exception $e) {
          Session::flash('error', $e->getMessage());
          return back();
        }

        // finally add other informations and send the mail
        try {
          if ($info->smtp_status == 1) {
            Mail::send([], [], function (Message $message) use ($data, $info) {
              $fromMail = $info->from_mail;
              $fromName = $info->from_name;
              $message->to($data['to'])
                ->subject($data['subject'])
                ->from($fromMail, $fromName)
                ->html($data['body'], 'text/html');
            });
          }

          return response()->json([
            'success' => true,
            'message' => __('A verification mail has been sent to your email address')
          ]);
        } catch (\Exception $e) {
          return response()->json([
            'success' => false,
            'message' => __('Failed to send verification email. Please try again later.')
          ], 500);
        }
      } else {
        $in['email_verified_at'] = now();
      }

      $in['status'] = 0;
    } else {
      return response()->json([
        'success' => true,
        'message' => __('Sign up successfully completed.Please Login Now')
      ], 422);
    }
    if ($setting->vendor_admin_approval == 1) {
      $in['status'] = 0;
    }

    if ($setting->vendor_admin_approval == 0 && $setting->vendor_email_verification == 0) {
      $in['status'] = 1;
    }

    if ($setting->vendor_email_verification == 0) {
      $in['email_verified_at'] = now();
    }

    $in['password'] = Hash::make($request->password);
    $in['recived_email'] = $request->email;
    $vendor = Vendor::create($in);

    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $in['language_id'] = $language->id;
    $in['vendor_id'] = $vendor->id;
    VendorInfo::create($in);

    //create global time schedule for this vendor
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    foreach ($days as $key => $day) {
      $staffday = new StaffGlobalDay();
      $staffday->day = $day;
      $staffday->vendor_id = $vendor->id;
      $staffday->indx = $key;
      $staffday->save();
    }

    //create a staff for this vendor
    $staff = Staff::create([
      'username' =>  $request->username,
      'password' =>  Hash::make($request->password),
      'vendor_id' => $vendor->id,
      'email' => $request->email,
      'status' => 1,
      'order_number' => 0,
      'allow_login' => 1,
      'role' => 'vendor'
    ]);

    $languages = Language::all();
    foreach ($languages as $language) {
      StaffContent::create([
        'language_id' => $language->id,
        'staff_id' => $staff->id,
        'name' => null,
        'location' => null,
        'information' => null,
      ]);
    }

    return response()->json([
      'success' => true,
      'message' => __('Sign up successfully completed. Please check your email for verification link.')
    ]);
  }


  //login
  public function login(Request $request)
  {
    $misc = new MiscellaneousController();

    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $data['pageHeading'] = $misc->getPageHeading($language);

    $data['bgImg'] = asset('assets/img/' . @$misc->getBreadcrumb()->breadcrumb);

    $data['bs'] = Basic::query()->select('google_recaptcha_status', 'facebook_login_status', 'google_login_status')->first();

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }

  public function authentication(Request $request)
  {
    $rules = [
      'username' => 'required',
      'password' => 'required',
    ];

    $info = Basic::select('google_recaptcha_status')->first();

    if ($info->google_recaptcha_status == 1) {
      $rules['g-recaptcha-response'] = 'required|captcha';
    }

    $messages = [];

    if ($info->google_recaptcha_status == 1) {
      $messages['g-recaptcha-response.required'] = 'Please verify that you are not a robot.';
      $messages['g-recaptcha-response.captcha'] = 'Captcha error! Try again later or contact site admin.';
    }

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'errors' => $validator->errors()
      ], 422);
    }

    // Try to find vendor by username
    $vendor = Vendor::where('username', $request->username)->first();

    if (!$vendor || !Hash::check($request->password, $vendor->password)) {
      return response()->json([
        'success' => false,
        'message' => __('Incorrect username or password')
      ], 422);
    }

    // Check email verification and status
    $setting = DB::table('basic_settings')->where('uniqid', 12345)->select('vendor_email_verification', 'vendor_admin_approval')->first();

    if ($setting->vendor_email_verification == 1 && !$vendor->email_verified_at && $vendor->status == 0) {
      return response()->json([
        'success' => false,
        'message' => __('Please verify your email address')
      ], 422);
    }

    // Clean old tokens
    $vendor->tokens()->where('name', 'vendor-login')->delete();

    // Create Sanctum Token
    $token = $vendor->createToken('vendor-login')->plainTextToken;

    return response()->json([
      'status' => 'success',
      'user' => $vendor,
      'token' => $token
    ], 200);
  }

  public function dashboard()
  {
    $vendor_id = Auth::guard('sanctum_vendor')->user()->id;
    $totalBalance = Vendor::where('id', $vendor_id)->pluck('amount')->first();

    $information['totalServices'] = Services::query()->where('vendor_id', $vendor_id)->count();
    $information['totalAppointment'] = ServiceBooking::query()->where('vendor_id', $vendor_id)->count();
    $information['totalPendingAppointment'] = ServiceBooking::query()
      ->where('order_status', 'pending')
      ->where('vendor_id', $vendor_id)->count();
    $information['totalCompleteAppointment'] = ServiceBooking::query()
      ->where('order_status', 'accepted')
      ->where('vendor_id', $vendor_id)->count();

    $information['totalRejectedAppointment'] = ServiceBooking::query()
      ->where('order_status', 'rejected')
      ->where('vendor_id', $vendor_id)->count();

    $information['admin_setting'] = DB::table('basic_settings')->where('uniqid', 12345)->select('vendor_admin_approval', 'admin_approval_notice')->first();

    $support_status = DB::table('support_ticket_statuses')->first();
    if ($support_status->support_ticket_status == 'active') {
      $total_support_tickets = SupportTicket::where([['user_id', Auth::guard('sanctum_vendor')->user()->id], ['user_type', 'vendor']])->get()->count();
      $information['total_support_tickets'] = $total_support_tickets;
    }
    $information['support_status'] = $support_status;
    $information['admin_setting'] = DB::table('basic_settings')->where('uniqid', 12345)->select('vendor_admin_approval', 'admin_approval_notice')->first();

    //total service posts
    $monthWiseTotalIncomes = DB::table('transactions')
      ->select(DB::raw('month(created_at) as month'), DB::raw('sum(actual_total) as total'))
      ->where('payment_status', 'completed')
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->whereIn('transaction_type', ['service_booking'])
      ->groupBy('month')
      ->whereYear('created_at', '=', date('Y'))
      ->get();

    $monthlyAppointment = DB::table('service_bookings')
      ->select(DB::raw('month(created_at) as month'), DB::raw('count(id) as total'))
      ->groupBy('month')
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->whereYear('created_at', '=', date('Y'))
      ->get();


    $months = [];
    $monthlyIncome = [];
    $monthlyAppoinment = [];


    //event icome calculation
    for ($i = 1; $i <= 12; $i++) {
      // get all 12 months name
      $monthNum = $i;
      $dateObj = DateTime::createFromFormat('!m', $monthNum);
      $monthName = $dateObj->format('M');
      array_push($months, $monthName);

      // get all 12 months's income
      $incomeFound = false;
      foreach ($monthWiseTotalIncomes as $appointment) {
        if ($appointment->month == $i) {
          $incomeFound = true;
          array_push($monthlyIncome, $appointment->total);
          break;
        }
      }
      if ($incomeFound == false) {
        array_push($monthlyIncome, 0);
      }

      // get all 12 months's income
      $apFound = false;
      foreach ($monthlyAppointment as $totalUser) {
        if ($totalUser->month == $i) {
          $apFound = true;
          array_push($monthlyAppoinment, $totalUser->total);
          break;
        }
      }
      if ($apFound == false) {
        array_push($monthlyAppoinment, 0);
      }
    }

    $payment_logs = Membership::where('vendor_id', $vendor_id)->get()->count();

    //package start
    $nextPackageCount = Membership::query()->where([
      ['vendor_id', Auth::guard('sanctum_vendor')->user()->id],
      ['expire_date', '>=', Carbon::now()->toDateString()]
    ])->whereYear('start_date', '<>', '9999')->where('status', '<>', 2)->count();
    //current package
    $information['current_membership'] = Membership::query()->where([
      ['vendor_id', Auth::guard('sanctum_vendor')->user()->id],
      ['start_date', '<=', Carbon::now()->toDateString()],
      ['expire_date', '>=', Carbon::now()->toDateString()]
    ])->where('status', 1)->whereYear('start_date', '<>', '9999')->first();

    if ($information['current_membership'] != null) {
      $countCurrMem = Membership::query()->where([
        ['vendor_id', Auth::guard('sanctum_vendor')->user()->id],
        ['start_date', '<=', Carbon::now()->toDateString()],
        ['expire_date', '>=', Carbon::now()->toDateString()]
      ])->where('status', 1)->whereYear('start_date', '<>', '9999')->count();
      if ($countCurrMem > 1) {
        $information['next_membership'] = Membership::query()->where([
          ['vendor_id', Auth::guard('sanctum_vendor')->user()->id],
          ['start_date', '<=', Carbon::now()->toDateString()],
          ['expire_date', '>=', Carbon::now()->toDateString()]
        ])->where('status', '<>', 2)->whereYear('start_date', '<>', '9999')->orderBy('id', 'DESC')->first();
      } else {
        $information['next_membership'] = Membership::query()->where([
          ['vendor_id', Auth::guard('sanctum_vendor')->user()->id],
          ['start_date', '>', $information['current_membership']->expire_date]
        ])->whereYear('start_date', '<>', '9999')->where('status', '<>', 2)->first();
      }
      $information['next_package'] = $information['next_membership'] ? Package::query()->where('id', $information['next_membership']->package_id)->first() : null;
    } else {
      $information['next_package'] = null;
    }
    $information['current_package'] = $information['current_membership'] ? Package::query()->where('id', $information['current_membership']->package_id)->first() : null;

    $information['package_count'] = $nextPackageCount;
    //package start end

    $information['monthArr'] = $months;
    $information['monthlyIncome'] = $monthlyIncome;
    $information['monthlyAppoinment'] = $monthlyAppoinment;
    $information['payment_logs'] = $payment_logs;
    $information['totalBalance'] = symbolPrice($totalBalance);

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }


  public function updated_password(Request $request)
  {
    $rules = [
      'current_password' => 'required',
      'new_password' => 'required|confirmed',
    ];

    $messages = [
      'new_password.confirmed' => __('Password confirmation does not match.')
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'errors' => $validator->errors()->toArray()
      ], 400);
    }

    $vendor = Auth::guard('sanctum_vendor')->user();

    // Check if current password is correct
    if (!Hash::check($request->current_password, $vendor->password)) {
      return response()->json([
        'success' => false,
        'errors' => [
          'current_password' => [__('The current password is incorrect.')]
        ]
      ], 422);
    }

    // Check if new password is same as current password
    if ($request->new_password === $request->current_password) {
      return response()->json([
        'success' => false,
        'errors' => [
          'new_password' => [__('New password cannot be the same as the current password.')]
        ]
      ], 422);
    }

    // Update the password
    $vendor->update([
      'password' => Hash::make($request->new_password)
    ]);

    return response()->json([
      'success' => true,
      'message' => __('Password updated successfully!')
    ]);
  }

  /**
   * Show the vendor profile for editing.
   */
  public function edit_profile()
  {
    $language = vendor_api_default_language();
    $id = Auth::guard('sanctum_vendor')->user()->id;

    $data['vendor'] = Vendor::where('id', $id)->first();

    if ($data['vendor']) {
      $data['vendor']->photo = asset('assets/admin/img/vendor-photo/' . $data['vendor']->photo);
    }

    $data['langauges'] = Language::all();

    $data['vendor_info'] = VendorInfo::where('vendor_id', $id)->where('language_id', $language->id)->get();

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }
  /**
   * Update the vendor profile.
   */
  public function update_profile(Request $request)
  {
    // dd($request->all());
    $id = Auth::guard('sanctum_vendor')->user()->id;
    $rules = [
      'username' => [
        'required',
        'not_in:admin',
        Rule::unique('vendors', 'username')->ignore($id),
      ],
      'email' => [
        'required',
        'email',
        Rule::unique('vendors', 'email')->ignore($id)
      ]
    ];

    if ($request->hasFile('photo')) {
      $rules['photo'] = 'mimes:png,jpeg,jpg|dimensions:min_width=80,max_width=80,min_width=80,min_height=80';
    }

    $defaultLanguage = Language::where('is_default', 1)->first();
    $rules[$defaultLanguage->code . '_name'] = 'required|max:255';

    $languages = Language::get();
    foreach ($languages as $language) {
      $code = $language->code;
      // Skip the default language as it's always required
      if ($language->id == $defaultLanguage->id) {
        continue;
      }
      // Check if any field for this language is filled
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

    $messages = [];

    foreach ($languages as $language) {
      $name = ' ' . $language->name . ' ' . __('language.');
      $messages[$language->code . '_name.required'] = __('The name field is required for') . $name;
    }

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }


    $in = $request->all();
    $vendor  = Vendor::where('id', $id)->first();
    $file = $request->file('photo');
    if ($file) {
      $extension = $file->getClientOriginalExtension();
      $directory = public_path('assets/admin/img/vendor-photo/');
      $fileName = uniqid() . '.' . $extension;
      @mkdir($directory, 0775, true);
      $file->move($directory, $fileName);

      @unlink(public_path('assets/admin/img/vendor-photo/') . $vendor->photo);
      $in['photo'] = $fileName;
    }

    $in['email'] = $request->email;
    if ($request->show_email_addresss) {
      $in['show_email_addresss'] = 1;
    } else {
      $in['show_email_addresss'] = 0;
    }
    if ($request->show_phone_number) {
      $in['show_phone_number'] = 1;
    } else {
      $in['show_phone_number'] = 0;
    }
    if ($request->show_contact_form) {
      $in['show_contact_form'] = 1;
    } else {
      $in['show_contact_form'] = 0;
    }

    $vendor->update($in);

    $languages = Language::get();
    $vendor_id = $vendor->id;
    foreach ($languages as $language) {
      $vendorInfo = VendorInfo::where('vendor_id', $vendor_id)->where('language_id', $language->id)->first();
      if ($vendorInfo == NULL) {
        $vendorInfo = new VendorInfo();
      }
      if (
        $language->is_default == 1 ||
        $request->filled($code . '_city') ||
        $request->filled($code . '_country') ||
        $request->filled($code . '_zip_code') ||
        $request->filled($code . '_state') ||
        $request->filled($code . '_details') ||
        $request->filled($code . '_address')
      ) {
        $vendorInfo->language_id = $language->id;
        $vendorInfo->vendor_id = $vendor_id;
        $vendorInfo->name = $request[$language->code . '_name'];
        $vendorInfo->country = $request[$language->code . '_country'];
        $vendorInfo->city = $request[$language->code . '_city'];
        $vendorInfo->state = $request[$language->code . '_state'];
        $vendorInfo->zip_code = $request[$language->code . '_zip_code'];
        $vendorInfo->address = $request[$language->code . '_address'];
        $vendorInfo->details = $request[$language->code . '_details'];
        $vendorInfo->save();
      }
    }
    return response()->json([
      'success' => true,
      'message' => __('Profile updated successfully!')
    ]);
  }

  public function logout(Request $request)
  {
    $request->user()->currentAccessToken()->delete();

    return response()->json([
      'status' => 'success',
      'message' => 'Logout successfully'
    ], 200);
  }


  public function subscription_log(Request $request)
  {
    $search = $request->search;
    $data['memberships'] = Membership::query()
      ->when($search, function ($query, $search) {
        $query->where('transaction_id', 'like', '%' . $search . '%');
      })
      ->where('vendor_id', Auth::guard('sanctum_vendor')->id())
      ->orderByDesc('id')
      ->select([
        'id',
        'price',
        'currency',
        'currency_symbol',
        'payment_method',
        'transaction_id',
        'status',
        'is_trial',
        'trial_days',
        'receipt',
        'package_id',
        'vendor_id',
        'start_date',
        'expire_date',
        'modified',
        'created_at',
        'updated_at',
        'conversation_id',
      ])
      ->paginate(10);

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }

  public function index(Request $request)
  {
    $transaction = $request->transaction_id;
    $data['transactions'] = Transaction::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->orderBy('id', 'desc')
      ->when(
        $transaction,
        function ($query, $transaction) {
          return $query->where('transaction_id', 'like', '%' . $transaction . '%');
        }
      )
      ->whereNotIn('transaction_type', ['membership_buy', 'featured_service'])
      ->get();

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }


  public function transcation(Request $request)
  {
    $transaction = $request->transaction_id;
    $data['transactions'] = Transaction::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->orderBy('id', 'desc')
      ->when(
        $transaction,
        function ($query, $transaction) {
          return $query->where('transaction_id', 'like', '%' . $transaction . '%');
        }
      )
      ->whereNotIn('transaction_type', ['membership_buy', 'featured_service'])
      ->paginate(10);

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }


  /**
   * Get basic settings data
   */
  public function getBasic()
  {
    $basicData = DB::table('basic_settings')
      ->select('primary_color', 'secondary_color', 'mobile_app_logo', 'mobile_favicon', 'base_currency_text', 'base_currency_rate')
      ->first();

    $basicData->mobile_app_logo = asset('assets/img/mobile-interface/' . $basicData->mobile_app_logo);
    $basicData->mobile_favicon = asset('assets/img/mobile-interface/' . $basicData->mobile_favicon);

    $data['basic_data'] = $basicData;
    $data['languages'] = Language::all();

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }
}
