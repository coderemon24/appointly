<?php

namespace App\Http\Controllers\Api\Vendor\Staff;

use App\Models\Language;
use App\Models\Staff\Staff;
use Illuminate\Http\Request;
use App\Models\Services\Services;
use App\Models\Staff\StaffService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ServiceAssignController extends Controller
{
  public function index($id, Request $request)
  {
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $language_id = $language->id;
    $vendor_id = Auth::guard('sanctum_vendor')->user()->id;

    $information['langs'] = Language::all();
    $information['staff'] = Staff::where('vendor_id', $vendor_id)->find($id);

    $information['staffServices'] = StaffService::with([
      'staffContent' => function ($q) use ($language_id) {
        $q->where('language_id', $language_id);
      },
      'service' => function ($q) use ($language_id) {
        $q->where('language_id', $language_id)
          ->select('id', 'service_id', 'name', 'slug');
      }
    ])
      ->where([['vendor_id', $vendor_id], ['staff_id', $id]])
      ->orderByDesc('id')
      ->paginate(10);



    $information['services'] = Services::join('service_contents', 'services.id', '=', 'service_contents.service_id')
      ->where('services.vendor_id', $vendor_id)
      ->where('service_contents.language_id', $language->id)
      ->select('services.id as id', 'service_contents.name as name')
      ->get();

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }


  public function store(Request $request)
  {
    $request->validate([
      'service_id' => 'required|integer',
      'staff_id' => 'required|integer',
    ]);

    $staffServices = StaffService::where('staff_id', $request->staff_id)
      ->where('service_id', $request->service_id)
      ->count();


    if ($staffServices > 0) {
      return response()->json([
        'status' => 'warning',
        'message' => __("You can't add the same service multiple times!")
      ]);
    } else {
      StaffService::create([
        'vendor_id' => Auth::guard('sanctum_vendor')->user()->id,
        'service_id' => $request->service_id,
        'staff_id' => $request->staff_id,
      ]);

      $mainService = Services::find($request->service_id);
      $mainService->update(['staff_id' => $request->staff_id]);

      return response()->json([
        'status' => 'success',
        'message' => __('Service assigned successfully!')
      ]);
    }
  }


  public function destroy(Request $request)
  {
    $request->validate([
      'assign_id' => 'required|integer',
    ]);

    $id = $request->assign_id;
    $staffService = StaffService::query()->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->find($id);
    if (!$staffService) {
      return response()->json([
        'status' => 'error',
        'message' => __('Staff service not found!')
      ]);
    }

    $mainService = Services::find($staffService->service_id);
    $mainService->update(['staff_id' => null]);

    $staffService->delete();

    return response()->json([
      'status' => 'success',
      'message' => __('Service unassigned successfully!')
    ]);
  }
}
