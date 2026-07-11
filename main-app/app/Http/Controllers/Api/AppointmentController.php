<?php

namespace App\Http\Controllers\Api;

use Auth;
use App\Models\Admin;

use App\Models\Vendor;
use App\Models\Language;
use App\Models\Staff\Staff;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceBooking;
use App\Http\Controllers\FrontEnd\MiscellaneousController;

class AppointmentController extends Controller
{
  public function appointment(Request $request)
  {
    $misc = new MiscellaneousController();

    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $language_id = $language->id;

    $breadcrumb = null;
    if (!is_null($misc->getBreadcrumb()->breadcrumb)) {
      $breadcrumb = $misc->getBreadcrumb()->breadcrumb;
    }
    $data['bgImg'] = asset('assets/img/' . $breadcrumb);

    $data['pageHeading'] = $misc->getPageHeading($language);


    $data['appointments'] = ServiceBooking::join('service_contents', 'service_bookings.service_id', '=', 'service_contents.service_id')
      ->where('service_contents.language_id', $language_id)
      ->when($request->search_appointment, function ($query) use ($request) {
        $query->where(function ($subQuery) use ($request) {
          $subQuery->where('service_bookings.order_number', 'like', '%' . $request->search_appointment . '%')
            ->orWhere('service_contents.name', 'like', '%' . $request->search_appointment . '%');
        });
      })
      ->where('service_bookings.user_id', Auth::id())
      ->orderBy('service_bookings.id', 'desc')
      ->select(
        'service_contents.name',
        'service_contents.slug',
        'service_bookings.id',
        'service_bookings.start_date',
        'service_bookings.end_date',
        'service_bookings.booking_date',
        'service_bookings.vendor_id',
        'service_bookings.order_status',
        'service_bookings.service_id',
      )
      ->get();

    return response()->json($data);
  }

  public function details($id, Request $request)
  {
    $misc = new MiscellaneousController();
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();
    $language_id = $language->id;
    $user_id = Auth::id();
    $data['bgImg'] = asset('assets/img/' . $misc->getBreadcrumb()->breadcrumb);
    $data['pageHeading'] = $misc->getPageHeading($language);

    $appointment = ServiceBooking::with(['serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->find($id);

    //if appointment not found
    if (!$appointment) {
      return response()->json([
        'success' => false,
        'message' => 'Appointment not found'
      ]);
    }

    if ($appointment->user_id != $user_id) {
      return redirect()->route('user.dashboard');
    }
    $data['appointment'] = $appointment;

    //if staff not found
    $data['staff'] = Staff::join('staff_contents', 'staff_contents.staff_id', '=', 'staff.id')
      ->where('staff.id', $appointment->staff_id)
      ->select('staff.info_status', 'staff_contents.location', 'staff_contents.information', 'staff_contents.name')
      ->first();

    if (!$data['staff']) {
      return response()->json([
        'success' => false,
        'message' => 'Appointment not found'
      ]);
    }

    if ($appointment->vendor_id != 0) {
      $data['vendor'] = Vendor::join('vendor_infos', 'vendor_infos.vendor_id', 'vendors.id')
        ->where('vendors.id', $appointment->vendor_id)
        ->select('vendors.show_email_addresss', 'vendors.show_phone_number', 'vendors.email', 'vendors.phone', 'vendor_infos.name', 'vendor_infos.country', 'vendor_infos.city', 'vendor_infos.address', 'vendor_infos.details')
        ->first();
    } else {
      $data['vendor'] = Admin::whereNull('role_id')->first();
    }

    return response()->json($data);
  }
}
