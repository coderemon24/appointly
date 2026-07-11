<?php

namespace App\Http\Controllers\Api;

use Auth;
use Response;
use Carbon\Carbon;
use App\Models\Admin;
use App\Models\Vendor;
use App\Models\Language;
use App\Models\Staff\Staff;
use Illuminate\Http\Request;
use App\Models\Staff\StaffDay;
use App\Http\Helpers\GeoSearch;
use App\Http\Helpers\BasicMailer;
use App\Models\Services\Services;
use App\Models\Staff\StaffContent;
use App\Models\Staff\StaffHoliday;
use App\Models\Staff\StaffService;
use Illuminate\Support\Facades\DB;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Admin\AdminGlobalDay;
use App\Models\Staff\StaffGlobalDay;
use App\Models\Staff\StaffGlobalHour;
use App\Http\Helpers\CheckLimitHelper;
use App\Models\Services\ServiceReview;
use App\Models\Staff\StaffServiceHour;
use App\Models\Services\InqueryMessage;
use App\Models\Services\ServiceBooking;
use App\Models\Services\ServiceContent;
use App\Models\Services\ServiceCategory;
use App\Models\Staff\StaffGlobalHoliday;
use Illuminate\Support\Facades\Validator;
use App\Models\BasicSettings\MailTemplate;
use App\Models\Services\ServiceSubCategory;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\PaymentGateway\OfflineGateway;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Controllers\StaffDistanceController;
use App\Http\Controllers\FrontEnd\MiscellaneousController;

class ServiceController extends Controller
{
  /**
   * services page
   */
  public function index(Request $request)
  {
    $misc = new MiscellaneousController();

    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $data['bgImg'] = asset('assets/img/' . $misc->getBreadcrumb()->breadcrumb);
    $data['pageHeading'] = $misc->getPageHeading($language);
    //active or deactive vendor check
    $vendorStatus = Vendor::where('status', 1)->select('id')->get()->toArray();


    //service count category wise
    $categories = ServiceCategory::where('language_id', $language->id)->get();
    foreach ($categories as $category) {
      $serviceCount = Services::join(
        'service_contents',
        'service_contents.service_id',
        '=',
        'services.id',
      )
        ->where(function ($query) use ($vendorStatus) {
          $query->whereIn('services.vendor_id', $vendorStatus)
            ->orWhere('services.vendor_id', 0);
        })
        ->when('services.vendor_id' != '0', function ($query) {
          return $query
            ->leftJoin('memberships', 'services.vendor_id', '=', 'memberships.vendor_id')
            ->where(function ($query) {
              $query
                ->where([
                  ['memberships.status', '=', 1],
                  ['memberships.start_date', '<=', now()->format('Y-m-d')],
                  ['memberships.expire_date', '>=', now()->format('Y-m-d')],
                ])
                ->orWhere('services.vendor_id', '=', 0);
            });
        })
        ->where('service_contents.category_id', $category->id)
        ->where('services.status', 1)
        ->where('service_contents.language_id', $category->language_id)
        ->count();
    }
    $data['categories'] = $categories;
    $location  = $serviceName = $category = $sort = null;

    //search by category
    $category_serviceId = [];
    if ($request->filled('category')) {
      $category = $request->category;
      $category_content = ServiceCategory::where([['language_id', $language->id], ['slug', $category]])->first();

      if (!empty($category_content)) {
        $category_id = $category_content->id;
        $contents = ServiceContent::where('language_id', $language->id)
          ->where('category_id', $category_id)
          ->get()
          ->pluck('service_id');
        foreach ($contents as $content) {
          if (!in_array($content, $category_serviceId)) {
            array_push($category_serviceId, $content);
          }
        }
      }
    }

    //sort
    if ($request->filled('sort_val')) {
      $sort = $request['sort_val'];
    }

    //search by location
    $serviceIds = [];
    $lat_long = [];
    $bs = Basic::select('google_map_status', 'google_map_radius', 'google_map_api_key')->first();
    $radius = $bs->google_map_status == 1 ? $bs->google_map_radius : 5000;

    if ($request->filled('location')) {
      $location = $request->location;

      if ($bs->google_map_status == 1) {
        $lat_long = GeoSearch::getCoordinates($location, $bs->google_map_api_key);
      } else {
        $serviceIds = ServiceContent::Where('language_id', $language->id)
          ->where('address', 'like', '%' . $location . '%')
          ->distinct()
          ->pluck('service_id')
          ->toArray();
      }
    }

    //search by service name
    $serviceNameIds = [];
    if ($request->filled('service_title')) {
      $serviceName = $request->service_title;

      $contents = ServiceContent::where('language_id', $language->id)
        ->where('name', 'like', '%' . $serviceName . '%')
        ->get()
        ->pluck('service_id');
      foreach ($contents as $content) {
        if (!in_array($content, $serviceNameIds)) {
          array_push($serviceNameIds, $content);
        }
      }
    }

    $featuredServices = Services::join('service_promotions', 'service_promotions.service_id', '=', 'services.id')
      ->join('service_contents', 'services.id', '=', 'service_contents.service_id')
      ->join('service_categories', 'service_categories.id', '=', 'service_contents.category_id')
      ->where('service_contents.language_id', $language->id)
      ->where('services.status', 1)
      ->where(function ($query) use ($vendorStatus) {
        $query->whereIn('services.vendor_id', $vendorStatus)
          ->orWhere('services.vendor_id', 0);
      })
      ->where('service_promotions.order_status', '=', 'approved')
      ->where('service_promotions.payment_status', '=', 'completed')
      ->whereDate('service_promotions.end_date', '>=', Carbon::now()->format('Y-m-d'))
      ->when('services.vendor_id' != "0", function ($query) {
        return $query->leftJoin('memberships', 'services.vendor_id', '=', 'memberships.vendor_id')
          ->where(function ($query) {
            $query->where([
              ['memberships.status', '=', 1],
              ['memberships.start_date', '<=', now()->format('Y-m-d')],
              ['memberships.expire_date', '>=', now()->format('Y-m-d')],
            ])->orWhere('services.vendor_id', '=', 0);
          });
      })
      ->when($category, function ($query) use ($category_serviceId) {
        return $query->whereIn('services.id', $category_serviceId);
      })
      ->when(($location && $bs->google_map_status == 0), function ($query) use ($serviceIds) {
        return $query->whereIn('services.id', $serviceIds);
      })
      ->when($serviceName, function ($query) use ($serviceNameIds) {
        return $query->whereIn('services.id', $serviceNameIds);
      })
      ->select(
        'services.*',
        'service_contents.name',
        'service_contents.slug',
        'service_contents.address',
        'service_categories.name as categoryName',
        'service_categories.id as categoryid',
        'service_categories.slug as categorySlug',
        'service_categories.icon as categoryIcon',
      )
      ->inRandomOrder()
      ->get()
      ->map(function ($featured_service) {
        $featured_service->service_image = asset('assets/img/services/' . $featured_service->service_image);
        $featured_service->formatted_price = symbolPrice($featured_service->price);
        $featured_service->formatted_prev_price = symbolPrice($featured_service->prev_price);
        return $featured_service;
      });

    //condition for geo location search
    if ($bs->google_map_status == 1) {
      if ($location && is_array($lat_long) && isset($lat_long['lat'], $lat_long['lng'])) {
        $featuredServices = $featuredServices->transform(function ($item) use ($lat_long) {
          $item->distance = GeoSearch::getDistance($item->latitude, $item->longitude, $lat_long['lat'], $lat_long['lng']);
          return $item;
        })->filter(function ($item) use ($radius) {
          $item =  floatval($item->distance) <= $radius;
          return $item;
        })->values()
          ->sortBy('distance')
          ->take(3);
      } elseif ($location && (!isset($lat_long['lat']) || !isset($lat_long['lng']))) {
        $featuredServices = collect();
      } elseif (!$location && (!isset($lat_long['lat']) || !isset($lat_long['lng']))) {
        $featuredServices = $featuredServices->take(3);
      }
    } else {
      $featuredServices = $featuredServices->take(3);
    }



    $data['featuredServices'] = $featuredServices;
    $numFeaturedServices = $featuredServices->count();
    $numRegularServices = max(0, 12 - $numFeaturedServices);

    $featuredServiceIds = $featuredServices->pluck('id')->toArray();
    $services = Services::with('vendor')
      ->join('service_contents', 'services.id', '=', 'service_contents.service_id')
      ->join('service_categories', 'service_categories.id', '=', 'service_contents.category_id')
      ->where('service_contents.language_id', $language->id)
      ->where('services.status', 1)
      ->whereNotIn('services.id', $featuredServiceIds)
      ->where(function ($query) use ($vendorStatus) {
        $query->whereIn('services.vendor_id', $vendorStatus)
          ->orWhere('services.vendor_id', 0);
      })
      ->when('services.vendor_id' != "0", function ($query) {
        return $query->leftJoin('memberships', 'services.vendor_id', '=', 'memberships.vendor_id')
          ->where(function ($query) {
            $query->where([
              ['memberships.status', '=', 1],
              ['memberships.start_date', '<=', now()->format('Y-m-d')],
              ['memberships.expire_date', '>=', now()->format('Y-m-d')],
            ])->orWhere('services.vendor_id', '=', 0);
          });
      })
      ->when($category, function ($query) use ($category_serviceId) {
        return $query->whereIn('services.id', $category_serviceId);
      })
      ->when(($location && $bs->google_map_status == 0), function ($query) use ($serviceIds) {
        return $query->whereIn('services.id', $serviceIds);
      })
      ->when($serviceName, function ($query) use ($serviceNameIds) {
        return $query->whereIn('services.id', $serviceNameIds);
      })
      ->select(
        'services.*',
        'service_contents.name',
        'service_contents.slug',
        'service_contents.address',
        'service_categories.name as categoryName',
        'service_categories.id as categoryid',
        'service_categories.slug as categorySlug',
        'service_categories.icon as categoryIcon',
      )
      ->when($sort, function ($query, $sort) {
        if ($sort == 'newest') {
          return $query->orderBy('services.created_at', 'desc');
        } else if ($sort == 'old') {
          return $query->orderBy('services.created_at', 'asc');
        } else if ($sort == 'high-to-low') {
          return $query->orderBy('services.price', 'desc');
        } else if ($sort == 'low-to-high') {
          return $query->orderBy('services.price', 'asc');
        }
      }, function ($query) {
        return $query->orderBy('services.created_at', 'asc');
      });

    //condition for geo location search
    if ($bs->google_map_status == 1) {
      if ($location && is_array($lat_long) && isset($lat_long['lat'], $lat_long['lng'])) {
        $services = $services->get()->map(function ($item) use ($lat_long) {
          $item->distance = round(GeoSearch::getDistance(
            $item->latitude,
            $item->longitude,
            $lat_long['lat'],
            $lat_long['lng']
          ));
          return $item;
        })->filter(function ($item) use ($radius) {
          $item =  floatval($item->distance) <= $radius;
          return $item;
        });

        $services = $request->filled('sort_val') && $request->input('sort_val') == 'distance-away'
          ? $services->sortByDesc('distance')
          : $services->sortBy('distance');
      } elseif ($location && (!isset($lat_long['lat']) || !isset($lat_long['lng']))) {
        $services = collect();
      } elseif (!$location && (!isset($lat_long['lat']) || !isset($lat_long['lng']))) {
        $services = $services->get();
      }
    } else {
      $services = $services->get();
    }

    $data['services'] = $services->transform(function ($service) {
      $service->service_image = asset('assets/img/services/' . $service->service_image);
      $service->formatted_price = symbolPrice($service->price);
      $service->formatted_prev_price = symbolPrice($service->prev_price);

      if (isset($service->vendor->photo)) {
        if (!str_starts_with($service->vendor->photo, 'http')) {
          $service->vendor->photo = asset('assets/admin/img/vendor-photo/' . $service->vendor->photo);
        }
      }

      return $service;
    });


    $featuredServiceCount = $featuredServices->count();
    $serviceCount = $services->count();
    $data['total_services'] = $featuredServiceCount + $serviceCount;

    $featuredAddresses = $featuredServices->pluck('address');
    $regularAddresses = $services->pluck('address');
    $data['allAddresses'] = $featuredAddresses->merge($regularAddresses)->unique()->toArray();
    //admin info
    $data['admin'] = Admin::whereNull('role_id')->first();

    //sidebara search
    $data['min'] = Services::where('status', 1)->min('price');
    $data['max'] = Services::where('status', 1)->max('price');

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }

  /**
   * service search pagination & sort
   */
  public function searchService(Request $request)
  {
    $misc = new MiscellaneousController();
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();
    //admin info
    $data['admin'] = Admin::whereNull('role_id')->first();
    $data['bgImg'] = $misc->getBreadcrumb();


    $min = $max = $category = $rating = $sort = $location = $service_type = $serviceName = $subcategory = null;

    //search by category
    $category_serviceId = [];
    if ($request->filled('category')) {
      $category = $request->category;
      $category_content = ServiceCategory::where([['language_id', $language->id], ['slug', $category]])->select('id')->first();
      if (!empty($category_content)) {
        $category_id = $category_content->id;
        $contents = ServiceContent::where('language_id', $language->id)
          ->where('category_id', $category_id)
          ->get()
          ->pluck('service_id');
        foreach ($contents as $content) {
          if (!in_array($content, $category_serviceId)) {
            array_push($category_serviceId, $content);
          }
        }
      }
    }

    //search by subcategory
    $subcategory_serviceId = [];
    if ($request->filled('subcategory')) {
      $subcategory = $request->subcategory;
      $sub_content = ServiceSubCategory::where([['language_id', $language->id], ['slug', $subcategory]])->select('id')->first();
      if (!empty($sub_content)) {
        $subcategory_id = $sub_content->id;
        $contents = ServiceContent::where('language_id', $language->id)
          ->where('subcategory_id', $subcategory_id)
          ->get()
          ->pluck('service_id');
        foreach ($contents as $content) {
          if (!in_array($content, $subcategory_serviceId)) {
            array_push($subcategory_serviceId, $content);
          }
        }
      }
    }
    //search by price
    $priceIds = [];
    if ($request->filled('min_val') && $request->filled('max_val')) {
      $min = intval($request->min_val);
      $max = intval(($request->max_val));
      $price_servicess = DB::table('services')
        ->select('*')
        ->where('price', '>=', $min)
        ->where('price', '<=', DB::raw($max))
        ->get();
      foreach ($price_servicess as $service) {
        if (!in_array($service->id, $priceIds)) {
          array_push($priceIds, $service->id);
        }
      }
    }
    //search by location
    $serviceIds = [];
    $lat_long = [];
    $bs = Basic::select('google_map_status', 'google_map_radius', 'google_map_api_key')->first();
    $radius = $bs->google_map_status == 1 ? $bs->google_map_radius : 5000;

    if ($request->filled('location_val')) {
      $location = $request->location_val;

      if ($bs->google_map_status == 1) {
        $lat_long = GeoSearch::getCoordinates($location, $bs->google_map_api_key);
      } else {
        $serviceIds = ServiceContent::Where('language_id', $language->id)
          ->where('address', 'like', '%' . $location . '%')
          ->distinct()
          ->pluck('service_id')
          ->toArray();
      }
    }

    //search by service name
    $serviceNameIds = [];
    if ($request->filled('service_title')) {
      $serviceName = $request->service_title;

      $contents = ServiceContent::where('language_id', $language->id)
        ->where('name', 'like', '%' . $serviceName . '%')
        ->get()
        ->pluck('service_id');
      foreach ($contents as $content) {
        if (!in_array($content, $serviceNameIds)) {
          array_push($serviceNameIds, $content);
        }
      }
    }

    //search by service type
    $serviceTypeId = [];
    if ($request->filled('service_type')) {
      $service_type = $request->service_type;

      if ($service_type == 'online') {
        $services = Services::where('zoom_meeting', 1)->get()->pluck('id');
      } elseif ($service_type == 'offline') {
        $services = Services::where('zoom_meeting', 0)->get()->pluck('id');
      } else {
        $services = Services::query()->get()->pluck('id');
      }
      foreach ($services as $service) {
        if (!in_array($service, $serviceTypeId)) {
          array_push($serviceTypeId, $service);
        }
      }
    }

    //search by rating
    if ($request->filled('rating')) {
      $rating = floatval($request['rating']);
    }
    if ($request->filled('sort_val')) {
      $sort = $request['sort_val'];
    }

    $vendorStatus = Vendor::where('status', 1)->select('id')->get()->toArray();

    //featured services
    $featuredServices = Services::join('service_promotions', 'service_promotions.service_id', '=', 'services.id')
      ->join('service_contents', 'services.id', '=', 'service_contents.service_id')
      ->join('service_categories', 'service_categories.id', '=', 'service_contents.category_id')
      ->where('service_contents.language_id', $language->id)
      ->where('services.status', 1)
      ->where(function ($query) use ($vendorStatus) {
        $query->whereIn('services.vendor_id', $vendorStatus)
          ->orWhere('services.vendor_id', 0);
      })
      ->where('service_promotions.order_status', '=', 'approved')
      ->whereDate('service_promotions.end_date', '>=', Carbon::now()->format('Y-m-d'))
      ->when('services.vendor_id' != "0", function ($query) {
        return $query->leftJoin('memberships', 'services.vendor_id', '=', 'memberships.vendor_id')
          ->where(function ($query) {
            $query->where([
              ['memberships.status', '=', 1],
              ['memberships.start_date', '<=', now()->format('Y-m-d')],
              ['memberships.expire_date', '>=', now()->format('Y-m-d')],
            ])->orWhere('services.vendor_id', '=', 0);
          });
      })
      ->when($category, function ($query) use ($category_serviceId) {
        return $query->whereIn('services.id', $category_serviceId);
      })
      ->when($subcategory, function ($query) use ($subcategory_serviceId) {
        return $query->whereIn('services.id', $subcategory_serviceId);
      })
      ->when(($min && $max), function ($query) use ($priceIds) {
        return $query->whereIn('services.id', $priceIds);
      })
      ->when($rating, function ($query, $rating) {
        return $query->where('services.average_rating', '>=', $rating);
      })
      ->when($serviceName, function ($query) use ($serviceNameIds) {
        return $query->whereIn('services.id', $serviceNameIds);
      })
      ->when($service_type, function ($query) use ($serviceTypeId) {
        return $query->whereIn('services.id', $serviceTypeId);
      })
      ->when(($location && $bs->google_map_status == 0), function ($query) use ($serviceIds) {
        return $query->whereIn('services.id', $serviceIds);
      })
      ->select(
        'services.*',
        'service_contents.name',
        'service_contents.slug',
        'service_contents.address',
        'service_categories.name as categoryName',
        'service_categories.id as categoryid',
        'service_categories.slug as categorySlug',
        'service_categories.icon as categoryIcon'
      )
      ->inRandomOrder()
      ->get()->map(function ($featured_service) {
        $featured_service->service_image = asset('assets/img/services/' . $featured_service->service_image);
        $featured_service->formatted_price = symbolPrice($featured_service->price);
        $featured_service->formatted_prev_price = symbolPrice($featured_service->prev_price);
        return $featured_service;
      });

    //condition for geo location search
    if ($bs->google_map_status == 1) {
      if ($location && is_array($lat_long) && isset($lat_long['lat'], $lat_long['lng'])) {
        $featuredServices = $featuredServices->transform(function ($item) use ($lat_long) {
          $item->distance = GeoSearch::getDistance($item->latitude, $item->longitude, $lat_long['lat'], $lat_long['lng']);
          return $item;
        })->filter(function ($item) use ($radius) {
          $item =  floatval($item->distance) <= $radius;
          return $item;
        })->values()
          ->sortBy('distance')
          ->take(3);
      } elseif ($location && (!isset($lat_long['lat']) || !isset($lat_long['lng']))) {
        $featuredServices = collect();
      } elseif (!$location && (!isset($lat_long['lat']) || !isset($lat_long['lng']))) {
        $featuredServices = $featuredServices->take(3);
      }
    } else {
      $featuredServices = $featuredServices->take(3);
    }

    $data['featuredServices'] = $featuredServices;

    //logic for regular service pagination
    $numFeaturedServices = $featuredServices->count();
    $numRegularServices = max(0, 12 - $numFeaturedServices);
    $featuredServiceIds = $featuredServices->pluck('id')->toArray();

    //regular services
    $services = Services::join('service_contents', 'services.id', '=', 'service_contents.service_id')
      ->join('service_categories', 'service_categories.id', '=', 'service_contents.category_id')
      ->where('service_contents.language_id', $language->id)
      ->where(function ($query) use ($vendorStatus) {
        $query->whereIn('services.vendor_id', $vendorStatus)
          ->orWhere('services.vendor_id', 0);
      })
      ->where('services.status', 1)
      ->whereNotIn('services.id', $featuredServiceIds)
      ->when('services.vendor_id' != "0", function ($query) {
        return $query->leftJoin('memberships', 'services.vendor_id', '=', 'memberships.vendor_id')
          ->where(function ($query) {
            $query->where([
              ['memberships.status', '=', 1],
              ['memberships.start_date', '<=', now()->format('Y-m-d')],
              ['memberships.expire_date', '>=', now()->format('Y-m-d')],
            ])->orWhere('services.vendor_id', '=', 0);
          });
      })
      ->when($category, function ($query) use ($category_serviceId) {
        return $query->whereIn('services.id', $category_serviceId);
      })
      ->when($subcategory, function ($query) use ($subcategory_serviceId) {
        return $query->whereIn('services.id', $subcategory_serviceId);
      })
      ->when(($min && $max), function ($query) use ($priceIds) {
        return $query->whereIn('services.id', $priceIds);
      })
      ->when($rating, function ($query, $rating) {
        return $query->where('services.average_rating', '>=', $rating);
      })
      ->when($serviceName, function ($query) use ($serviceNameIds) {
        return $query->whereIn('services.id', $serviceNameIds);
      })
      ->when($service_type, function ($query) use ($serviceTypeId) {
        return $query->whereIn('services.id', $serviceTypeId);
      })
      ->when(($location && $bs->google_map_status == 0), function ($query) use ($serviceIds) {
        return $query->whereIn('services.id', $serviceIds);
      })
      ->select(
        'services.*',
        'service_contents.name',
        'service_contents.slug',
        'service_contents.address',
        'service_categories.name as categoryName',
        'service_categories.id as categoryid',
        'service_categories.slug as categorySlug',
        'service_categories.icon as categoryIcon'
      )->when($sort, function ($query, $sort) {
        if ($sort == 'newest') {
          return $query->orderBy('services.created_at', 'desc');
        } else if ($sort == 'old') {
          return $query->orderBy('services.created_at', 'asc');
        } else if ($sort == 'high-to-low') {
          return $query->orderBy('services.price', 'desc');
        } else if ($sort == 'low-to-high') {
          return $query->orderBy('services.price', 'asc');
        }
      }, function ($query) {
        return $query->orderBy('services.created_at', 'asc');
      });

    //condition for geo location search
    if ($bs->google_map_status == 1) {
      if ($location && is_array($lat_long) && isset($lat_long['lat'], $lat_long['lng'])) {
        $services = $services->get()->map(function ($item) use ($lat_long) {
          $item->distance = round(GeoSearch::getDistance(
            $item->latitude,
            $item->longitude,
            $lat_long['lat'],
            $lat_long['lng']
          ));
          return $item;
        })->filter(function ($item) use ($radius) {
          $item =  floatval($item->distance) <= $radius;
          return $item;
        });

        $services = $request->filled('sort_val') && $request->input('sort_val') == 'distance-away'
          ? $services->sortByDesc('distance')
          : $services->sortBy('distance');

        $services = $services->values(); // Reset keys
        $services = new LengthAwarePaginator(
          $services->forPage(request('page', 1), $numRegularServices),
          $services->count(),
          $numRegularServices,
          request('page', 1),
          ['path' => request()->url(), 'query' => request()->query()]
        );
      } elseif ($location && (!isset($lat_long['lat']) || !isset($lat_long['lng']))) {
        $services = new LengthAwarePaginator([], 0, $numRegularServices, request('page', 1), [
          'path' => request()->url(),
          'query' => request()->query(),
        ]);
      } elseif (!$location && (!isset($lat_long['lat']) || !isset($lat_long['lng']))) {
        $services = $services->paginate($numRegularServices);
      }
    } else {
      $services = $services->paginate($numRegularServices);
    }


    $data['services'] = $services->transform(function ($service) {
      $service->service_image = asset('assets/img/services/' . $service->service_image);
      $service->formatted_price = symbolPrice($service->price);
      $service->formatted_prev_price = symbolPrice($service->prev_price);
      return $service;
    });
    $data['min'] = Services::where('status', 1)->min('price');
    $data['max'] = Services::where('status', 1)->max('price');

    //Strip payment
    $stripe = OnlineGateway::where('keyword', 'stripe')->first();
    $stripe_info = json_decode($stripe->information, true);
    $data['stripe_key'] = $stripe_info['key'];

    //Authorize.Net payment
    $authorizeNet = OnlineGateway::where('keyword', 'authorize.net')->first();
    $authorizeInfo = json_decode($authorizeNet->information, true);

    if ($authorizeInfo['sandbox_check'] == 1) {
      $data['authorizeUrl'] = 'https://jstest.authorize.net/v1/Accept.js';
    } else {
      $data['authorizeUrl'] = 'https://js.authorize.net/v1/Accept.js';
    }

    $data['authorize_login_id'] = $authorizeInfo['login_id'];
    $data['authorize_public_key'] = $authorizeInfo['public_key'];


    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }

  /**
   * service details page
   */
  public function details($slug, $id, Request $request)
  {
    $misc = new MiscellaneousController();
    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();
    $data['language'] = $language;

    $currentLang =  Language::where('is_default', 1)->first();

    //admin info
    $data['admin'] = Admin::whereNull('role_id')->first();
    $data['admin']->image = asset('assets/img/admins/' . @$data['admin']->image) ?? null;
    $data['bgImg'] = asset('assets/img/' . $misc->getBreadcrumb()->breadcrumb);
    $data['pageHeading'] = $misc->getPageHeading($language);

    //service details
    $serviceDetails = Services::with([
      'content' => function ($query) use ($language) {
        return $query->where('language_id', $language->id);
      },
      'sliderImage',
      'vendor',
      'reviews' => function ($query) {
        $query->with(['user' => function ($q) {
          $q->select(
            'id',
            'username',
            'name',
            'email',
            'image',
            'address',
            'created_at',
            'country',
            'city',
            'state',
            \DB::raw("CONCAT('" . url('assets/img/users') . "/', image) as image_url")
          );
        }]);
      },
    ])
      ->with(['vendorInfo' => function ($query) use ($language) {
        return $query->where('language_id', $language->id);
      }])
      ->when('services.vendor_id' != "0", function ($query) {
        return $query->leftJoin('memberships', 'services.vendor_id', '=', 'memberships.vendor_id')
          ->where(function ($query) {
            $query->where([
              ['memberships.status', '=', 1],
              ['memberships.start_date', '<=', now()->format('Y-m-d')],
              ['memberships.expire_date', '>=', now()->format('Y-m-d')],
            ])->orWhere('services.vendor_id', '=', 0);
          });
      })
      ->where('services.status', 1)
      ->select('services.*')
      ->where('services.id', $id)
      ->first();

    //send formated price
    $serviceDetails->formatted_price = symbolPrice($serviceDetails->price);
    $serviceDetails->formatted_prev_price = symbolPrice($serviceDetails->prev_price);
    //add vendor photo
    if ($serviceDetails->vendor_id != 0) {
      $serviceDetails->vendor->photo = asset('assets/admin/img/vendor-photo/' . $serviceDetails->vendor->photo);
    }
    //add slider image path
    foreach ($serviceDetails->sliderImage as $image) {
      $image->image = asset('assets/img/services/service-gallery/' . $image->image);
    }

    if (!$serviceDetails) {
      return response()->json([
        'success' => false,
        'message' => __('Service not found')
      ]);
    }

    if ($serviceDetails->content->isEmpty()) {
      session()->put('currentLocaleCode', $currentLang->code);
      return response()->json([
        'success' => false,
        'message' => __('Content not available. Please try another language.')
      ]);
    }
    $serviceDetails->service_image = asset('assets/img/services/' . $serviceDetails->service_image);
    $data['details'] = $serviceDetails;


    //category related service
    $service_content = ServiceContent::where('language_id', $language->id)->where('service_id', $id)->first();
    if ($service_content) {
      $category_id = $service_content->category_id;
      $data['related_services'] = Services::join('service_contents', 'services.id', '=', 'service_contents.service_id')
        ->join('service_categories', 'service_contents.category_id', '=', 'service_categories.id')
        ->with('vendor')
        ->where('service_contents.language_id', $language->id)
        ->where('service_categories.language_id', $language->id)
        ->where('services.id', '!=', $id)
        ->where('services.status', 1)
        ->where('service_contents.category_id', $category_id)
        ->when('services.vendor_id' != "0", function ($query) {
          return $query->leftJoin('memberships', 'services.vendor_id', '=', 'memberships.vendor_id')
            ->where(function ($query) {
              $query->where([
                ['memberships.status', '=', 1],
                ['memberships.start_date', '<=', now()->format('Y-m-d')],
                ['memberships.expire_date', '>=', now()->format('Y-m-d')],
              ])->orWhere('services.vendor_id', '=', 0);
            });
        })
        ->select(
          'services.*',
          'service_contents.slug',
          'service_contents.name',
          'service_contents.address',
          'service_categories.name as category_name',
          'service_categories.name as category_slug',
          'service_categories.id as category_id'
        )
        ->orderBy('services.created_at', 'desc')
        ->get()
        ->map(function ($related_services) {
          $related_services->service_image = asset('assets/img/services/' . $related_services->service_image);
          $related_services->formatted_price = symbolPrice($related_services->price);
          $related_services->formatted_prev_price = symbolPrice($related_services->prev_price);

          if ($related_services->vendor && $related_services->vendor->photo) {
            $photo = $related_services->vendor->photo;

            // Check if it's already a full URL
            if (!\Str::startsWith($photo, ['http://', 'https://'])) {
              $photo = asset('assets/admin/img/vendor-photo/' . $photo);
            }

            $related_services->vendor->photo = $photo;
          }

          return $related_services;
        });
    } else {
      $data['related_services'] = collect();
    }

    $globalDaysTable = ($serviceDetails->vendor_id != 0) ? 'staff_global_days' : 'admin_global_days';

    $serviceDays = StaffGlobalHour::join($globalDaysTable, 'staff_global_hours.global_day_id', '=', $globalDaysTable . '.id')
      ->where('staff_global_hours.vendor_id', $serviceDetails->vendor_id)
      ->selectRaw('min(start_time) AS minTime, max(end_time) AS maxTime, ' . $globalDaysTable . '.indx, ' . $globalDaysTable . '.is_weekend, ' . $globalDaysTable . '.day, ' . $globalDaysTable . '.id')
      ->groupBy('staff_global_hours.global_day_id', $globalDaysTable . '.indx', $globalDaysTable . '.is_weekend', $globalDaysTable . '.day', $globalDaysTable . '.id')
      ->get();

    $reordered = $serviceDays->map(function ($item, $key) {
      return [
        'dayId' => $item['id'],
        'minTime' => $item['minTime'],
        'maxTime' => $item['maxTime'],
        'day' => $item['day'],
        'is_weekend' => $item['is_weekend'],
        'indx' => $item['indx'],
      ];
    })->sortBy('indx');
    $data['allDays'] = $reordered;
    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }


  //review store
  public function storeReview(Request $request, $id)
  {
    $rule = ['rating' => 'required'];

    $validator = Validator::make($request->all(), $rule);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => __('The rating field is required for product review')
      ]);
    }

    $serviceBooking = false;

    // get the authenticate user
    $user = Auth::user();

    // then, get the purchases of that user
    $booking = $user->serviceBooking()->where('payment_status', 'completed')->get();

    if (count($booking) > 0) {
      foreach ($booking as $bookItem) {
        if ($bookItem->service_id == $id) {
          $serviceBooking = true;
        }
      }

      if ($serviceBooking == true) {
        ServiceReview::updateOrCreate(
          ['user_id' => $user->id, 'service_id' => $id, 'vendor_id' => $request->vendor_id],
          ['comment' => $request->comment, 'rating' => $request->rating]
        );

        // now, get the average rating of this product
        $reviews = ServiceReview::where('service_id', $id)->get();

        $totalRating = 0;

        foreach ($reviews as $review) {
          $totalRating += $review->rating;
        }

        $numOfReview = count($reviews);

        $averageRating = number_format($totalRating / $numOfReview, 1);

        // finally, store the average rating of this product
        Services::find($id)->update(['average_rating' => $averageRating]);

        return response()->json([
          'success' => true,
          'message' => __('Your review submitted successfully')
        ]);
      } else {
        return response()->json([
          'success' => false,
          'message' => __('You have not bought this service yet!')
        ]);
      }
    }
    return response()->json([
      'success' => false,
      'message' => __('You have not booking anything yet')
    ]);
  }


  /**
   * show staff content
   */
  public function staffcontent($id, Request $request)
  {
    $service = Services::select('vendor_id', 'price', 'id', 'zoom_meeting', 'calendar_status')->find($id);

    //check if staff account status active or not
    $staffService = StaffService::where('service_id', $id)
      ->whereHas('staff', function ($query) {
        $query->where('status', 1);
      })
      ->count();
    $data['staffCount'] = $staffService;
    //check vendor membershiop limit
    $countAppointment = CheckLimitHelper::countAppointment($service->vendor_id);
    if ($countAppointment > 0) {
      //get language
      $locale = $request->header('Accept-Language');
      $language = $locale ? Language::where('code', $locale)->first()
        : Language::where('is_default', 1)->first();

      //staff query
      $staffQuery = Staff::join('staff_contents', 'staff.id', '=', 'staff_contents.staff_id')
        ->where('staff_contents.language_id', $language->id)
        ->where('staff.status', 1)
        ->select(
          'staff.is_day',
          'staff.id',
          'staff.email',
          'staff.image',
          'staff.email_status',
          'staff_contents.name',
          'staff.vendor_id',
          'staff.role',
          'staff.status',
          'staff.username'
        );

      $staffQueryForCheck = (clone $staffQuery);

      if ($staffQueryForCheck->whereNull('staff.role')->where('staff.vendor_id', $service->vendor_id)->count() > 0) {
        $data['staffs'] = $staffQuery->join('staff_services', 'staff.id', '=', 'staff_services.staff_id')
          ->where('staff_services.service_id', $id)
          ->whereNull('staff.role')
          ->get()
          ->map(function ($staff) {
            $staff->image = $staff->image ? asset('assets/img/staff/' . $staff->image) : '';
            return $staff;
          });
      } else {
        $data['staffs'] = $staffQuery
          ->where('staff.role', 'vendor')
          ->where('staff.vendor_id', $service->vendor_id)
          ->get()
          ->map(function ($staff) {
            $staff->image = $staff->image ? asset('assets/img/staff/' . $staff->image) : '';
            return $staff;
          });
      }

      $data['data'] = [
        'vendor_id' => $service->vendor_id,
        'service_ammount' => $service->price,
        'service_id' => $service->id,
        'zoom_status' => $service->zoom_meeting,
        'calendar_status' => $service->calendar_status,
      ];

      // Session::put('serviceData', $data);

      $data['bs'] = Basic::query()->select('google_recaptcha_status')->first();
      $data['authUser'] =  Auth::user() ?? null;
      $data['groupService'] = Services::where('id', $id)->select('max_person')->first();

      $data['online_gateways'] = OnlineGateway::where('status', 1)->get();
      $data['offline_gateways'] = OfflineGateway::where('status', 1)->orderBy('serial_number', 'desc')->get();

      /**
       * editcode
       */
      $data['staffService'] = $staffService;
      return response()->json([
        'success' => true,
        'data' => $data
      ]);
    }
    return response()->json([
      'success' => true,
      'data' => __('Appointment not available') . '.' . __('Please contact support')
    ], 422);
  }


  //show staff holiday
  public function staffHoliday($id, Request $request)
  {
    $staff = Staff::find($id);
    $vendor_id = $staff->vendor_id;

    // if (Session::has('serviceData')) {
    //   $service = Session::get('serviceData');
    // }

    $array['holiday'] = StaffHoliday::where('staff_id', $id)
      ->where('vendor_id', $vendor_id)
      ->pluck('date');

    $array['globalWeekend'] = StaffGlobalDay::where('is_weekend', 1)
      ->where('vendor_id', $vendor_id)
      ->pluck('indx');

    $array['adminGlobalWeekend'] = AdminGlobalDay::where('is_weekend', 1)
      ->pluck('indx');

    $array['staffWeekend'] = StaffDay::where('is_weekend', 1)
      ->where('staff_id', $id)
      ->where('vendor_id', $vendor_id)
      ->pluck('indx');

    $array['globalHoliday'] = StaffGlobalHoliday::where('vendor_id', $vendor_id)
      ->pluck('date');

    $array['vendor_id'] = $vendor_id;
    $array['serviceId'] = $request->service_id;

    return response()->json([
      'success' => true,
      'data' => $array
    ]);
  }


  /**
   * show service hour
   */
  public function staffHour(Request $request)
  {
    $staff = Staff::find($request->staff_id);
    $information['staff'] = $staff;

    //check auto approval booking status
    if ($request->vendor_id != 0) {
      $autoApproval = DB::table('vendors')->where('id', $request->vendor_id)->pluck('booking_type')->first();
    } else {
      $autoApproval = DB::table('basic_settings')->pluck('booking_type')->first();
    }

    // count the bookings for the same time slot
    $bookedCount = ServiceBooking::where('staff_id', $request->staff_id)
      ->where('booking_date', $request->bookingDate)
      ->when($autoApproval == 'active', function ($query) {
        $query->where('payment_status', '!=', 'rejected');
      }, function ($query) {
        $query->where('order_status', '!=', 'rejected');
      })
      ->count();


    // Retrieve staff-specific available time slots
    $information['staff_time'] = StaffServiceHour::leftJoin('staff_days', 'staff_days.id', '=', 'staff_service_hours.staff_day_id')
      ->leftJoin('service_bookings', function ($join) use ($request, $bookedCount) {
        $join->on('service_bookings.service_hour_id', '=', 'staff_service_hours.id')
          ->where('service_bookings.booking_date', '=', $request->bookingDate)
          ->where(function ($query) use ($bookedCount) {
            $query->where('staff_service_hours.max_booking', '<=', $bookedCount);
          })
          ->where('service_bookings.staff_id', '=', $request->staff_id);
      })
      ->whereNull('service_bookings.id')
      ->where('staff_days.staff_id', $request->staff_id)
      ->where('staff_service_hours.staff_id', $request->staff_id)
      ->where('staff_days.day', $request->dayName)
      ->select('staff_days.day', 'staff_service_hours.start_time', 'staff_service_hours.end_time', 'staff_service_hours.id', 'staff_service_hours.staff_id', 'staff_service_hours.max_booking')
      ->get();

    if ($request->vendor_id != 0) {
      // Retrieve vendor-specific available time slots
      $information['global_time'] = StaffGlobalHour::join('staff_global_days', 'staff_global_days.id', '=', 'staff_global_hours.global_day_id')
        ->leftJoin('service_bookings', function ($join) use ($request, $bookedCount) {
          $join->on('service_bookings.service_hour_id', '=', 'staff_global_hours.id')
            ->where('service_bookings.booking_date', '=', $request->bookingDate)
            ->where(function ($query) use ($bookedCount) {
              $query->where('staff_global_hours.max_booking', '<=', $bookedCount);
            })
            ->where('service_bookings.staff_id', '=', $request->staff_id);
        })
        ->whereNull('service_bookings.id')
        ->where('staff_global_hours.vendor_id', $request->vendor_id)
        ->where('staff_global_days.day', $request->dayName)
        ->select('staff_global_days.day', 'staff_global_hours.start_time', 'staff_global_hours.end_time', 'staff_global_hours.id')
        ->get();
    } else {
      // Retrieve admin-specific available time slots
      $information['global_time'] = StaffGlobalHour::join('admin_global_days', 'admin_global_days.id', '=', 'staff_global_hours.global_day_id')
        ->leftJoin('service_bookings', function ($join) use ($request, $bookedCount) {
          $join->on('service_bookings.service_hour_id', '=', 'staff_global_hours.id')
            ->where('service_bookings.booking_date', '=', $request->bookingDate)
            ->where(function ($query) use ($bookedCount) {
              $query->where('staff_global_hours.max_booking', '<=', $bookedCount);
            })
            ->where('service_bookings.staff_id', '=', $request->staff_id);
        })
        ->whereNull('service_bookings.id')
        ->where('staff_global_hours.vendor_id', $request->vendor_id)
        ->where('admin_global_days.day', $request->dayName)
        ->select('admin_global_days.day', 'staff_global_hours.start_time', 'staff_global_hours.end_time', 'staff_global_hours.id')
        ->get();
    }

    $maxPerson = Services::where('id', $request->serviceId)->value('max_person');
    if (!is_null($maxPerson)) {
      $information['maxPerson'] = $maxPerson;
    }

    return response()->json([
      'success' => true,
      'data' => $information
    ]);
  }

  //billing form
  public function billing(Request $request)
  {
    $rules = [
      'name' => 'required',
      'phone' => 'required',
      'email' => 'required|email',
      'address' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 422);
    }

    $data = [
      'name' => $request->name,
      'phone' => $request->phone,
      'email' => $request->email,
      'address' => $request->address,
      'zip_code' => $request->zip_code,
      'country' => $request->country,
      'booking_date' => $request->booking_date,
      'user_id' => $request->user_id,
      'staff_id' => $request->staff_id,
      'service_hour_id' => $request->service_hour_id,
      'max_person' => $request->max_person,
    ];

    return response()->json([
      'success' => true,
      'data' => $data
    ]);
  }

  public function inQuiryMessage(Request $request)
  {
    try {
      $rules = [
        'first_name' => 'required|max:255',
        'vendor_id' => 'required',
        'service_id' => 'required',
        'email' => 'required|email',
        'message' => 'required'
      ];

      $validator = Validator::make($request->all(), $rules);

      if ($validator->fails()) {
        return response()->json([
          'success' => false,
          'errors' => $validator->errors()
        ], 422);
      }

      $inquiryMessage = InqueryMessage::create([
        'vendor_id' => $request->vendor_id,
        'service_id' => $request->service_id,
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'email' => $request->email,
        'message' => $request->message,
      ]);

      $bs = DB::table('basic_settings')->select('to_mail')->first();
      $admin = Admin::whereNull('role_id')->select('email', 'username')->first();
      if ($bs->to_mail != null) {
        $admin_email = $bs->to_mail;
      } else {
        $admin_email = $admin->email;
      }

      if ($request->vendor_id != 0) {
        $to_mail = DB::table('vendors')
          ->where('id', $request->vendor_id)
          ->select('recived_email')->first();
        $vendor_mail =  $to_mail->recived_email;
      } else {
        $vendor_mail = $admin_email;
      }

      // get the mail template info from db
      $mailTemplate = MailTemplate::query()->where('mail_type', '=', 'service_inquery')->first();
      $mailData['subject'] = $mailTemplate->mail_subject;
      $mailBody = $mailTemplate->mail_body;

      // get the website title info from db
      $info = Basic::select('website_title')->first();

      //get language
      $locale = $request->header('Accept-Language');
      $language = $locale ? Language::where('code', $locale)->first()
        : Language::where('is_default', 1)->first();

      $message = $request->message;
      $service = $inquiryMessage->serviceContent->where('language_id', $language->id)->first();

      $url = route('frontend.service.details', ['slug' => $service->slug, 'id' => $inquiryMessage->service_id]);

      if ($request->vendor_id != 0) {
        $vendor_name = $inquiryMessage->vendor->name;
      } else {
        $vendor_name = $admin->username;
      }
      $websiteTitle = $info->website_title;
      // replacing with actual data
      $mailBody = str_replace('{username}', $vendor_name, $mailBody);
      $mailBody = str_replace('{enquirer_name}', "<a href=" . $url . ">$service->name</a>", $mailBody);
      $mailBody = str_replace('{service_name}', $service->name, $mailBody);
      $mailBody = str_replace('{website_title}', $websiteTitle, $mailBody);
      $mailBody = str_replace('{enquirer_email}', $inquiryMessage->email, $mailBody);
      $mailBody = str_replace('{enquirer_message}', $message, $mailBody);

      $mailData['body'] = $mailBody;

      $mailData['recipient'] = $vendor_mail;

      BasicMailer::sendMail($mailData);

      return response()->json([
        'status' => 'success',
        'message' => __('Message sent successfully')
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'status' => 'error',
        'message' => __('Something went wrong!')
      ]);
    }
  }

  public function staffSearch(Request $request, $id)
  {
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $location = $name = null;
    $staffIds = [];
    //search for location
    if ($request->filled('searchVal')) {
      $location = $request->searchVal;
      $bs = Basic::select('google_map_status')->first();
      if ($bs->google_map_status == 1) {
        $distanceAddress = new StaffDistanceController;
        $staffIds = $distanceAddress->index($location, $language->id, $id);
      } else {
        $staffContents = StaffContent::where('location', 'like', '%' . $location . '%')
          ->pluck('staff_id')->toArray();
        $staffIds =  $staffContents;
      }
    }
    if ($request->filled('searchName')) {
      $name = $request->searchName;
    }
    // Initial query to find staff based on service ID and other criteria
    $staffs = Staff::join('staff_contents', 'staff.id', '=', 'staff_contents.staff_id')
      ->join('staff_services', 'staff.id', '=', 'staff_services.staff_id')
      ->where('staff_services.service_id', $id)
      ->where('staff_contents.language_id', $language->id)
      ->when($name, function ($query) use ($name) {
        return $query->where('staff_contents.name', 'like', '%' . $name . '%');
      })
      ->when($location, function ($query) use ($staffIds) {
        return $query->whereIn('staff.id', $staffIds);
      })
      ->where('status', 1)
      ->select(
        'staff.id',
        'staff.email',
        'staff.image',
        'staff.is_day',
        'staff_contents.name',
        'staff_contents.staff_id'
      )
      ->get();

    return response()->json([
      'success' => true,
      'data' => $staffs->map(function ($staff) {
        $staff->image = $staff->image ? asset('assets/img/staff/' . $staff->image) : '';
        return $staff;
      })
    ]);
  }
}
