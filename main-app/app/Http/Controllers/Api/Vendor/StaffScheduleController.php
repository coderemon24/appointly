<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Models\Language;
use App\Models\Staff\Staff;
use Illuminate\Http\Request;
use App\Models\Staff\StaffDay;
use App\Models\Staff\StaffHoliday;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Staff\StaffServiceHour;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class StaffScheduleController extends Controller
{
  public function day($id)
  {
    $language = Language::where('is_default', 1)->first();
    $language_id = $language->id;

    $data['staff'] = Staff::with(['StaffContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->find($id);

    $data['themeInfo'] = DB::table('basic_settings')->select('theme_version')->first();

    $data['days'] = StaffDay::where('staff_id', $id)
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->get();

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }

  //change schedule type
  public function scheduleType(Request $request, $id)
  {
    $staffday = Staff::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->find($id);
    if ($staffday) {
      $staffday->is_day = $request->is_day;
      $staffday->save();
    }

    if ($staffday->is_day == 1) {
      return response()->json([
        'success' => true,
        'message' => __('Staff schedule entered successfully!')
      ]);
    }
    return response()->json([
      'success' => true,
      'message' => __('Owner schedule entered successfully!')
    ]);
  }

  //get time slots
  public function TimeSlots(Request $request)
  {
    $information['currentDay'] = StaffDay::where('id', $request->day_id)->select('day')->first();
    $information['staff'] = Staff::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->where('id', $request->staff_id)->first();
    $information['service_hours'] = StaffServiceHour::where('staff_id', $request->staff_id)
      ->where('staff_day_id', $request->day_id)
      ->get();

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }

  //store time slot
  public function storeTimeSlot(Request $request)
  {
    $rules = [
      'start_time' => 'required',
      'end_time' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json(
        [
          'errors' => $validator->getMessageBag()->toArray()
        ],
        400
      );
    }

    $servicehour = new StaffServiceHour();
    $servicehour->staff_id = $request->staff_id;
    $servicehour->start_time = $request->start_time;
    $servicehour->end_time =  $request->end_time;
    $servicehour->max_booking =  $request->max_booking;
    $servicehour->staff_day_id = $request->staff_day_id;
    $servicehour->save();

    return response()->json([
      'success' => true,
      'message' => __('Time slot added successfully!')
    ]);
  }

  //update time slot
  public function updateSlot(Request $request)
  {
    $rules = [
      'start_time' => 'required',
      'end_time' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json(
        [
          'errors' => $validator->getMessageBag()->toArray()
        ],
        400
      );
    }

    $servicehour = StaffServiceHour::find($request->id);

    $servicehour->staff_id = $servicehour->staff_id;
    $servicehour->staff_day_id = $servicehour->staff_day_id;
    $servicehour->start_time = $request->start_time;
    $servicehour->end_time =  $request->end_time;
    $servicehour->max_booking =  $request->max_booking;
    $servicehour->update();

    return response()->json([
      'success' => true,
      'message' => __('Time slot updated successfully!')
    ]);
  }


  public function update(Request $request)
  {
    $rules = [
      'start_time' => 'required',
      'end_time' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json(
        [
          'errors' => $validator->getMessageBag()->toArray()
        ],
        400
      );
    }

    $servicehour = StaffServiceHour::find($request->id);

    $servicehour->staff_id = $servicehour->staff_id;
    $servicehour->staff_day_id = $servicehour->staff_day_id;
    $servicehour->start_time = $request->start_time;
    $servicehour->end_time =  $request->end_time;
    $servicehour->max_booking =  $request->max_booking;
    $servicehour->update();

    return response()->json([
      'success' => true,
      'message' => __('Time slot updated successfully!')
    ]);
  }

  public function destroy($id)
  {
    $service_hour = StaffServiceHour::query()->find($id);
    if (!$service_hour) {
      return response()->json([
        'success' => false,
        'message' => __('Time slot not found!')
      ], 404);
    }
    $service_hour->delete();

    return response()->json([
      'success' => true,
      'message' => __('Time slot deleted successfully!')
    ]);
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;

    if (empty($ids)) {
      return response()->json([
        'success' => false,
        'message' => __('No time slots selected for deletion!')
      ], 400);
    }

    foreach ($ids as $id) {
      $service_hours = StaffServiceHour::find($id);
      $service_hours->delete();
    }
    return response()->json([
      'success' => true,
      'message' => __('Time slots deleted successfully!')
    ]);
  }

  //change weekend

  public function weekendChange($id, Request $request)
  {

    if (!$id) {
      return response()->json([
        'success' => false,
        'message' => __('Invalid staff ID!')
      ], 400);
    }
    $staffday = StaffDay::where('staff_id', $request->staff_id)->find($id);

    $hour = StaffServiceHour::where('staff_id', $request->staff_id)->where('staff_day_id', $id)->get();

    if ($hour->count() > 0) {
      return response()->json([
        'success' => false,
        'message' => __('First delete all the time slots of this day!')
      ], 400);
    } else {
      if ($staffday) {
        $staffday->update(['is_weekend' => $request->is_weekend]);
      }
      return response()->json([
        'success' => true,
        'message' => __('Weekend change successfully!')
      ]);
    }
  }

  //holidays
  public function holidays($id)
  {
    $language = Language::where('is_default', 1)->first();
    $language_id = $language->id;

    $information['staff'] = Staff::with(['StaffContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->find($id);
    $information['staff_holydays'] = StaffHoliday::where('staff_id', $id)->get();

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }


  public function holidayStore(Request $request)
  {
    $rules = ['date' => 'required'];

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
      return Response::json(
        [
          'success' => false,
          'errors' => $validator->getMessageBag()->toArray()
        ],
        400
      );
    }

    $holiday = StaffHoliday::where('staff_id', $request->staff_id)->pluck('date')->toArray();
    $date = date('Y-m-d', strtotime($request->date));

    if (in_array($date, $holiday)) {

      return Response::json(
        [
          'status' => 'warning',
          'message' => __('The date exists in the holiday list!')
        ],
        200
      );
    } else {
      StaffHoliday::create([
        'date' => $date,
        'staff_id' => $request->staff_id,
        'vendor_id' => Auth::guard('sanctum_vendor')->user()->id
      ]);

      return Response::json(
        [
          'success' => true,
          'message' => __('Holiday added successfully!')
        ],
        200
      );
    }
  }


  public function holidayDestory(Request $request, $id)
  {

    $UserStaffHoliday = StaffHoliday::where('staff_id', $request->staff_id)->find($id);

    $UserStaffHoliday->delete();

    return response()->json([
      'success' => true,
      'message' => __('Holiday delete successfully!')
    ]);
  }

  public function holidayBulkDestory(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $UserStaffHoliday = StaffHoliday::find($id);
      $UserStaffHoliday->delete();
    }

    return Response::json(
      [
        'success' => true,
        'message' => __('Holiday delete successfully!')
      ],
      200
    );
  }

  public function holidayScheduleType(Request $request, $id)
  {
    $vendorId = Auth::guard('sanctum_vendor')->id();

    $staff = Staff::where('vendor_id', $vendorId)->find($id);

    if (!$staff) {
      return response()->json([
        'status'  => 'error',
        'message' => __('Staff not found!')
      ], 404);
    }

    $staff->update([
      'is_day' => (int) $request->input('is_day', 0),
    ]);

    $message = $staff->is_day === 1
      ? __('Staff schedule entered successfully!')
      : __('Owner schedule entered successfully!');

    return response()->json([
      'status'  => 'success',
      'message' => $message
    ], 200);
  }
}
