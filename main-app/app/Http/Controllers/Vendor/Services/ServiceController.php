<?php

namespace App\Http\Controllers\Vendor\Services;

use DB;
use Auth;
use Session;
use Purifier;
use Validator;
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
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\PaymentGateway\OfflineGateway;
use App\Models\FeaturedService\ServicePromotion;
use App\Http\Requests\Service\ServiceStoreRequest;
use App\Http\Requests\Service\ServiceUpdateRequest;
use App\Models\FeaturedService\FeaturedServiceCharge;

class ServiceController extends Controller
{
  public function index(Request $request)
  {
    $language = Language::where('code', $request->language)->firstOrFail();

    $information['langs'] = Language::all();
    $language_id = $language->id;

    $information['services'] = Services::where('vendor_id', Auth::guard('vendor')->user()->id)->with([
      'content' => function ($q) use ($language_id) {
        $q->where('language_id', $language_id);
      },
    ])
      ->orderBy('id', 'desc')
      ->get();

    $information['promotionList'] = FeaturedServiceCharge::all();

    $information['currencyInfo'] = $this->getCurrencyInfo();

    $information['online_gateways'] = OnlineGateway::where('status', 1)->get();
    $information['offline_gateways'] = OfflineGateway::where('status', 1)->orderBy('serial_number', 'asc')->get();

    $information['themeInfo'] = DB::table('basic_settings')->select('theme_version')->first();

    //Strip payment
    $stripe = OnlineGateway::where('keyword', 'stripe')->first();
    $stripe_info = json_decode($stripe->information, true);
    $information['stripe_key'] = $stripe_info['key'];


    //Authorize.Net payment
    $authorizeNet = OnlineGateway::where('keyword', 'authorize.net')->first();
    $authorizeInfo = json_decode($authorizeNet->information, true);

    if ($authorizeInfo['sandbox_check'] == 1) {
      $information['authorizeUrl'] = 'https://jstest.authorize.net/v1/Accept.js';
    } else {
      $information['authorizeUrl'] = 'https://js.authorize.net/v1/Accept.js';
    }

    $information['authorize_login_id'] = $authorizeInfo['login_id'];
    $information['authorize_public_key'] = $authorizeInfo['public_key'];


    return view('vendors.services.index', $information);
  }


  public function create()
  {
    $information['languages'] = Language::all();
    $information['currencyInfo'] = $this->getCurrencyInfo();

    return view('vendors.services.create', $information);
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
      return $pi->id;
    } else {
      return 'false';
    }
  }

  //imagedbrmv
  public function imagedbrmv(Request $request)
  {
    $pi = ServiceImage::findOrFail($request->fileid);
    $image_count = ServiceImage::where('service_id', $pi->service_id)->get()->count();
    if ($image_count > 1) {
      @unlink(public_path('assets/img/services/service-gallery/') . $pi->image);
      $pi->delete();
      Session::flash('success',  __('Slider image deleted successfully!'));
      return 'success';
    } else {
      Session::flash('warning',  __("You can't delete all images"));
      return 'success';
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
        'calendar_status' => $request->calender_status ?? 0,
        'service_image' => $service_image,
        'vendor_id' => Auth::guard('vendor')->user()->id,
        'status' => $request->status,
        'prev_price' => $request->prev_price,
        'max_person' => $request->person,
        'latitude' => $request->latitude,
        'longitude' => $request->longitude
      ]);

      //store slider image
      $sliders = $request->slider_images;
      if ($sliders) {
        $pis = ServiceImage::findOrFail($sliders);
        foreach ($pis as $key => $pi) {
          $pi->service_id = $service->id;
          $pi->save();
        }
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

    Session::flash('success',  __('New service added successfully!'));
    return 'success';
  }

  public function edit($id)
  {
    $language = Language::query()->where('is_default', '=', 1)->first();
    $current_package = VendorPermissionHelper::packagePermission(Auth::guard('vendor')->user()->id);

    if ($current_package == '[]') {
      return redirect()->route('vendor.service_managment', ['language' => $language->code]);
    } else {

      $mapStatus = Basic::pluck('google_map_status')->first();
      if ($mapStatus == 1) {
        $information['service_address'] = ServiceContent::select('address')->where('service_id', $id)->first();
      }
      $information['service'] = Services::with('sliderImage')->where('vendor_id', Auth::guard('vendor')->user()->id)->findOrFail($id);

      $information['languages'] = Language::all();
      $information['currencyInfo'] = $this->getCurrencyInfo();

      return view('vendors.services.edit', $information);
    }
  }


  public function update($id, ServiceUpdateRequest $request)
  {
    $service = Services::findOrFail($id);

    // store servic image in storage
    if ($request->hasFile('service_image')) {
      $newImage = $request->file('service_image');
      $oldImage = $service->service_image;
      $serviceImgName = UploadFile::update(public_path('assets/img/services/'), $newImage, $oldImage);
    }
    $sliders = $request->slider_images;
    if ($sliders) {
      $pis = ServiceImage::findOrFail($sliders);
      foreach ($pis as $key => $pi) {
        $pi->service_id = $request->service_id;
        $pi->save();
      }
    }

    $languages = Language::all();

    $service->update([
      'price' => $request->price,
      'zoom_meeting' => $request->zoom_meeting ?? 0,
      'calendar_status' => $request->calender_status ?? 0,
      'vendor_id' => Auth::guard('vendor')->user()->id,
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
    Session::flash('success',  __('Service updated successfully!'));
    return 'success';
  }


  public function destroy($id)
  {
    $hasService = checkService($id);

    if ($hasService > 0) {
      return redirect()->back()->with('warning',  __('First reject or delete the appointment for this service!'));
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
      $service = Services::findOrFail($id);
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

      return redirect()->back()->with('success',  __('Service deleted successfully!'));
    }
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $hasService = checkService($id);
      if ($hasService > 0) {
        session()->flash('warning',  __('First reject or delete the appointment for this services!'));
        return response()->json(['status' => 'success'], 200);
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

        $service = Services::where('vendor_id', Auth::guard('vendor')->user()->id)->findOrFail($id);

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

    session()->flash('success',  __('Services deleted successfully!'));
    return response()->json(['status' => 'success'], 200);
  }

  public function servicestatus(Request $request)
  {
    $current_package = VendorPermissionHelper::packagePermission(Auth::guard('vendor')->user()->id);

    if ($current_package == '[]') {
      session::flash('warning',  __('Please buy a package to use this panel!'));
      return redirect()->back();
    } else {
      $staff = Services::where('id', $request->service_id)->first();

      $staff->update([
        'status' => $request->status,
      ]);
      session()->flash('success',  __('Status update successfully!'));
      return back();
    }
  }

  //onlineSuccess
  public function onlineSuccess()
  {
    return view('vendors.services.online-success');
  }
  public function offlineSuccess()
  {
    return view('vendors.services.offline-success');
  }
  public function getSucategory(Request $request, $categoryId)
  {
    $categories = ServiceSubCategory::where('category_id', $categoryId)->get();
    return response()->json(['successData' => $categories], 200);
  }
}
