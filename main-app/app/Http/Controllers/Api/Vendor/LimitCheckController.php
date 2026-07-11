<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Models\Language;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\CheckLimitHelper;
use App\Models\Services\ServiceContent;
use App\Http\Helpers\VendorPermissionHelper;

class LimitCheckController extends Controller
{
  public function limitCheck(Request $request)
  {
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $vendor = Auth::guard('sanctum_vendor')->user();

    if (!$vendor) {
      return response()->json([
        'status' => false,
        'message' => __('Unauthenticated'),
      ], 401);
    }

    $vendor_id = $vendor->id;
    $currentPackage = VendorPermissionHelper::currentPackagePermission($vendor_id);

    // check if user has a package
    if (!$currentPackage) {
      return response()->json([
        'status' => false,
        'message' => __('Please buy a package to use this panel!'),
      ], 400);
    }

    // ====== Counts / Remaining ======
    $totalServices = (int) CheckLimitHelper::countService($vendor_id);
    $serviceLimit  = (int) CheckLimitHelper::serviceLimit($vendor_id); // usually from package
    $servicesLeft  = $serviceLimit - $totalServices;

    $totalStaffs = (int) vendorTotalAddedStaff($vendor_id);
    $staffLimit  = (int) CheckLimitHelper::staffLimit($vendor_id);
    $staffsLeft  = $staffLimit - $totalStaffs;

    // your UI treats $appointments as "appointments left"
    // so assume helper returns remaining = limit - used
    $appointmentsLeft = (int) CheckLimitHelper::countAppointment($vendor_id);

    // image downgraded services list (where per-service image limit exceeded)
    $serviceIds = CheckLimitHelper::countImage($vendor_id); // array of service ids
    $imageDowngradedServices = ServiceContent::where('language_id', $language->id)
      ->whereIn('service_id', $serviceIds)
      ->select('name','slug','service_id')
      ->get();
    $imageLimitCount = is_array($serviceIds) ? count($serviceIds) : 0;

    // ====== helpers ======
    $isUnlimited = function ($limit) {
      return (int) $limit >= 999999;
    };

    $data = [
      'package' => [
        'id' => $currentPackage->id ?? null,
        'name' => $currentPackage->title ?? ($currentPackage->name ?? null),
      ],
      'services' => [
        'status' => 'success',       // success | limit_reached | exceeding_limits | downgraded
        'can_add' => 0,
        'need_to_delete' => 0,
        'total' => $totalServices,
        'limit' => (int) $currentPackage->number_of_service_add,
      ],
      'staffs' => [
        'status' => 'success',
        'can_add' => 0,
        'need_to_delete' => 0,
        'total' => $totalStaffs,
        'limit' => (int) $currentPackage->staff_limit,
      ],
      'appointments' => [
        'status' => 'success',
        'can_add' => 0,
        'need_to_delete' => 0,
        'left' => $appointmentsLeft,
        'limit' => (int) $currentPackage->number_of_appointment,
      ],
      'images' => [
        'status' => 'success',
        'limit_per_service' => (int) $currentPackage->number_of_service_image,
        'need_fix_count' => $imageLimitCount,   // how many services are violating
        'services' => $imageDowngradedServices ?? [],
      ],
    ];

    // ====== SERVICES ======
    $servicePkgLimit = (int) $currentPackage->number_of_service_add;

    if ($totalServices > $servicePkgLimit) {
      $data['services']['status'] = 'downgraded';
      $data['services']['need_to_delete'] = $totalServices - $servicePkgLimit;
    }

    if (!$isUnlimited($servicePkgLimit)) {
      if ($servicesLeft <= 0 && $totalServices <= $servicePkgLimit) {
        $data['services']['status'] = 'limit_reached';
      } elseif ($totalServices > $servicePkgLimit) {
        $data['services']['status'] = 'exceeding_limits';
      }

      $data['services']['can_add'] = ($totalServices > $servicePkgLimit) ? 0 : max(0, $servicesLeft);
    } else {
      $data['services']['can_add'] = 'unlimited';
    }

    // ====== STAFFS ======
    $staffPkgLimit = (int) $currentPackage->staff_limit;

    if ($totalStaffs > $staffPkgLimit) {
      $data['staffs']['status'] = 'downgraded';
      $data['staffs']['need_to_delete'] = $totalStaffs - $staffPkgLimit;
    }

    if (!$isUnlimited($staffPkgLimit)) {
      if ($staffsLeft <= 0 && $totalStaffs <= $staffPkgLimit) {
        $data['staffs']['status'] = 'limit_reached';
      } elseif ($totalStaffs > $staffPkgLimit) {
        $data['staffs']['status'] = 'exceeding_limits';
      }

      $data['staffs']['can_add'] = ($totalStaffs > $staffPkgLimit) ? 0 : max(0, $staffsLeft);
    } else {
      $data['staffs']['can_add'] = 'unlimited';
    }

    // ====== APPOINTMENTS ======
    $appPkgLimit = (int) $currentPackage->number_of_appointment;

    if (!$isUnlimited($appPkgLimit)) {
      // appointmentsLeft == 0 => limit reached
      if ($appointmentsLeft === 0) {
        $data['appointments']['status'] = 'limit_reached';
      }
      // appointmentsLeft < 0 => exceeding
      if ($appointmentsLeft < 0) {
        $data['appointments']['status'] = 'exceeding_limits';
        $data['appointments']['need_to_delete'] = abs($appointmentsLeft); // how many over
      }

      $data['appointments']['can_add'] = ($appointmentsLeft > 0) ? $appointmentsLeft : 0;
    } else {
      $data['appointments']['can_add'] = 'unlimited';
      $data['appointments']['status'] = 'success';
    }

    // ====== IMAGES (per service) ======
    $imgPkgLimit = (int) $currentPackage->number_of_service_image;

    if (!$isUnlimited($imgPkgLimit)) {
      if ($imageLimitCount > 0) {
        $data['images']['status'] = 'downgraded'; // violating services exist
      } else {
        $data['images']['status'] = 'success';
      }
    } else {
      $data['images']['status'] = 'success';
      $data['images']['limit_per_service'] = 'unlimited';
    }

    // ====== INFO ICON (anything problematic?) ======
    $infoIcon = (
      in_array($data['services']['status'], ['downgraded', 'exceeding_limits'], true) ||
      in_array($data['staffs']['status'], ['downgraded', 'exceeding_limits'], true) ||
      in_array($data['appointments']['status'], ['downgraded', 'exceeding_limits'], true) ||
      $data['images']['status'] === 'downgraded'
    );

    return response()->json([
      'downgraded' => $infoIcon,
      'data' => $data,
    ], 200);
  }
}
