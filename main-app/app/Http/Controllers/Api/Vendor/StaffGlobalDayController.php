<?php

namespace App\Http\Controllers\Api\Vendor;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Staff\StaffGlobalDay;
use Illuminate\Support\Facades\Auth;
use App\Models\Staff\StaffGlobalHour;
use App\Models\Staff\StaffGlobalHoliday;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class StaffGlobalDayController extends Controller
{
  public function index()
  {
    $information['days'] = StaffGlobalDay::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->get();

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }

  public function weekendChange($id, Request $request)
  {
    $current_package = \App\Http\Helpers\VendorPermissionHelper::packagePermission(Auth::guard('sanctum_vendor')->user()->id);

    if ($current_package == '[]') {
      return response()->json([
        'success' => false,
        'message' => __('Please buy a plan to make changes!'),
      ], 400);
    } else {
      $hour = StaffGlobalHour::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->where('global_day_id', $id)->get();

      if ($hour->count() > 0) {
        return response()->json([
          'success' => false,
          'message' => __('First delete all the time slots of this day!'),
        ], 400);
      } else {
        $staffGlobalDay = StaffGlobalDay::find($id);
        $staffGlobalDay->update(['is_weekend' => $request->is_weekend]);
        return response()->json([
          'success' => true,
          'message' => __('Weekend change successfully!'),
        ], 200);
      }
    }
  }

  public function serviceHour(Request $request)
  {
    $information['currentDay'] = StaffGlobalDay::where('id', $request->day_id)->select('day')->first();

    $information['service_hours'] = StaffGlobalHour::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->where('global_day_id', $request->day_id)
      ->paginate(10);

    return response()->json([
      'success' => true,
      'data' => $information
    ], 200);
  }


  public function store(Request $request)
  {
    $current_package = \App\Http\Helpers\VendorPermissionHelper::packagePermission(Auth::guard('sanctum_vendor')->user()->id);

    if ($current_package == '[]') {
      return response()->json([
        'success' => false,
        'message' => __('Please buy a plan to add hour!'),
      ], 400);
    } else {
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
      $servicehour = new StaffGlobalHour();
      $servicehour->global_day_id = $request->global_day_id;
      $servicehour->start_time = $request->start_time;
      $servicehour->end_time =  $request->end_time;
      $servicehour->max_booking =  $request->max_booking;
      $servicehour->vendor_id = Auth::guard('sanctum_vendor')->user()->id;
      $servicehour->save();

      return response()->json([
        'success' => true,
        'message' => __('Time slot added successfully!'),
      ], 200);
    }
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

    $servicehour = StaffGlobalHour::find($request->id);

    $servicehour->global_day_id = $request->global_day_id;
    $servicehour->start_time = $request->start_time;
    $servicehour->max_booking =  $request->max_booking;
    $servicehour->end_time =  $request->end_time;
    $servicehour->vendor_id = Auth::guard('sanctum_vendor')->user()->id;
    $servicehour->save();

    return response()->json([
      'success' => true,
      'message' => __('Time slot updated successfully!'),
    ], 200);
  }

  public function destroy($id)
  {
    $service_hour = StaffGlobalHour::query()->find($id);
    $service_hour->delete();

    return response()->json([
      'success' => true,
      'message' => __('Time slots deleted successfully!'),
    ], 200);
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $service_hours = StaffGlobalHour::find($id);
      $service_hours->delete();
    }

    return response()->json([
      'success' => true,
      'message' => __('Time slots deleted successfully!'),
    ], 200);
  }


  public function holidayIndex()
  {
    $globalHoliday = StaffGlobalHoliday::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->get();

    return response()->json([
      'success' => true,
      'data' => $globalHoliday
    ]);
  }



  public function holidayStore(Request $request)
  {
    $current_package = \App\Http\Helpers\VendorPermissionHelper::packagePermission(Auth::guard('sanctum_vendor')->user()->id);

    if ($current_package == '[]') {
      return response()->json([
        'success' => false,
        'message' => __('Please buy a plan to add holiday!'),
      ], 400);
    } else {
      $rules = [
        'date' => 'required',
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

      $holiday = StaffGlobalHoliday::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->pluck('date')->toArray();
      $date = date('Y-m-d', strtotime($request->date));
      if (in_array($date, $holiday)) {
        return response()->json([
          'success' => false,
          'message' => __('The date exists in the holiday list!'),
        ], 400);
      } {
        StaffGlobalHoliday::create([
          'date' => $date,
          'vendor_id' => Auth::guard('sanctum_vendor')->user()->id
        ]);

        return response()->json([
          'success' => true,
          'message' => __('Holiday added successfully!'),
        ], 200);
      }
    }
  }


  public function holidayDestroy(Request $request, $id)
  {

    $UserStaffHoliday = StaffGlobalHoliday::find($id);

    $UserStaffHoliday->delete();

    return response()->json([
      'success' => true,
      'message' => __('Holiday delete successfully!'),
    ], 200);
  }

  public function holidayBulkDestroy(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $UserStaffHoliday = StaffGlobalHoliday::find($id);
      $UserStaffHoliday->delete();
    }

    return response()->json([
      'success' => true,
      'message' => __('Holiday delete successfully!'),
    ], 200);
  }
}
