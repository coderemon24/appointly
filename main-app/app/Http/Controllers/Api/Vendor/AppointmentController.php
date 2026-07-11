<?php

namespace App\Http\Controllers\Api\Vendor;

use Auth;
use Response;
use App\Models\Vendor;
use App\Models\Language;
use App\Models\Staff\Staff;
use Illuminate\Http\Request;
use App\Http\Helpers\BasicMailer;
use Illuminate\Support\Facades\DB;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use Illuminate\Support\Facades\Validator;
use App\Models\BasicSettings\MailTemplate;
use App\Http\Controllers\FrontEnd\Booking\ServicePaymentController;

class AppointmentController extends Controller
{
  public function setting()
  {
    $info['info'] = Vendor::where('id', Auth::guard('sanctum_vendor')->user()->id)
      ->select('booking_type')->first();

    return response()->json([
      'success' => true,
      'data' => $info,
    ]);
  }
  public function updatesetting(Request $request)
  {
    $rules = [
      'booking_type' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'errors' => $validator->errors(),
      ], 422);
    }
    DB::table('vendors')->updateOrInsert(
      ['id' => Auth::guard('sanctum_vendor')->user()->id],
      ['booking_type' => $request->booking_type]
    );

    return response()->json([
      'success' => true,
      'message' => __('Booking type updated successfully!'),
    ]);
  }

  public function index(Request $request)
  {
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();


    $language_id = $language->id;
    $information['langs'] = Language::all();

    $orderNumber = $paymentStatus = $orderStatus = $refundStatus = null;
    if ($request->filled('order_no')) {
      $orderNumber = $request['order_no'];
    }
    if ($request->filled('payment_status')) {
      $paymentStatus = $request['payment_status'];
    }
    if ($request->filled('order_status')) {
      $orderStatus = $request['order_status'];
    }
    if ($request->filled('refund')) {
      $refundStatus = $request['refund'];
    }
    $information['currencyInfo'] = $this->getCurrencyInfo();

    $information['booking_item'] = ServiceBooking::with(['vendorInfo' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }, 'serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->when($orderNumber, function ($query, $orderNumber) {
        return $query->where('order_number', 'like', '%' . $orderNumber . '%');
      })
      ->when($paymentStatus, function ($query, $paymentStatus) {
        return $query->where('payment_status', '=', $paymentStatus);
      })
      ->when($orderStatus, function ($query, $orderStatus) {
        return $query->where('order_status', '=', $orderStatus);
      })
      ->when($refundStatus, function ($query, $refundStatus) {
        return $query->where('refund', '=', $refundStatus);
      })
      ->orderByDesc('id')
      ->paginate(10);


    return response()->json([
      'success' => true,
      'data' => $information,
    ]);
  }


  public function pendingAppointment(Request $request)
  {
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $language_id = $language->id;
    $information['langs'] = Language::all();

    $paymentStatus = $refundStatus = null;
    if ($request->filled('payment_status')) {
      $paymentStatus = $request['payment_status'];
    }
    if ($request->filled('refund')) {
      $refundStatus = $request['refund'];
    }

    $information['booking_item'] = ServiceBooking::with(['vendorInfo' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }, 'serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->when($paymentStatus, function ($query, $paymentStatus) {
        return $query->where('payment_status', '=', $paymentStatus);
      })
      ->when($refundStatus, function ($query, $refundStatus) {
        return $query->where('refund', '=', $refundStatus);
      })
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->where('order_status', 'pending')
      ->orderByDesc('id')
      ->paginate(10);

    return response()->json([
      'success' => true,
      'data' => $information,
    ]);
  }

  public function acceptedAppointment(Request $request)
  {
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $language_id = $language->id;
    $information['langs'] = Language::all();

    $paymentStatus = $refundStatus = null;
    if ($request->filled('payment_status')) {
      $paymentStatus = $request['payment_status'];
    }
    if ($request->filled('refund')) {
      $refundStatus = $request['refund'];
    }

    $information['booking_item'] = ServiceBooking::with(['vendorInfo' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }, 'serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->when($paymentStatus, function ($query, $paymentStatus) {
        return $query->where('payment_status', '=', $paymentStatus);
      })
      ->when($refundStatus, function ($query, $refundStatus) {
        return $query->where('refund', '=', $refundStatus);
      })
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->where('order_status', 'accepted')->orderByDesc('id')
      ->paginate(10);

    return response()->json([
      'success' => true,
      'data' => $information,
    ]);
  }

  public function rejectedAppointment(Request $request)
  {

    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $language_id = $language->id;
    $information['langs'] = Language::all();

    $refundStatus = $paymentStatus = null;
    if ($request->filled('refund')) {
      $refundStatus = $request['refund'];
    }
    if ($request->filled('payment_status')) {
      $paymentStatus = $request['payment_status'];
    }

    $information['booking_item'] = ServiceBooking::with(['vendorInfo' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }, 'serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->when($paymentStatus, function ($query, $paymentStatus) {
        return $query->where('payment_status', '=', $paymentStatus);
      })
      ->when($refundStatus, function ($query, $refundStatus) {
        return $query->where('refund', '=', $refundStatus);
      })
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->where('order_status', 'rejected')->orderByDesc('id')
      ->paginate(10);

    return response()->json([
      'success' => true,
      'data' => $information,
    ]);
  }

  public function show($id, Request $request)
  {
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();


    $language_id = $language->id;

    $appointment = ServiceBooking::with(['serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->find($id);

    if (!$appointment) {
      return response()->json([
        'success' => false,
        'message' => __('Appointment not found.'),
      ], 404);
    }

    $information['details'] = $appointment;

    $information['vendor_details'] = Vendor::join('vendor_infos', 'vendor_infos.vendor_id', '=', 'vendors.id')
      ->where('vendors.id', $appointment->vendor_id)
      ->first();

    $information['staff'] = Staff::join('staff_contents', 'staff_contents.staff_id', '=', 'staff.id')
      ->where('staff.id', $appointment->staff_id)
      ->select('staff.id', 'staff.email', 'staff.phone', 'staff_contents.location', 'staff_contents.information', 'staff_contents.name')
      ->first();

    return response()->json([
      'success' => true,
      'data' => $information,
    ], 200);
  }


  public function destroy($id)
  {
    $appointment = ServiceBooking::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->find($id);
    // delete the attachment
    @unlink(public_path('assets/file/attachments/service/') . $appointment->attachment);
    @unlink(public_path('assets/file/invoices/service/') . $appointment->invoice);
    $appointment->delete();

    return response()->json([
      'success' => true,
      'message' => __('Appointment delete successfully!'),
    ], 200);
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $appointment = ServiceBooking::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->find($id);
      if (!empty($appointment->invoice)) {
        @unlink(public_path('assets/file/invoices/service/' . $appointment->invoice));
      }
      @unlink(public_path('assets/file/attachments/service/') . $appointment->attachment);
      $appointment->delete();
      return response()->json([
        'success' => true,
        'message' => __('Appointment delete successfully!'),
      ], 200);
    }
    return response()->json([
      'success' => true,
      'message' => __('Appointment delete successfully!'),
    ], 200);
  }

  //order status change
  public function updateAppointmentStatus(Request $request, $id)
  {
    $language = Language::where('is_default', 1)->first();
    $language_id = $language->id;

    $booking = ServiceBooking::with(['serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->find($id);

    if (!$booking) {
      return response()->json([
        'success' => false,
        'message' => __('Appointment not found.'),
      ], 404);
    }
    $vendor = Vendor::find($booking->vendor_id);

    if ($request['order_status'] == 'pending') {

      $booking->update([
        'order_status' => 'pending'
      ]);
      return response()->json([
        'success' => true,
        'message' => __('Appointment status update successful!'),
      ]);
    } else if ($request['order_status'] == 'accepted') {
      $lessAppointmentNum = intval($vendor->total_appointment) - 1;

      $booking->update([
        'order_status' => 'accepted'
      ]);

      //update less appoitnment number
      $vendor->update([
        'total_appointment' => $lessAppointmentNum,
      ]);

      $purchaseProcess = new ServicePaymentController();
      // generate an invoice in pdf format
      $invoice = $purchaseProcess->generateInvoice($booking);

      // then, update the invoice field info in database
      $booking->update(['invoice' => $invoice]);

      // send a mail to the customer with the invoice for booking accepted
      $purchaseProcess->prepareMail($booking);

      return response()->json([
        'success' => true,
        'message' => __('Appointment status update successful!'),
      ]);
    } else {
      //after reject
      $lessAppointmentNum = intval($vendor->total_appointment) + 1;
      $booking->update([
        'order_status' => 'rejected',
        'refund' => 'pending',
      ]);

      //update less appoitnment number
      $vendor->update([
        'total_appointment' => $lessAppointmentNum,
      ]);

      // get the mail template info from db
      $mailTemplate = MailTemplate::query()->where('mail_type', '=', 'service_booking_rejected')->first();
      $mailData['subject'] = $mailTemplate->mail_subject;
      $mailBody = $mailTemplate->mail_body;

      // get the website title info from db
      $info = Basic::select('website_title')->first();

      if ($booking->serviceContent->isNotEmpty()) {
        $service =  $booking->serviceContent->first()->name;
      }

      $price = $booking->currency_symbol . $booking->customer_paid;
      $username = $booking->customer_name;
      $websiteTitle = $info->website_title;

      // replacing with actual data

      $mailBody = str_replace('{service_name}', $service, $mailBody);
      $mailBody = str_replace('{username}', $username, $mailBody);
      $mailBody = str_replace('{website_title}', $websiteTitle, $mailBody);
      $mailBody = str_replace('{price}', $price, $mailBody);

      $mailData['body'] = $mailBody;
      $mailData['recipient'] = $booking->customer_email;

      BasicMailer::sendMail($mailData);

      return response()->json([
        'success' => true,
        'message' => __('Appointment status update successful!'),
      ]);
    }
  }
  public function staffAssign(Request $request)
  {
    $ruels = ['staff_id' => 'required'];
    $messages = [
      'staff_id.required' => 'The staff field is required',
    ];
    $validator = Validator::make($request->all(), $ruels, $messages);
    if ($validator->fails()) {
      return Response::json([
        'success' => false,
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }
    $appointment = ServiceBooking::find($request->appointment_id);
    $appointment->update([
      'staff_id' => $request->staff_id,
    ]);

    return response()->json([
      'success' => true,
      'message' => __('Staff assigned successfully!'),
    ]);
  }
}
