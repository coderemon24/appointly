<?php

namespace App\Http\Controllers\Api\Vendor;

use DB;
use Auth;
use Purifier;
use Carbon\Carbon;
use App\Models\Language;
use Illuminate\Http\Request;
use App\Http\Helpers\UploadFile;
use App\Models\Services\Services;
use App\Models\Services\Wishlist;
use App\Models\Staff\StaffService;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceImage;
use App\Models\Services\ServiceReview;
use App\Models\Services\InqueryMessage;
use App\Models\Services\ServiceBooking;
use App\Models\Services\ServiceContent;
use App\Models\Services\ServiceSubCategory;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\FeaturedService\ServicePromotion;
use App\Http\Requests\Service\ServiceStoreRequest;
use App\Http\Requests\Service\ServiceUpdateRequest;

class ServiceController extends Controller
{
  public function index(Request $request)
  {
    $locale = $request->header('Accept-Language');

    $language = $locale
      ? Language::where('code', $locale)->first()
      : null;

    $language ??= Language::where('is_default', 1)->first();

    // extra safety fallback
    if (!$language) {
      $language = Language::first();
    }

    $information['langs'] = Language::all();

    $language_id = $language->id;
    $vendorId = Auth::guard('sanctum_vendor')->id();

    $services = Services::where('vendor_id', $vendorId)
      ->with([
        'content' => function ($q) use ($language_id) {
          $q->select('id', 'service_id', 'name', 'slug')
            ->where('language_id', $language_id);
        },
        'servicePromotion' => function ($q) {
          $q->select('id', 'service_id', 'payment_status', 'order_status', 'end_date')
            ->orderByDesc('id');
        }
      ])
      ->latest()
      ->paginate(10);

    $today = Carbon::today();

    // Transform paginator items properly
    $services->getCollection()->transform(function ($service) use ($today) {
      $service->service_image = asset('assets/img/services/' . $service->service_image);

      $promos = $service->servicePromotion ?? collect();

      $pending = $promos->first(function ($p) {
        return $p->order_status === 'pending' || $p->payment_status === 'pending';
      });

      if ($pending) {
        $service->featured_status = 'pending';
        $service->featured_promotion = $pending;
        return $service;
      }

      $active = $promos->first(function ($p) use ($today) {
        if (
          $p->order_status !== 'approved' ||
          $p->payment_status !== 'completed' ||
          empty($p->end_date)
        ) {
          return false;
        }

        return Carbon::parse($p->end_date)->endOfDay()->gte($today);
      });

      if ($active) {
        $service->featured_status = 'active';
        $service->featured_promotion = $active;
        return $service;
      }

      $service->featured_status = 'pay';
      $service->featured_promotion = null;

      return $service;
    });

    $information['services'] = $services;
    $information['currencyInfo'] = $this->getCurrencyInfo();

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }

  //crate
  public function create()
  {
    $languages = Language::all();
    $information['currencyInfo'] = $this->getCurrencyInfo();

    $languages->map(function ($language) {
      $language->categories = $language->serviceCategory()
        ->where('status', 1)
        ->orderBy('serial_number', 'asc')
        ->select('id', 'name', 'slug')
        ->get();
    });

    $information['languages'] = $languages;

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }
  public function getSucategory(Request $request, $categoryId)
  {
    $categories = ServiceSubCategory::where('category_id', $categoryId)->get();
    return response()->json(
      [
        'status' => true,
        'data' => $categories
      ]
    );
  }

  public function store(ServiceStoreRequest $request)
  {
    DB::transaction(function () use ($request) {
      // store featured image in storage
      $service_image = UploadFile::store(public_path('assets/img/services/'), $request->file('service_image'));

      $service = Services::create([
        'price' => $request->price,
        'zoom_meeting' => $request->zoom_meeting ?? 0,
        'calendar_status' => $request->calender_status ?? 0,
        'service_image' => $service_image,
        'vendor_id' => Auth::guard('sanctum_vendor')->user()->id,
        'status' => $request->status,
        'prev_price' => $request->prev_price,
        'max_person' => $request->person,
        'latitude' => $request->latitude,
        'longitude' => $request->longitude
      ]);
      //store slider image
      foreach ($request->slider_images as $key => $image) {
        $sliderImagePath = UploadFile::store(public_path('assets/img/services/service-gallery/'), $image);
        ServiceImage::create([
          'image' => $sliderImagePath,
          'service_id' => $service->id
        ]);
      }

      $languages = Language::all();
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
          $content->address = $request[$code . '_address'];
          $content->slug = createSlug($request[$code . '_name']);
          $content->description = Purifier::clean($request[$code . '_description']);
          $content->meta_keyword = $request[$code . '_meta_keyword'];
          $content->meta_description = $request[$code . '_meta_description'];
          $content->features = $request[$code . '_features'];
          $content->save();
        }
      }
    });

    return response()->json([
      'success' => true,
      'message' => __('Service created successfully')
    ]);
  }

  //edit
  public function edit($id)
  {
    $language = Language::query()->where('is_default', 1)->first();
    $vendor = Auth::guard('sanctum_vendor')->user();

    $vendorId = $vendor->id;
    $current_package = VendorPermissionHelper::packagePermission($vendorId);

    if ($current_package == '[]') {
      return response()->json([
        'success' => false,
        'message' => __('Please buy a package to use this panel!')
      ]);
    }


    $information = [];
    // Get service info
    $service = Services::with([
      'sliderImage',
      'content.category',
      'content.subcategory',
    ])
      ->where('vendor_id', $vendorId)
      ->where('id', $id)
      ->first();

    if (!$service) {
      return response()->json([
        'success' => false,
        'message' => __('Service not found')
      ], 404);
    }



    // service address
    $mapStatus = Basic::query()->value('google_map_status');
    if ((int)$mapStatus === 1) {
      $information['service_address'] = ServiceContent::select('address')
        ->where('service_id', $id)
        ->first();
    }

    // service featured image URL (don’t overwrite the original if you may update later)
    $service->service_image_url = asset('assets/img/services/' . $service->service_image);

    // slider images URL list (NEW KEY to avoid double/overwrite)
    $service->slider_images = $service->sliderImage->map(function ($img) {
      return asset('assets/img/services/service-gallery/' . $img->image);
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

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }



  //update
  public function update($id, ServiceUpdateRequest $request)
  {
    $service = Services::find($id);
    if (!$service) {
      return response()->json([
        'success' => false,
        'message' => __('Service not found')
      ], 404);
    }

    $remainingSliderSlots = null;
    $vendorId = Auth::guard('sanctum_vendor')->user()->id;
    $currentPackage = VendorPermissionHelper::packagePermission($vendorId);

    if ($currentPackage != '[]') {
      $currentServiceImg = vendorTotalSliderImage($id);
      $limitServiceImg = $currentPackage->number_of_service_image;
      $remainingSliderSlots = max(0, $limitServiceImg - $currentServiceImg);
    }

    // store servic image in storage
    if ($request->hasFile('service_image')) {
      $newImage = $request->file('service_image');
      $oldImage = $service->service_image;
      $serviceImgName = UploadFile::update(public_path('assets/img/services/'), $newImage, $oldImage);
    }

    //store slider image
    if ($request->slider_images) {
      $sliderFiles = $request->slider_images;
      $sliderFiles = is_array($sliderFiles) ? $sliderFiles : [$sliderFiles];

      if (!is_null($remainingSliderSlots)) {
        $sliderFiles = array_slice($sliderFiles, 0, $remainingSliderSlots);
      }

      foreach ($sliderFiles as $key => $image) {
        $sliderImagePath = UploadFile::store(public_path('assets/img/services/service-gallery/'), $image);
        ServiceImage::create([
          'image' => $sliderImagePath,
          'service_id' => $service->id
        ]);
      }
    }


    $languages = Language::all();
    $service->update([
      'price' => $request->price,
      'zoom_meeting' => $request->zoom_meeting ?? 0,
      'calendar_status' => $request->calender_status ?? 0,
      'vendor_id' => $vendorId,
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
        $content->address = $request[$code . '_address'];
        $content->slug = createSlug($request[$code . '_name']);
        $content->description = Purifier::clean($request[$code . '_description']);
        $content->meta_keyword = $request[$code . '_meta_keyword'];
        $content->meta_description = $request[$code . '_meta_description'];
        $content->features = $request[$code . '_features'];
        $content->save();
      }
    }
    return response()->json([
      'success' => true,
      'message' => __('Service updated successfully')
    ]);
  }


  public function destroy($id)
  {
    $hasService = checkService($id);

    if ($hasService > 0) {
      return response()->json([
        'success' => false,
        'message' => __('First reject or delete the appointment for this service!')
      ], 400);
    } else {
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
      /**
       *delete from appointment
       */
      $appointments = ServiceBooking::where('service_id', $id)->get();
      foreach ($appointments as $appointment) {
        @unlink(public_path('assets/file/invoices/service/') . $appointment->invoice);
        @unlink(public_path('assets/file/attachments/service/') . $appointment->attachment);
        $appointment->delete();
      }

      ServiceReview::where('service_id', $id)->delete();
      InqueryMessage::where('service_id', $id)->delete();
      Wishlist::where('service_id', $id)->delete();

      /**
       * delete staff assign service
       */
      $staffServices = StaffService::where('service_id', $id)->get();

      foreach ($staffServices as $staffService) {
        $staffService->delete();
      }

      /**
       * delete service slider image
       */
      $service = Services::find($id);
      $servicesContents = $service->content()->get();
      // delete the service_image
      @unlink(public_path('assets/img/services/') . $service->service_image);
      $galleries = $service->sliderImage()->get();

      foreach ($galleries as $gallery) {
        @unlink(public_path('assets/img/services/service-gallery/') . $gallery->image);
        $gallery->delete();
      }

      /**
       * delete the service_image
       */
      @unlink(public_path('assets/img/services/') . $service->service_image);

      foreach ($servicesContents as $content) {
        $content->delete();
      }

      $service->delete();

      return response()->json([
        'success' => true,
        'message' => __('Service deleted successfully')
      ]);
    }
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $hasService = checkService($id);
      if ($hasService > 0) {
        return response()->json([
          'success' => false,
          'message' => __('First reject or delete the appointment for this services!')
        ], 400);
      } else {
        /**
         * delete from appointment
         */
        $appointments = ServiceBooking::where('service_id', $id)->get();

        foreach ($appointments as $appointment) {
          @unlink(public_path('assets/file/invoices/service/') . $appointment->invoice);
          @unlink(public_path('assets/file/attachments/service/') . $appointment->attachment);
          $appointment->delete();
        }

        $service = Services::where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)->find($id);

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


          $servicesContent = ServiceContent::where('service_id', $service->id)->get();

          /**
           * delete staff assign service
           */
          $staffServices = StaffService::where('service_id', $id)->get();

          foreach ($staffServices as $staffService) {
            $staffService->delete();
          }

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

    return response()->json([
      'success' => true,
      'message' => __('Services deleted successfully')
    ]);
  }


  public function servicestatus(Request $request)
  {
    $current_package = VendorPermissionHelper::packagePermission(Auth::guard('sanctum_vendor')->user()->id);

    if ($current_package == '[]') {
      return response()->json([
        'success' => false,
        'message' => __('Please buy a package to use this panel!')
      ]);
    } else {
      $staff = Services::where('id', $request->service_id)->first();

      $staff->update([
        'status' => $request->status,
      ]);
      return response()->json([
        'success' => true,
        'message' => __('Status update successfully!')
      ]);
    }
  }

  public function message(Request $request)
  {
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $language_id = $language->id;
    $information['langs'] = Language::all();

    $information['messages'] = InqueryMessage::with(['serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->orderBy('id', 'DESC')
      ->paginate(10);

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }

  public function messageDestroy($id)
  {
    InqueryMessage::where('id', $id)
      ->where('vendor_id', Auth::guard('sanctum_vendor')->user()->id)
      ->delete();

    return response()->json([
      'success' => true,
      'message' => __('Message delete successfully!')
    ]);
  }
}
