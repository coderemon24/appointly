<?php

namespace App\Http\Controllers\Api\StaffAdmin;

use Auth;
use Session;
use Purifier;
use Validator;
use App\Models\Language;
use App\Models\Staff\Staff;
use Illuminate\Http\Request;
use App\Http\Helpers\UploadFile;
use App\Models\Services\Services;
use App\Models\Services\Wishlist;
use App\Models\Staff\StaffService;
use Illuminate\Support\Facades\DB;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceImage;
use App\Models\Services\ServiceReview;
use App\Models\Services\InqueryMessage;
use App\Models\Services\ServiceBooking;
use App\Models\Services\ServiceContent;
use App\Models\Services\ServiceCategory;
use App\Models\Services\ServiceSubCategory;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\FeaturedService\ServicePromotion;
use App\Http\Requests\Service\ServiceStoreRequest;
use App\Http\Requests\Service\ServiceUpdateRequest;

class ServiceController extends Controller
{
  public function index(Request $request)
  {
    // Resolve language: query param → Accept-Language header → default → first
    $langCode = $request->language
      ?? $request->getPreferredLanguage()
      ?? null;

    $language = ($langCode ? Language::where('code', $langCode)->first() : null)
      ?? Language::where('is_default', 1)->first()
      ?? Language::first();

    $information['language_id'] = $language->id;
    $information['langs'] = Language::all();

    //staff
    $staffId = Auth::guard('sanctum_staff')->user()->id;
    $vendorId = Staff::pluck('vendor_id')->where('id', $staffId)->first();

    $package = null;
    if ($vendorId != 0) {
      $package = VendorPermissionHelper::packagePermission($vendorId);
    }
    $information['package'] = $package;

    //check package
    if ($package != null && $package != []) {
      if (vendorTotalAddedService($vendorId) > $package->number_of_service_add) {
        $information['service_add'] = 'over';
      } else {
        $information['service_add'] = '';
      }
    } else {
      $information['service_add'] = 0;
    }

    // Get assigned service IDs for this staff
    $serviceIds = StaffService::where('staff_id', $staffId)->pluck('service_id');

    // Load services with content filtered to the resolved language only
    $information['services'] = Services::with(['content' => function ($query) use ($language) {
      $query->where('language_id', $language->id);
    }])
      ->whereIn('id', $serviceIds)
      ->orderBy('created_at', 'desc')
      ->paginate(10);

    $information['services']->getCollection()->transform(function ($service) {
      $service->service_image = asset('assets/img/services/' . $service->service_image);
      return $service;
    });

    $information['currencyInfo'] = $this->getCurrencyInfo();
    return response()->json(['status' => 'success', 'data' => $information], 200);
  }

  public function create()
  {
    //staff
    $staffId = Auth::guard('sanctum_staff')->user()->id;
    $staff = Staff::findOrFail($staffId);
    if ($staff->service_add == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission!')], 403);
    }
    $information['staff'] = $staff;

    $information['languages'] = Language::all();
    $information['currencyInfo'] = $this->getCurrencyInfo();

    return response()->json(['status' => 'success', 'data' => $information], 200);
  }

  //service slider image
  public function imagesstore(Request $request)
  {
    $img = $request->file('file');
    $allowedExts = array('jpg', 'png', 'jpeg', 'svg', 'webp');
    $rules = [
      'file' => [
        function ($attribute, $value, $fail) use ($img, $allowedExts) {
          $ext = $img->getClientOriginalExtension();
          if (!in_array($ext, $allowedExts)) {
            return $fail("Only png, jpg, jpeg images are allowed");
          }
        },
      ]
    ];
    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
      $validator->getMessageBag()->add('error', 'true');
      return response()->json($validator->errors());
    }
    $filename = uniqid() . '.jpg';

    $directory = public_path('assets/img/services/service-gallery/');
    @mkdir($directory, 0775, true);
    $img->move($directory, $filename);

    $pi = new ServiceImage();
    if (!empty($request->service_id)) {
      $pi->service_id = $request->service_id;
    }
    $pi->image = $filename;
    $pi->save();
    return response()->json(['status' => 'success', 'file_id' => $pi->id]);
  }

  //delete slider image after reload
  public function deleteSliderImage()
  {
    try {
      $images = ServiceImage::all();
      return response()->json(['status' => 'success', 'images' => $images]);
    } catch (\Exception $e) {
      return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
  }


  public function removeImage(Request $request)
  {
    $pi = ServiceImage::findOrFail($request->fileid);
    $image_count = ServiceImage::where('service_id', $pi->service_id)->get()->count();
    if ($image_count > 1) {
      @unlink(public_path('assets/img/services/service-gallery/') . $pi->image);
      $pi->delete();
      return response()->json(['status' => 'success', 'id' => $pi->id]);
    } else {
      return response()->json(['status' => 'error', 'message' => __('You can\'t delete all images!')], 400);
    }
  }

  //imagedbrmv
  public function imagedbrmv(Request $request)
  {

    $pi = ServiceImage::find($request->fileid);

    if (!$pi) {
      return response()->json(['status' => 'error', 'message' => __('Image not found!')], 404);
    }

    $image_count = ServiceImage::where('service_id', $pi->service_id)->get()->count();
    if ($image_count > 1) {
      @unlink(public_path('assets/img/services/service-gallery/') . $pi->image);
      $pi->delete();

      return response()->json(['status' => 'success', 'message' => __('Image deleted successfully!')], 200);
    } else {
      return response()->json(['status' => 'error', 'message' => __('You can\'t delete all images!')], 400);
    }
  }


  public function store(ServiceStoreRequest $request)
  {
    DB::transaction(function () use ($request) {
      // store featured image in storage
      $service_image = UploadFile::store(public_path('assets/img/services/'), $request->file('service_image'));


      $languages = Language::all();

      $service = Services::create([
        'price' => $request->price,
        'zoom_meeting' => $request->zoom_meeting ?? 0,
        'calendar_status' => $request->calendar_status ?? 0,
        'service_image' => $service_image,
        'vendor_id' => $request->vendor_id,
        'status' => $request->status,
        'staff_id' => $request->staff_id,
        'prev_price' => $request->prev_price,
        'max_person' => $request->person,
        'latitude' => $request->latitude,
        'longitude' => $request->longitude
      ]);

      //staff service assign
      StaffService::create([
        'vendor_id' => $service->vendor_id,
        'service_id' => $service->id,
        'staff_id' => $service->staff_id,
      ]);

      //store slider image
      foreach ($request->slider_images as $key => $image) {
        $sliderImagePath = UploadFile::store(public_path('assets/img/services/service-gallery/'), $image);
        ServiceImage::create([
          'image' => $sliderImagePath,
          'service_id' => $service->id
        ]);
      }

      foreach ($languages as $language) {
        $code = $language->code;
        if (
          $language->is_default == 1 ||
          $request->filled($code . '_name') ||
          $request->filled($code . '_description') ||
          $request->filled($code . '_category_id')
        ) {
          $content = new ServiceContent();
          $content->service_id  = $service->id;
          $content->language_id = $language->id;
          $content->category_id = $request[$code . '_category_id'];
          $content->subcategory_id = $request[$code . '_subcategory_id'];
          $content->name = $request[$code . '_name'];
          $content->slug = createSlug($request[$code . '_name']);
          $content->address = $request[$code . '_address'];
          $content->description = Purifier::clean($request[$code . '_description']);
          $content->meta_keyword = $request[$code . '_meta_keyword'];
          $content->meta_description = $request[$code . '_meta_description'];
          $content->features = $request[$code . '_features'];
          $content->save();
        }
      }
    });

    return response()->json(['status' => 'success', 'message' => __('New service added successfully!')], 200);
  }


  public function edit($id)
  {
    $staffId = Auth::guard('sanctum_staff')->user()->id;
    $staff = Staff::select('vendor_id', 'service_edit')->where('id', $staffId)->first();

    if (!$staff) {
      return response()->json(['success' => false, 'message' => __('Staff not found.')], 404);
    }
    if ($staff->service_edit == 0) {
      return response()->json(['success' => false, 'message' => __('You do not have permission!')], 403);
    }

    $vendorId = $staff->vendor_id;
    $current_package = VendorPermissionHelper::packagePermission($vendorId);

    if ($current_package == '[]') {
      return response()->json(['success' => false, 'message' => __('Please buy a package to use this panel!')]);
    }

    $information = [];

    $service = Services::with([
      'sliderImage',
      'content.category',
      'content.subcategory',
    ])
      ->where('vendor_id', $vendorId)
      ->where('id', $id)
      ->first();

    if (!$service) {
      return response()->json(['success' => false, 'message' => __('Service not found')], 404);
    }

    $service->service_image = asset('assets/img/services/' . $service->service_image);


    $service->slider_images = $service->sliderImage->map(function ($img) {
      return [
        'id' => $img->id,
        'image' => asset('assets/img/services/service-gallery/' . $img->image)
      ];
    })->values();

    unset($service->sliderImage);

    $information['service'] = $service;
    $information['languages'] = Language::with([
      'serviceCategory' => function ($q) {
        $q->where('status', 1)
          ->orderBy('serial_number', 'asc')
          ->select('id', 'name', 'slug', 'language_id');
      },
      'serviceSubCategory' => function ($q) {
        $q->where('status', 1)
          ->orderBy('serial_number', 'asc')
          ->select('id', 'name', 'slug', 'language_id');
      },
    ])->get();

    $information['languages']->each(function ($language) {
      $language->categories = $language->serviceCategory;
      $language->subcategories = $language->serviceSubCategory;
      unset($language->serviceCategory, $language->serviceSubCategory);
    });

    $information['currencyInfo'] = $this->getCurrencyInfo();

    return response()->json(['success' => true, 'data' => $information], 200);
  }

  public function update($id, ServiceUpdateRequest $request)
  {
    $service = Services::findOrFail($id);
    $remainingSliderSlots = null;

    if ($request->vendor_id != 0) {
      $current_package = VendorPermissionHelper::packagePermission($request->vendor_id);

      if ($current_package != '[]') {
        $currentServiceImg =  vendorTotalSliderImage($id);
        $limitServiceImg = $current_package->number_of_service_image;
        $remainingSliderSlots = max(0, $limitServiceImg - $currentServiceImg);

        if ($currentServiceImg > $limitServiceImg) {
          return response()->json(['status' => 'error', 'message' => __("You can't add image more than") . ' ' . $limitServiceImg . ' ' . __('images') . '!'], 422);
        }
      }
    }

    // store servic image in storage
    if ($request->hasFile('service_image')) {
      $newImage = $request->file('service_image');
      $oldImage = $service->service_image;
      $serviceImgName = UploadFile::update(public_path('assets/img/services/'), $newImage, $oldImage);
    }
    $sliders = $request->slider_images;
    if ($sliders) {
      if ($request->hasFile('slider_images')) {
        $sliderFiles = $request->file('slider_images');
        $sliderFiles = is_array($sliderFiles) ? $sliderFiles : [$sliderFiles];

        if (!is_null($remainingSliderSlots)) {
          $sliderFiles = array_slice($sliderFiles, 0, $remainingSliderSlots);
        }

        foreach ($sliderFiles as $image) {
          $sliderImagePath = UploadFile::store(public_path('assets/img/services/service-gallery/'), $image);
          ServiceImage::create([
            'image' => $sliderImagePath,
            'service_id' => $service->id
          ]);
        }
      } else {
        $pis = ServiceImage::findOrFail($sliders);
        foreach ($pis as $key => $pi) {
          $pi->service_id = $request->service_id;
          $pi->save();
        }
      }
    }

    $languages = Language::all();

    $service->update([
      'price' => $request->price,
      'zoom_meeting' => $request->zoom_meeting ?? 0,
      'calendar_status' => $request->calendar_status ?? 0,
      'vendor_id' => $request->vendor_id,
      'service_image' => $request->hasFile('service_image') ? $serviceImgName : $service->service_image,
      'status' => $request->status,
      'prev_price' => $request->prev_price,
      'max_person' => $request->person,
      'latitude' => $request->latitude,
      'longitude' => $request->longitude
    ]);
    foreach ($languages as $language) {
      $code = $language->code;
      $content = ServiceContent::where('service_id', $service->id)->where('language_id', $language->id)
        ->first();
      if (empty($content)) {
        $content = new ServiceContent();
      }

      if (
        $language->is_default == 1 ||
        $request->filled($code . '_name') ||
        $request->filled($code . '_description') ||
        $request->filled($code . '_category_id')
      ) {
        $content->language_id = $language->id;
        $content->service_id = $service->id;
        $content->category_id = $request[$code . '_category_id'];
        $content->subcategory_id = $request[$code . '_subcategory_id'];
        $content->name = $request[$code . '_name'];
        $content->slug = createSlug($request[$code . '_name']);
        $content->address = $request[$code . '_address'];
        $content->description = Purifier::clean($request[$code . '_description']);
        $content->meta_keyword = $request[$code . '_meta_keyword'];
        $content->meta_description = $request[$code . '_meta_description'];
        $content->features = $request[$code . '_features'];
        $content->save();
      }
    }

    return response()->json(['status' => 'success', 'message' => __('Service updated successfully!')], 200);
  }

  public function servicestatus(Request $request)
  {
    $user = Auth::guard('sanctum_staff')->user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
    }
    $staff = Staff::find($user->id);
    if (!$staff) {
      return response()->json(['status' => 'error', 'message' => 'Staff not found.'], 404);
    }

    $current_package = VendorPermissionHelper::packagePermission($staff->vendor_id);
    if ($current_package == '[]') {
      return response()->json(['status' => 'error', 'message' => __('Please buy a package to use this panel!')], 403);
    }

    $validator = \Validator::make($request->all(), [
      'service_id' => 'required|integer|exists:services,id',
      'status'     => 'required|in:0,1',
    ]);

    if ($validator->fails()) {
      return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
    }

    $service = Services::find($request->service_id);

    $service->update(['status' => $request->status]);

    return response()->json(['status' => 'success', 'message' => __('Status update successfully!')], 200);
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

    if ($staff->service_delete == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission!')], 403);
    }

    $service = Services::find($id);
    if (!$service) {
      return response()->json(['status' => 'error', 'message' => 'Service not found.'], 404);
    }

    $hasService = checkService($id);
    if ($hasService > 0) {
      return response()->json(['status' => 'error', 'message' => __('First reject or delete the appointment for this service!')], 422);
    }

    ServiceReview::where('service_id', $id)->delete();
    InqueryMessage::where('service_id', $id)->delete();
    Wishlist::where('service_id', $id)->delete();

    $featuredServices = ServicePromotion::where('service_id', $id)->get();
    foreach ($featuredServices as $featuredService) {
      @unlink(public_path('assets/file/attachments/service-promotion/') . $featuredService->attachment);
      @unlink(public_path('assets/file/invoices/featured/service/') . $featuredService->invoice);
      $featuredService->delete();
    }

    $appointments = ServiceBooking::where('service_id', $id)->get();
    foreach ($appointments as $appointment) {
      @unlink(public_path('assets/file/invoices/service/') . $appointment->invoice);
      @unlink(public_path('assets/file/attachments/service/') . $appointment->attachment);
      $appointment->delete();
    }

    $staffServices = StaffService::where('service_id', $id)->get();
    foreach ($staffServices as $staffService) {
      $staffService->delete();
    }

    $servicesContents = $service->content()->get();
    @unlink(public_path('assets/img/services/') . $service->service_image);
    foreach ($servicesContents as $content) {
      $content->delete();
    }

    $service->delete();

    return response()->json(['status' => 'success', 'message' => __('Service deleted successfully!')], 200);
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
    if ($staff->service_delete == 0) {
      return response()->json(['status' => 'error', 'message' => __('You do not have permission!')], 403);
    }

    $ids = $request->ids;

    foreach ($ids as $id) {
      $service = Services::findOrFail($id);
      $hasService = checkService($id);
      if ($hasService > 0) {
        session()->flash('warning', __('First reject or delete the appointment for this services!'));
        return response()->json(['status' => 'success'], 200);
      } else {
        if ($service) {
          ServiceReview::where('service_id', $id)->delete();
          InqueryMessage::where('service_id', $id)->delete();
          Wishlist::where('service_id', $id)->delete();
          /**
           * delete featured service
           */
          $featuredServices = ServicePromotion::where('service_id', $id)->get();
          foreach ($featuredServices as $featuredService) {
            // delete the attachment
            @unlink(public_path('assets/file/attachments/service-promotion/') . $featuredService->attachment);
            // delete the invoice
            @unlink(public_path('assets/file/invoices/featured/service/') . $featuredService->invoice);
            $featuredService->delete();
          }

          $appointments = ServiceBooking::where('service_id', $id)->get();

          foreach ($appointments as $appointment) {
            @unlink(public_path('assets/file/invoices/service/') . $appointment->invoice);
            @unlink(public_path('assets/file/attachments/service/') . $appointment->attachment);
            $appointment->delete();
          }

          /**
           * delete staff assign service
           */
          $staffServices = StaffService::where('service_id', $id)->get();

          foreach ($staffServices as $staffService) {
            $staffService->delete();
          }

          $servicesContent = ServiceContent::where('service_id', $service->id)->get();

          foreach ($servicesContent as $content) {
            $content->delete();
          }

          $galleries = $service->sliderImage()->get();

          foreach ($galleries as $gallery) {
            @unlink(public_path('assets/img/services/service-gallery/') . $gallery->image);
            $gallery->delete();
          }

          @unlink(public_path('assets/img/services/') . $service->service_image);
          $service->delete();
        }
      }
    }

    session()->flash('success', __('Services deleted successfully!'));
    return response()->json(['status' => 'success'], 200);
  }
  public function getCategory(Request $request)
  {
    $defaultLanguage = Language::where('is_default', 1)->first() ?? Language::first();

    $categories = ServiceCategory::select('id', 'name')
      ->where('language_id', $defaultLanguage->id)
      ->get();

    return response()->json([
      'languages'  => Language::all(),
      'categories' => $categories,
    ], 200);
  }

  public function getSubcategory(Request $request, $categoryId)
  {
    $defaultLanguage = Language::where('is_default', 1)->first() ?? Language::first();

    $categories = ServiceSubCategory::where('category_id', $categoryId)
      ->where('language_id', $defaultLanguage->id)
      ->get();

    return response()->json([
      'languages'  => Language::all(),
      'sub_categories' => $categories,
    ], 200);
  }
}
