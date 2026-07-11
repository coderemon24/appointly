<?php

namespace App\Http\Controllers\Api\StaffAdmin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Services\InqueryMessage;
use App\Models\Staff\StaffService;
use Auth;
use Illuminate\Http\Request;

class ServiceInqueryController extends Controller
{

  public function message()
  {
    $language = request()->language
      ? Language::where('code', request()->language)->first()
      : Language::where('is_default', 1)->first();

    if (!$language) {
      $language = Language::first();
    }

    $language_id = $language->id;
           $information['langs'] = Language::all();

    $staffId = Auth::guard('sanctum_staff')->user()->id;
    $serviceIds = StaffService::where('staff_id', $staffId)->pluck('service_id')->toArray();

    $information['messages'] = InqueryMessage::whereIn('service_id', $serviceIds)
    ->with(['serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->orderBy('id', 'DESC')
      ->get();

    return response()->json(['status' => 'success', 'data' => $information], 200);
  }

  public function messageDestroy($id)
  {
    $message = InqueryMessage::find($id);
    $message->delete();

    return response()->json(['status' => 'success', 'message' => __('Message delete successfully!')], 200);
  }

  public function bulkDelete(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $message = InqueryMessage::where('staff_id', Auth::guard('sanctum_staff')->user()->id)->find($id);

      if ($message) {
        $message->delete();
      }
    }

    session()->flash('success', __('Message deleted successfully!'));
    return response()->json(['status' => 'success', 'message' => __('Message deleted successfully!')], 200);
  }
}
