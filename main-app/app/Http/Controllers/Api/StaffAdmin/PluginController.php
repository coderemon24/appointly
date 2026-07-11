<?php

namespace App\Http\Controllers\Api\StaffAdmin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\Staff\Staff;
use App\Models\Staff\StaffPlugin;
use Auth;
use Illuminate\Http\Request;

class PluginController extends Controller
{
  public function index()
  {
    $vendorId = Staff::where('id', Auth::guard('sanctum_staff')->user()->id)->value('vendor_id');
    $permission = $vendorId != 0 ? VendorPermissionHelper::packagePermission($vendorId) : null;

    return response()->json([
      'status' => 'success',

      'plugin' => StaffPlugin::where('staff_id', Auth::guard('sanctum_staff')->user()->id)->first(['google_calendar', 'calender_id'])

    ], 200);
  }

  public function updateCalendar(Request $request)
  {
    $request->validate([
      'google_calendar' => 'required|mimes:json',
      'calender_id' => 'required',
    ], [
      'google_calendar.required' => __('The google calendar file is required.'),
      'google_calendar.mimes' => __('Only JSON files are supported for Google Calendar.'),
    ]);

    $file = UploadFile::store(public_path('assets/file/calendar/'), $request->file('google_calendar'));

    StaffPlugin::query()->updateOrInsert(
      ['staff_id' => Auth::guard('sanctum_staff')->user()->id],
      [
        'staff_id' => Auth::guard('sanctum_staff')->user()->id,
        'google_calendar' => $file,
        'calender_id' => $request->calender_id,
      ]
    );

    return response()->json(['status' => 'success', 'message' => __('Calendar info updated successfully!')], 200);
  }
}
