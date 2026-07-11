<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Vendor;
use GuzzleHttp\Client;
use App\Models\Membership;
use App\Models\Staff\Staff;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Helpers\UploadFile;
use App\Models\Services\Services;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\Staff\StaffGlobalHour;
use App\Http\Helpers\CheckLimitHelper;
use App\Models\Staff\StaffServiceHour;
use Illuminate\Support\Facades\Config;
use App\Models\Services\ServiceBooking;
use App\Models\Services\ServiceContent;
use Illuminate\Support\Facades\Validator;
use App\Models\VendorPlugins\VendorPlugin;
use App\Http\Controllers\WhatsAppController;
use App\Http\Helpers\VendorPermissionHelper;
use App\Http\Controllers\Admin\Transaction\TransactionController;

class BookingController extends Controller
{
  /**
   * Verify payment process.
   *
   * - Check membership expiry date
   * - Check vendor booking limit
   * - Convert amount based on gateway & currency
   */

  public function verfiyPayment(Request $request)
  {
    $rules = [
      'amount' => 'required',
      'gateway' => 'required',
      'vendor_id' => 'required',
      'booking_date' => 'required',
    ];

    $validator = Validator::make(request()->all(), $rules);
    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $amount = $request['amount'];
    $gateway = $request['gateway'];
    $vendor_id = $request['vendor_id'];
    $booking_date = $request['booking_date'];


    //check membership expire date
    if ($vendor_id != 0) {
      $expireDate = checkMembersipExpireDate($vendor_id);
      if ($booking_date > $expireDate) {
        return response()->json([
          'status' => 'error',
          'message' => __('Something went wrong')
        ]);
      }
    }
    //check vendor booking limit
    $countAppointment = CheckLimitHelper::countAppointment($vendor_id);
    if ($countAppointment == 0) {
      return response()->json([
        'status' => 'error',
        'message' => __('Please contact on support')
      ]);
    }

    //convert payment amount
    $currencyInfo  = Basic::select(
      'base_currency_symbol',
      'base_currency_symbol_position',
      'base_currency_text',
      'base_currency_text_position',
      'base_currency_rate'
    )
      ->first();
    $gateway = strtolower($gateway);

    switch ($gateway) {
      case 'paypal':
        if ($currencyInfo->base_currency_text !== 'USD') {
          $rate = floatval($currencyInfo->base_currency_rate);
          $convertedTotal = $amount / $rate;
        }
        $paidAmount = $currencyInfo->base_currency_text === 'USD' ? $amount : $convertedTotal;
        break;
      case 'paystack':
        if ($currencyInfo->base_currency_text !== 'NGN') {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for paystack payment.'], 422);
        }
        $paidAmount = $amount * 100;
        break;
      case 'flutterwave':
        $allowedCurrencies = array('BIF', 'CAD', 'CDF', 'CVE', 'EUR', 'GBP', 'GHS', 'GMD', 'GNF', 'KES', 'LRD', 'MWK', 'MZN', 'NGN', 'RWF', 'SLL', 'STD', 'TZS', 'UGX', 'USD', 'XAF', 'XOF', 'ZMK', 'ZMW', 'ZWD');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for flutterwave payment.'], 422);
        }
        $paidAmount = intval($amount);
        break;
      case 'razorpay':
        if ($currencyInfo->base_currency_text !== 'INR') {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for razorpay payment.'], 422);
        }
        $paidAmount = $amount * 100;
        break;
      case 'mercadopago':
        $allowedCurrencies = array('ARS', 'BOB', 'BRL', 'CLF', 'CLP', 'COP', 'CRC', 'CUC', 'CUP', 'DOP', 'EUR', 'GTQ', 'HNL', 'MXN', 'NIO', 'PAB', 'PEN', 'PYG', 'USD', 'UYU', 'VEF', 'VES');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for mercadopago payment.'], 422);
        }
        $paidAmount = intval($amount);
        break;
      case 'mollie':
        $allowedCurrencies = array('AED', 'AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HRK', 'HUF', 'ILS', 'ISK', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'USD', 'ZAR');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for mollie payment.'], 422);
        }
        $paidAmount = sprintf('%0.2f', $amount);
        break;
      case 'stripe':
        if ($currencyInfo->base_currency_text !== 'USD') {
          $rate = floatval($currencyInfo->base_currency_rate);
          $convertedTotal = round(($amount / $rate), 2);
        }

        $paidAmount = $currencyInfo->base_currency_text === 'USD' ? $amount : $convertedTotal;
        break;
      case 'authorize.net':
        $allowedCurrencies = array('USD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 'NOK', 'PLN', 'SEK', 'AUD', 'NZD');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for authorize.net payment.'], 422);
        }
        $paidAmount = sprintf('%0.2f', $amount);
        break;
      case 'phonepe':
        $allowedCurrencies = array('INR');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for phonepe payment.'], 422);
        }
        $paidAmount = $amount * 100;
        break;
      case 'myfatoorah':
        $allowedCurrencies = array('KWD', 'SAR', 'BHD', 'AED', 'QAR', 'OMR', 'JOD');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for myfatoorah payment.'], 422);
        }
        $paidAmount = intval($amount);
        break;
      case 'midtrans':
        $allowedCurrencies =  array('IDR');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for midtrans payment.'], 422);
        }
        $paidAmount = (int)round($amount);
        break;
      case 'toyyibpay':
        $allowedCurrencies =  array('RM');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for toyyibpay payment.'], 422);
        }
        $paidAmount = $amount * 100;
        break;
      case 'xendit':
        $allowedCurrencies =  array('IDR', 'PHP', 'USD', 'SGD', 'MYR');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for xendit payment.'], 422);
        }
        $paidAmount = $amount;
        break;
      case 'monnify':
        $allowedCurrencies =  array('NGN');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for monnify payment.'], 422);
        }
        $paidAmount = $amount;
        break;
      case 'now_payments':
        $allowedCurrencies =  array('USD', 'EUR', 'GBP', 'USDT', 'BTC', 'ETH');
        if (!in_array($currencyInfo->base_currency_text, $allowedCurrencies)) {
          return response()->json(['status' => 'error', 'message' => 'Invalid currency for now_payments payment.'], 422);
        }
        $paidAmount = $amount;
        break;
      default:
        $paidAmount = intval($amount);
        break;
    }

    return $paidAmount;
  }

  public function paymentProcess(Request $request)
  {
    try {
      $service_id = $request['service_id'];
      $service = Services::where('id', $service_id)
        ->select('zoom_meeting', 'calendar_status', 'vendor_id')->first();
      $vendor_id = $service->vendor_id;

      //check membership expire date
      if ($vendor_id != 0) {
        $expireDate = checkMembersipExpireDate($vendor_id);
        if ($request['booking_date'] > $expireDate) {
          return response()->json([
            'status' => 'error',
            'message' => __('Something went wrong')
          ]);
        }
      }
      //check vendor booking limit
      $countAppointment = CheckLimitHelper::countAppointment($vendor_id);
      if ($countAppointment == 0) {
        return response()->json([
          'status' => 'error',
          'message' => __('Please contact on support')
        ]);
      }

      $customerPaid = $request['amount'];
      $staffIdDay = Staff::where('id', $request['staff_id'])->value('is_day');
      $model = $staffIdDay == 1 ? StaffServiceHour::class : StaffGlobalHour::class;
      $staffHour = $model::where('id', $request['service_hour_id'])
        ->select('start_time', 'end_time')
        ->first();

      $currencyInfo  = Basic::select(
        'base_currency_symbol',
        'base_currency_symbol_position',
        'base_currency_text',
        'base_currency_text_position',
        'base_currency_rate'
      )
        ->first();

      //if gateway is offline store attachment in local storage
      if ($request['gateway_type'] == 'offline') {
        $directory = public_path('assets/file/attachments/service/');
        if ($request->hasFile('attachment')) {
          $attachmentName = UploadFile::store($directory, $request->file('attachment'));
        } else {
          $attachmentName = null;
        }
      }

      $arrData = [
        'zoom_status' => $service->zoom_meeting,
        'calender_status' => $service->calendar_status,
        'customer_name' => $request['name'],
        'customer_phone' => $request['phone'],
        'customer_email' => $request['email'],
        'customer_address' => $request['address'],
        'customer_zip_code' => $request['zip_code'],
        'customer_country' => $request['country'],
        'start_date' => $staffHour->start_time,
        'end_date' => $staffHour->end_time,
        'booking_date' => $request['booking_date'],
        'service_hour_id' => $request['service_hour_id'],
        'staff_id' => $request['staff_id'],
        'max_person' => $request['max_person'],
        'service_id' => $service_id,
        'user_id' => $request['user_id'],
        'vendor_id' => $service->vendor_id,
        'customer_paid' => $customerPaid,
        'currencyText' => $currencyInfo->base_currency_text,
        'currencyTextPosition' => $currencyInfo->base_currency_text_position,
        'currencySymbol' => $currencyInfo->base_currency_symbol,
        'currencySymbolPosition' => $currencyInfo->base_currency_symbol_position,
        'paymentMethod' => $request['payment_method'],
        'gatewayType' => Str::lower($request['gateway_type']),
        'paymentStatus' => Str::lower($request['payment_status']),
        'refund' => 'pending',
        'attachment' => @$attachmentName,
        'fcm_token' => $request['fcm_token'],
      ];

      $zoom_info = $this->createZoomMeeting($arrData);
      calendarEventCreate($arrData);
      $arrData['zoom_info'] = @$zoom_info;

      $bookingInfo = $this->storeData($arrData); //store booking data

      //send whatsapp sms
      WhatsAppController::sendMessage($bookingInfo->id, "customer_booking_confirmation", "new_booking");


      $firebase_admin_json = DB::table('basic_settings')
        ->where('uniqid', 12345)
        ->value('firebase_admin_json');
      //send notification for app
      if (!is_null($firebase_admin_json) && $bookingInfo->fcm_token != null) {
        $title = __('Appointment Request Received');
        $subtitle = __('Your appointment has been placed successfully.');
        FirebaseService::send($bookingInfo->fcm_token, $firebase_admin_json, $bookingInfo->id, $title, $subtitle);
      }
      return response()->json(['status' => 'success', 'message' => 'Booking successfully completed.', 'data' => $bookingInfo->order_number]);
    } catch (\Throwable $e) {
      return response()->json([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
      ], 500);
    }
  }

  //store booking data
  public function storeData($arrData)
  {
    $autoApprove = $orderStatus = null;
    if ($arrData['vendor_id'] != 0) {
      $autoApprove = Vendor::where('id', $arrData['vendor_id'])->pluck('booking_type')->first();
      $currentPackage = Membership::query()->where([
        ['vendor_id', '=', $arrData['vendor_id']],
        ['status', '=', 1],
        ['start_date', '<=', Carbon::now()->format('Y-m-d')],
        ['expire_date', '>=', Carbon::now()->format('Y-m-d')]
      ])->pluck('id')->first();
    } else {
      $autoApprove = DB::table('basic_settings')->pluck('booking_type')->first();
    }
    if ($arrData['gatewayType'] != "offline" && $autoApprove == "active") {
      $orderStatus = "accepted";
    } else {
      $orderStatus = "pending";
    }

    $orderInfo = ServiceBooking::create([
      'order_number' => uniqid(),
      'membership_id' => $arrData['vendor_id'] != 0 ? ($currentPackage ?? null) : null,
      'customer_name' => $arrData['customer_name'],
      'customer_phone' => $arrData['customer_phone'],
      'customer_email' => $arrData['customer_email'],
      'customer_address' => $arrData['customer_address'],
      'customer_zip_code' => $arrData['customer_zip_code'],
      'customer_country' => $arrData['customer_country'],
      'start_date' => $arrData['start_date'],
      'end_date' => $arrData['end_date'],
      'booking_date' => $arrData['booking_date'],
      'staff_id' => $arrData['staff_id'],
      'service_id' => $arrData['service_id'],
      'max_person' => $arrData['max_person'] != null ? $arrData['max_person'] : 1,
      'user_id' => $arrData['user_id'],
      'vendor_id' => $arrData['vendor_id'],
      'service_hour_id' => $arrData['service_hour_id'],
      'customer_paid' => $arrData['customer_paid'],
      'currency_text' => $arrData['currencyText'],
      'currency_text_position' => $arrData['currencyTextPosition'],
      'currency_symbol' => $arrData['currencySymbol'],
      'currency_symbol_position' => $arrData['currencySymbolPosition'],
      'payment_method' => $arrData['paymentMethod'],
      'gateway_type' => $arrData['gatewayType'],
      'payment_status' => $arrData['paymentStatus'],
      'order_status' => $orderStatus,
      'refund' => $arrData['refund'],
      'attachment' => array_key_exists('attachment', $arrData) ? $arrData['attachment'] : NULL,
      'zoom_info' => isset($arrData['zoom_info']) ? json_encode($arrData['zoom_info']) : NULL,
      'fcm_token' => $arrData['fcm_token'],
    ]);

    //create transaction for this payment
    if ($arrData['gatewayType'] != 'offline') {
      $transaction = new TransactionController();
      $transaction->storeTransaction($arrData);
    }
    if ($arrData['vendor_id'] != 0) {
      $vendor = Vendor::find($arrData['vendor_id']);
      $lessAppointmentNum = intval($vendor->total_appointment) - 1;
      //update less appoitnment number
      $vendor->update([
        'total_appointment' => $lessAppointmentNum,
      ]);
    }
    return $orderInfo;
  }

  /**
   * zoom meeting create
   */
  public function createZoomMeeting($bookInfo)
  {
    if ($bookInfo['zoom_status'] == 1) {
      $permission = $bookInfo['vendor_id'] != 0 ? VendorPermissionHelper::packagePermission($bookInfo['vendor_id']) : null;

      if (!$permission || $permission->zoom_meeting_status == 1) {
        if ($bookInfo['vendor_id'] != 0) {
          $plugin = VendorPlugin::where('vendor_id', $bookInfo['vendor_id'])->select(
            'zoom_account_id',
            'zoom_client_id',
            'zoom_client_secret'
          )->first();
        } else {
          $plugin = DB::table('basic_settings')->select('zoom_account_id', 'zoom_client_id', 'zoom_client_secret')->first();
        }

        $zoomCredential = [
          'account_id' => $plugin->zoom_account_id,
          'client_id' => $plugin->zoom_client_id,
          'client_secret' => $plugin->zoom_client_secret,
        ];
        Config::set('services.zoom', $zoomCredential);

        // Convert strings to Carbon instances
        $start_time = $bookInfo['start_date'];
        $end_time = $bookInfo['end_date'];

        // Format date for Zoom API (ISO 8601 format)
        $date = $bookInfo['booking_date'];
        $date = Carbon::parse($date);
        $startTime = Carbon::parse($start_time);
        $date->setTime($startTime->hour, $startTime->minute, 0);

        $formatStartTime = $date->format('Y-m-d\TH:i:s.u\Z');

        $timeFormat = DB::table('basic_settings')->pluck('time_format')->first();

        if ($timeFormat == 12) {
          $time1 = Carbon::createFromFormat('h:i A', $start_time);
          $time2 = Carbon::createFromFormat('h:i A', $end_time);
        } else {
          $time1 = Carbon::createFromFormat('H:i', $start_time);
          $time2 = Carbon::createFromFormat('H:i', $end_time);
        }


        // find duration from request time
        $duration = $time2->diffInMinutes($time1);

        $token = $this->getZoomAccessToken();
        $service_id = $bookInfo['service_id'];
        $serviceContent = ServiceContent::where('service_id', $service_id)->select('name')->first();
        $topicName = truncateString($serviceContent->name, 50);

        // Make a POST request to the Zoom API to create a meeting
        $response = Http::withToken($token)->post('https://api.zoom.us/v2/users/me/meetings', [
          'topic' => $topicName,
          'start_time' => $formatStartTime,
          'duration' => $duration,
          'type' => 2,
          'timezone' => 'UTC',
          'password' => Str::random(8)
        ]);
        return $response->json();
      }
    }
  }

  public function getZoomAccessToken()
  {
    $client = new Client();
    $clientId = config('services.zoom.client_id');
    $clientSecret = config('services.zoom.client_secret');
    $accountId = config('services.zoom.account_id');

    $response = $client->request('POST', 'https://zoom.us/oauth/token', [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept' => 'application/json',
      ],
      'form_params' => [
        'grant_type' => 'account_credentials',
        'account_id' => $accountId,
      ],
    ]);

    $token = json_decode($response->getBody(), true);
    return $token['access_token'];
  }
}
