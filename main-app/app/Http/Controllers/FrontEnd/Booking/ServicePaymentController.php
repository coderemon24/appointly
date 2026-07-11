<?php

namespace App\Http\Controllers\FrontEnd\Booking;

use PDF;
use Auth;
use Validator;
use Carbon\Carbon;
use App\Models\Vendor;
use App\Models\Language;
use App\Models\Membership;
use App\Models\Staff\Staff;
use Illuminate\Http\Request;
use App\Rules\ImageMimeTypeRule;
use App\Http\Helpers\BasicMailer;
use Illuminate\Support\Facades\DB;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Staff\StaffGlobalHour;
use App\Http\Helpers\CheckLimitHelper;
use App\Models\Staff\StaffServiceHour;
use App\Models\Services\ServiceBooking;
use App\Models\Services\ServiceContent;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Response;
use App\Models\BasicSettings\MailTemplate;
use App\Models\PaymentGateway\OfflineGateway;
use App\Http\Controllers\Admin\Transaction\TransactionController;
use App\Http\Controllers\FrontEnd\Booking\Payment\YocoController;
use App\Http\Controllers\FrontEnd\Booking\Payment\PaytmController;
use App\Http\Controllers\FrontEnd\Booking\Payment\IyzicoController;
use App\Http\Controllers\FrontEnd\Booking\Payment\MollieController;
use App\Http\Controllers\FrontEnd\Booking\Payment\PayPalController;
use App\Http\Controllers\FrontEnd\Booking\Payment\StripeController;
use App\Http\Controllers\FrontEnd\Booking\Payment\XenditController;
use App\Http\Controllers\FrontEnd\Booking\Payment\OfflineController;
use App\Http\Controllers\FrontEnd\Booking\Payment\PaytabsController;
use App\Http\Controllers\FrontEnd\Booking\Payment\PhonePeController;
use App\Http\Controllers\FrontEnd\Booking\Payment\MidtransController;
use App\Http\Controllers\FrontEnd\Booking\Payment\PaystackController;
use App\Http\Controllers\FrontEnd\Booking\Payment\RazorpayController;
use App\Http\Controllers\FrontEnd\Booking\Payment\InstamojoController;
use App\Http\Controllers\FrontEnd\Booking\Payment\ToyyibpayController;
use App\Http\Controllers\FrontEnd\Booking\Payment\MyFatoorahController;
use App\Http\Controllers\FrontEnd\Booking\Payment\FlutterwaveController;
use App\Http\Controllers\FrontEnd\Booking\Payment\MercadoPagoController;
use App\Http\Controllers\FrontEnd\Booking\Payment\AuthorizenetController;
use App\Http\Controllers\FrontEnd\Booking\Payment\PerfectMoneyController;

class ServicePaymentController extends Controller
{
  public function index(Request $request)
  {
    if (Session::has('serviceData')) {
      $serviceData = Session::get('serviceData');
    } else {
      return Response::json(['error' => __('Something went wrong')], 422);
    }


    //check membership expire date
    if ($serviceData['vendor_id'] != 0) {
      $expireDate = checkMembersipExpireDate($serviceData['vendor_id']);
      if ($request['bookingDate'] > $expireDate) {
        return Response::json(['error' => __('Something went wrong')], 422);
      }
    }

    $countAppointment = CheckLimitHelper::countAppointment($serviceData['vendor_id']);

    if ($countAppointment <= 0) {
      return Response::json(['error' => __('Please Contact Support')], 422);
    }

    //get staff time slot
    $staff = Staff::find($request['staffId']);
    if ($staff->is_day == 1) {
      $staffHour = StaffServiceHour::find($request['serviceHourId']);
    } else {
      $staffHour = StaffGlobalHour::find($request['serviceHourId']);
    }
    //gateway validation
    $rules = ['gateway' => 'required'];
    if ($request['gateway'] == 'iyzico') {
      $rules['identity_number'] = 'required';
    }
    if ($request['gateway'] == 'stripe') {
      $rules['stripeToken'] = 'required';
    }
    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }
    $currencyInfo = $this->getCurrencyInfo();

    //data for service booking
    $arrData = array(
      'zoom_status' => $serviceData['zoom_status'],
      'calender_status' => $serviceData['calendar_status'],
      'customer_name' => $request['name'],
      'customer_phone' => $request['phone'],
      'customer_email' => $request['email'],
      'customer_address' => $request['address'],
      'customer_zip_code' => $request['zip_code'],
      'customer_country' => $request['country'],
      'start_date' => $staffHour->start_time,
      'end_date' => $staffHour->end_time,
      'booking_date' => $request['bookingDate'],
      'service_hour_id' => $request['serviceHourId'],
      'staff_id' => $request['staffId'],
      'max_person' => $request['max_person'],
      'service_id' => $serviceData['service_id'],
      'user_id' => $request['user_id'],
      'vendor_id' => $serviceData['vendor_id'],
      'customer_paid' => $serviceData['service_ammount'],
      'currencyText' => $currencyInfo->base_currency_text,
      'currencyTextPosition' => $currencyInfo->base_currency_text_position,
      'currencySymbol' => $currencyInfo->base_currency_symbol,
      'currencySymbolPosition' => $currencyInfo->base_currency_symbol_position,
      'paymentMethod' => ucfirst($request['gateway']),
      'gatewayType' => 'online',
      'paymentStatus' => 'completed',
      'refund' => 'pending',
      'identity_number' => $request['gateway'] == 'iyzico' ? $request['identity_number'] : ''
    );
    $arrData['identity_number'] = $request->identity_number;
    $arrData['paymentStatus'] = 'pending';

    //redirect to gateway
    $cancel_url = route('frontend.service_booking.cancel');
    $amount = $serviceData['service_ammount'];
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
    return $controller->index($arrData, 'service booking', $cancel_url, $amount);
  }

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
      'attachment' => array_key_exists('attachment', $arrData) ? $arrData['attachment'] : null,
      'zoom_info' => session()->has('zoom_info') ? json_encode(session()->get('zoom_info')) : null,
      'conversation_id' => @$arrData['conversation_id']
    ]);

    //create transaction for this payment
    if ($arrData['gatewayType'] != 'offline') {
      $transaction = new TransactionController();
      $transaction->storeTransaction($arrData);
    }
    if ($arrData['vendor_id'] != 0) {
      $vendor = Vendor::findOrFail($arrData['vendor_id']);
      $lessAppointmentNum = intval($vendor->total_appointment) - 1;
      //update less appoitnment number
      $vendor->update([
        'total_appointment' => $lessAppointmentNum,
      ]);
    }

    session::forget('zoom_info');
    return $orderInfo;
  }

  public function generateInvoice($orderInfo)
  {
    $fileName = $orderInfo->order_number . '.pdf';

    $data['orderInfo'] = $orderInfo;

    $directory = public_path('assets/file/invoices/service/');
    @mkdir($directory, 0775, true);

    $fileLocated = $directory . $fileName;

    PDF::loadView('frontend.services.invoice', $data)->save($fileLocated);

    return $fileName;
  }

  public function prepareMail($orderInfo)
  {
    // get the mail template info from db
    $mailTemplate = MailTemplate::query()->where('mail_type', '=', 'service_booking_accepted')->first();
    $mailData['subject'] = $mailTemplate->mail_subject;
    $mailBody = $mailTemplate->mail_body;

    $appointment = ServiceBooking::where([
      'service_id' => $orderInfo['service_id'],
      'booking_date' => $orderInfo['booking_date'],
      'service_hour_id' => $orderInfo['service_hour_id']
    ])->select('zoom_info')->first();

    if ($appointment->zoom_info != null) {
      // Decode JSON data into an associative array
      $zoomLink = json_decode($appointment->zoom_info, true);
      $joinUrl = $zoomLink['join_url'];
      $joinPwd = $zoomLink['password'];
    }
    if ($appointment->zoom_info != null) {
      $joinurl = '<p>Zoom Join link: ' . $joinUrl . '</p>';
      $joinPassword = '<p>Zoom Join Password: ' . $joinPwd . '</p>';
    } else {
      $joinurl = '';
      $joinPassword = '';
    }

    if (Auth::guard('web')->check() == true) {
      $orderLink = '<p>Appointment Details: <a href=' . url("user/appointment/details/" . $orderInfo->id) . '>Click Here</a></p>';
    } else {
      $orderLink = '';
    }

    $language = Language::where('is_default', 1)->first();
    $serviceInfo = ServiceContent::query()
      ->where('service_id', $orderInfo->service_id)
      ->where('language_id', $language->id)
      ->select('name', 'slug')
      ->firstOrFail();

    $url = route('frontend.service.details', ['slug' => $serviceInfo->slug, 'id' => $orderInfo->service_id]);
    $serviceName = truncateString($serviceInfo->name, 50);

    // get the website title info from db
    $info = Basic::select('website_title')->first();
    $appointmentTime = $orderInfo->start_date . ' to ' . $orderInfo->end_date;

    // replacing with actual data
    $mailBody = str_replace('{booking_number}', $orderInfo->order_number, $mailBody);
    $mailBody = str_replace('{service_title}', "<a href=" . $url . ">$serviceName</a>", $mailBody);
    $mailBody = str_replace('{order_link}', $orderLink, $mailBody);
    $mailBody = str_replace('{zoom_link}', $joinurl, $mailBody);
    $mailBody = str_replace('{zoom_password}', $joinPassword, $mailBody);
    $mailBody = str_replace('{customer_name}', $orderInfo->customer_name, $mailBody);
    $mailBody = str_replace('{booking_date}', date_format($orderInfo->created_at, 'M d, Y'), $mailBody);
    $mailBody = str_replace('{appointment_date}', Carbon::parse($orderInfo->booking_date)->format('M d, Y'), $mailBody);
    $mailBody = str_replace('{appointment_time}', $appointmentTime, $mailBody);
    $mailBody = str_replace('{website_title}', $info->website_title, $mailBody);


    $mailData['body'] = $mailBody;
    $mailData['recipient'] = $orderInfo->customer_email;
    $mailData['invoice'] = public_path('assets/file/invoices/service/') . $orderInfo->invoice;
    BasicMailer::sendMail($mailData);
    return;
  }

  public function cancel()
  {
    $notification = array('message' => 'Something went wrong', 'alert-type' => 'error');
    return redirect()->route('frontend.services')->with($notification);
  }
}
