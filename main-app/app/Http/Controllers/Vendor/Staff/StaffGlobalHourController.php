<?php

namespace App\Http\Controllers\Vendor\Staff;

use App\Http\Controllers\Controller;
use App\Models\Staff\StaffGlobalDay;
use App\Models\Staff\StaffGlobalHour;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Response;

class StaffGlobalHourController extends Controller
{
  public function serviceHour(Request $request)
  {
    $information['currentDay'] = StaffGlobalDay::where('id', $request->day_id)->select('day')->first();

    $information['service_hours'] = StaffGlobalHour::where('vendor_id', Auth::guard('vendor')->user()->id)
      ->where('global_day_id', $request->day_id)
      ->get();

    return view('vendors.staff.global-hour.index', $information);
  }

  public function store(Request $request)
  {
    $current_package = \App\Http\Helpers\VendorPermissionHelper::packagePermission(Auth::guard('vendor')->user()->id);

    if ($current_package == '[]') {
      session()->flash('warning',  __('Please buy a plan to add hour!') );
      return Response::json(['status' => 'success'], 200);
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
      $servicehour->vendor_id = Auth::guard('vendor')->user()->id;
      $servicehour->save();

      session()->flash('success',  __('Time slot added successfully!') );
      return Response::json(['status' => 'success'], 200);
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
    $servicehour->vendor_id = Auth::guard('vendor')->user()->id;
    $servicehour->save();

    session()->flash('success',  __('Time slot updated successfully!') );
    return Response::json(['status' => 'success'], 200);
  }

  public function destroy($id)
  {
    $service_hour = StaffGlobalHour::query()->find($id);
    $service_hour->delete();
    return redirect()->back()->with('success',  __('Time slot deleted successfully!') );
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $service_hours = StaffGlobalHour::find($id);
      $service_hours->delete();
    }

    session()->flash('success',  __('Time slots deleted successfully!') );
    return Response::json(['status' => 'success'], 200);
  }
}
