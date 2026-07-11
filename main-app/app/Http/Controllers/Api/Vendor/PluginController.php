<?php

namespace App\Http\Controllers\Api\Vendor;

use DB;
use Auth;
use Session;
use Validator;
use Illuminate\Http\Request;
use App\Http\Helpers\UploadFile;
use App\Http\Controllers\Controller;
use App\Models\VendorPlugins\VendorPlugin;
use App\Http\Helpers\VendorPermissionHelper;

class PluginController extends Controller
{
  public function index()
  {
    $permission = VendorPermissionHelper::packagePermission(Auth::guard('sanctum_vendor')->user()->id);

    if ($permission->calendar_status == 0 && $permission->zoom_meeting_status == 0) {
      return redirect()->back();
    }
    $data = VendorPlugin::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->select('zoom_account_id', 'zoom_client_id', 'zoom_client_secret', 'google_calendar', 'calender_id')
      ->first();

    return response()->json([
      'status' => true,
      'data' => $data,
    ]);
  }

  public function zoomUpdate(Request $request)
  {
    $rules = [
      'zoom_account_id' => 'required',
      'zoom_client_id' => 'required',
      'zoom_client_secret' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return response()->json([
        'status' => false,
        'message' => $validator->errors()->first(),
      ], 422);
    }

    DB::table('vendor_plugins')->updateOrInsert(
      ['vendor_id' => Auth::guard('sanctum_vendor')->user()->id],
      [
        'vendor_id' => Auth::guard('sanctum_vendor')->user()->id,
        'zoom_account_id' => $request->zoom_account_id,
        'zoom_client_id' => $request->zoom_client_id,
        'zoom_client_secret' => $request->zoom_client_secret,
      ]
    );

    return response()->json([
      'status' => true,
      'message' => __('Zoom account updated successfully!'),
    ]);
  }



  public function updateCalendar(Request $request)
  {
    $data = VendorPlugin::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->select('google_calendar')
      ->first();

    $request->validate([
      'google_calendar' => 'required|mimes:json',
      'calender_id' => 'required',
    ], [
      'google_calendar.required' => __('The google calendar file is required.'),
      'google_calendar.mimes' => __('Only JSON files are supported for Google Calendar.'),
    ]);

    // Store the uploaded file
    $file = UploadFile::store(public_path('assets/file/calendar/'), $request->file('google_calendar'));

    // Update or insert into the database
    VendorPlugin::query()->updateOrInsert(
      ['vendor_id' => Auth::guard('sanctum_vendor')->user()->id],
      [
        'vendor_id' => Auth::guard('sanctum_vendor')->user()->id,
        'google_calendar' => $request->hasFile('google_calendar') ? $file : $data->google_calendar,
        'calender_id' => $request->calender_id,
      ]
    );

    return response()->json([
      'status' => true,
      'message' => __('Google Calendar updated successfully!'),
    ]);
  }
}
