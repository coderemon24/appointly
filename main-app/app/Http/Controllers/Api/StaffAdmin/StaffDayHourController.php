<?php

namespace App\Http\Controllers\Api\StaffAdmin;

use App\Http\Controllers\Controller;
use App\Models\Staff\Staff;
use App\Models\Staff\StaffDay;
use App\Models\Staff\StaffServiceHour;
use Auth;
use Illuminate\Http\Request;
use Response;
use Validator;

class StaffDayHourController extends Controller
{
  public function day()
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $id = $user->id;
    $staff = Staff::find($id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }
    $information['staff'] = $staff;
    $information['days'] = StaffDay::where('staff_id', $id)->get();

    if ($staff->time == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission to edit time slot!')], 403);
    }

    return response()->json(['status' => 'success', 'data' => $information], 200);
  }

  public function changeStaffSetting(Request $request, $id)
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $staff_id = $user->id;
    $staff = Staff::find($staff_id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }

    $staff->is_day = $request->is_day;
    $staff->save();

    $message = $staff->is_day == 1
      ? __('Staff schedule entered successfully!')
      : __('Owner schedule entered successfully!');

    return response()->json(['status' => 'success', 'message' => $message], 200);
  }

  public function weekendChange(Request $request, $id)
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $staff_id = $user->id;
    $staff = Staff::find($staff_id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }

    $staffday = StaffDay::where('staff_id', $staff_id)->find($id);
    if ($staff->time == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission to edit time slot!')], 403);
    }

    if ($staffday) {
      $staffday->update(['is_weekend' => $request->is_weekend]);
    }

    return response()->json(['status' => 'success', 'message' => __('Weekend change successfully!')], 200);
  }

  public function timeSlots(Request $request)
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $id = $user->id;
    $staff = Staff::find($id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }

    if ($staff->time == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission to edit time slot!')], 403);
    }

    if ($request->filled('staff_day_id')) {
      $day = StaffDay::where('staff_id', $id)->where('id', $request->staff_day_id)->first();

      if (!$day) {
        return response()->json(['status' => 'error', 'message' => __('Day not found!')], 404);
      }

      $day->service_hours = StaffServiceHour::where('staff_id', $id)
        ->where('staff_day_id', $day->id)
        ->get();

      return response()->json([
        'status' => 'success',
        'data' => [
          'staff' => $staff,
          'day'   => $day,
        ]
      ], 200);
    }

    $days = StaffDay::where('staff_id', $id)->get();

    $days->each(function ($day) use ($id) {
      $day->service_hours = StaffServiceHour::where('staff_id', $id)
        ->where('staff_day_id', $day->id)
        ->get();
    });

    return response()->json([
      'status' => 'success',
      'data' => [
        'staff' => $staff,
        'days'  => $days,
      ]
    ], 200);
  }

  public function hour(Request $request)
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $id = $user->id;
    $staff = Staff::find($id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }
    $information['staff'] = $staff;

    $information['currentDay'] = StaffDay::where('id', $request->day_id)
      ->where('staff_id', $id)
      ->select('day')
      ->first();

    $information['service_hours'] = StaffServiceHour::where('staff_id', $id)
      ->where('staff_day_id', $request->day_id)
      ->get();

    if ($staff->time == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission to edit time slot!')], 403);
    }

    return response()->json(['status' => 'success', 'data' => $information], 200);
  }

  public function store(Request $request)
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $id = $user->id;
    $staff = Staff::find($id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }

    if ($staff->time == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission to edit time slot!')], 403);
    }

    $rules = [
      'staff_day_id' => 'required|exists:staff_days,id',
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
    $servicehour->staff_id = $id;
    $servicehour->start_time = $request->start_time;
    $servicehour->end_time =  $request->end_time;
    $servicehour->max_booking =  $request->max_booking;
    $servicehour->staff_day_id = $request->staff_day_id;
    $servicehour->save();

    return Response::json(['status' => 'success', 'message' => __('Time slot added successfully!')], 200);
  }

  public function update(Request $request)
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $id = $user->id;
    $staff = Staff::find($id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }

    if ($staff->time == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission to edit time slot!')], 403);
    }

    $rules = [
      'id'         => 'required|integer',
      'start_time' => 'required',
      'end_time'   => 'required',
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

    if (!$servicehour) {
      return Response::json(['status' => 'error', 'message' => 'Time slot not found.'], 404);
    }

    $servicehour->start_time  = $request->start_time;
    $servicehour->end_time    = $request->end_time;
    $servicehour->max_booking = $request->max_booking;
    $servicehour->update();

    return Response::json(['status' => 'success', 'message' => __('Time slot updated successfully!')], 200);
  }

  public function destroy($id)
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $staff = Staff::find($user->id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }

    if ($staff->time == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission to edit time slot!')], 403);
    }

    $service_hour = StaffServiceHour::query()->find($id);
    if (!$service_hour) {
      return response()->json(['status' => 'error', 'message' => 'Time slot not found.'], 404);
    }
    $service_hour->delete();

    return response()->json(['status' => 'success', 'message' => __('Time slot delete successfully!')], 200);
  }

  public function bulkDestroy(Request $request)
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $staff = Staff::find($user->id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }

    if ($staff->time == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission to edit time slot!')], 403);
    }

    $ids = $request->ids;

    foreach ($ids as $id) {
      $service_hours = StaffServiceHour::find($id);
      $service_hours->delete();
    }

    session()->flash('success', __('Time slots delete successfully!'));
    return Response::json(['status' => 'success', 'message' => __('Time slots delete successfully!')], 200);
  }
}
