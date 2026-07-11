<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Services\ServiceCategory;
use App\Models\Services\ServiceContent;
use App\Models\Services\ServiceSubCategory;
use App\Rules\ImageMimeTypeRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServiceSubcategoryController extends Controller
{
  public function index(Request $request)
  {
    $language = Language::where('code', $request->language)->firstOrFail();

    $information['langs'] = Language::all();
    $information['categories'] = ServiceCategory::where('language_id', $language->id)->get();
    $information['subcategories'] = ServiceSubCategory::where('language_id', $language->id)
      ->orderBy('serial_number', 'asc')
      ->get();

    $information['currencyInfo'] = $this->getCurrencyInfo();

    $information['themeInfo'] = DB::table('basic_settings')->select('theme_version')->first();
    return view('admin.services.subcategory.index', $information);
  }

  public function store(Request $request)
  {
    $rules = [
      'language_id' => 'required',
      'name' => 'required|unique:service_sub_categories|max:255',
      'status' => 'required|numeric',
      'category_id' => 'required',
      'serial_number' => 'required|numeric',
    ];

    $message = [
      'name.required' => __('The subcategory name field is required.'),
    ];

    $validator = Validator::make($request->all(), $rules, $message);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }

    ServiceSubCategory::create($request->except('language_id', 'slug') + [
      'name' => $request->name,
      'slug' => createSlug($request->name),
      'language_id' => $request->language_id,
      'category_id' => $request->category_id,
      'serial_number' => $request->serial_number,
      'status' => $request->status,
    ]);

    session()->flash('success', __('New subcategory added successfully!'));
    return Response::json(['status' => 'success'], 200);
  }

  public function update(Request $request)
  {
    $subcategory = ServiceSubCategory::find($request->id);

    $rules = [
      'name' => [
        'required',
        'max:255',
        Rule::unique('service_sub_categories', 'name')->ignore($request->id, 'id')
      ],
      'status' => 'required|numeric',
      'serial_number' => 'required|numeric',
      'category_id' => 'required',
    ];
    $message = [
      'name.required' => __('The subcategory name field is required.'),
    ];

    if ($request->hasFile('image')) {
      $rules['image'] =  new ImageMimeTypeRule();
    }

    $validator = Validator::make($request->all(), $rules, $message);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }


    $subcategory->update([
      'name' => $request->name,
      'slug' => createSlug($request->name),
      'serial_number' => $request->serial_number,
      'status' => $request->status,
      'category_id' => $request->category_id,
    ]);

    session()->flash('success', __('Subcategory updated successfully!'));

    return Response::json(['status' => 'success'], 200);
  }


  public function destroy($id)
  {
    $servicesCount = ServiceContent::where('subcategory_id', $id)->count();

    if ($servicesCount > 0) {
      return redirect()->back()->with('warning', __('First delete all the services of this subcategory!'));
    } else {

      $subcategory = ServiceSubCategory::find($id);
      $subcategory->delete();
      return redirect()->back()->with('success', __('Subcategory deleted successfully!'));
    }
  }


  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;
    $errorOccured = false;


    foreach ($ids as $id) {
      $category = ServiceSubCategory::find($id);
      $servicesCount = ServiceContent::where('subcategory_id', $id)->count();

      if ($servicesCount > 0) {
        $errorOccured = true;
        break;
      } else {
        $category->delete();
      }
    }
    if ($errorOccured == true) {
      session()->flash('warning', __('First delete all the services of these subcategories!'));
    } else {
      session()->flash('success', __('Subcategories deleted successfully!'));
    }

    return Response::json(['status' => 'success'], 200);
  }

  public function serviceCategory($lang)
  {
    $categories = ServiceCategory::where('language_id', $lang)->get();
    return response()->json($categories);
  }
}
