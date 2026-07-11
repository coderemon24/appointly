<?php

namespace App\Http\Controllers\Admin;

use DB;
use Validator;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Helpers\UploadFile;
use App\Rules\ImageMimeTypeRule;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use App\Models\Services\ServiceContent;
use App\Models\Services\ServiceCategory;
use Illuminate\Support\Facades\Response;

class ServiceCategoryController extends Controller
{
  public function index(Request $request)
  {
    $language = Language::where('code', $request->language)->firstOrFail();

    $information['langs'] = Language::all();

    $information['categories'] = ServiceCategory::where('language_id', $language->id)
      ->orderBy('serial_number', 'asc')
      ->get();

    $information['currencyInfo'] = $this->getCurrencyInfo();

    $information['themeInfo'] = DB::table('basic_settings')->select('theme_version')->first();
    return view('admin.service-categories.index', $information);
  }

  public function store(Request $request)
  {
    $themeVersion = Basic::query()->pluck('theme_version')->first();
    $rules = [
      'language_id' => 'required',
      'name' => 'required|unique:service_categories|max:255',
      'status' => 'required|numeric',
      'serial_number' => 'required|numeric',
      'mobail_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
    ];

    if ($themeVersion == 3) {
      $rules['image'] = ['required', new ImageMimeTypeRule()];
    }
    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }

    $imgName = '';
    if ($request->hasFile('image')) {
      $imgName = UploadFile::store(public_path('assets/img/category/'), $request->file('image'));
    }
    $mobail_image = '';
    if ($request->hasFile('mobail_image')) {
      $mobail_image = UploadFile::store(public_path('assets/img/category/'), $request->file('mobail_image'));
    }

    ServiceCategory::create($request->except('language_id', 'slug', 'image', 'icon', 'background_color', 'mobail_image') + [
      'name' => $request->name,
      'slug' => createSlug($request->name),
      'language_id' => $request->language_id,
      'serial_number' => $request->serial_number,
      'status' => $request->status,
      'icon' => $request->icon,
      'image' => $imgName,
      'mobail_image' => $mobail_image,
      'background_color' => $request->background_color,
    ]);

    session()->flash('success', __('New category added successfully!'));
    return Response::json(['status' => 'success'], 200);
  }

  public function update(Request $request)
  {
    $category = ServiceCategory::find($request->id);

    $rules = [
      'name' => [
        'required',
        'max:255',
        Rule::unique('service_categories', 'name')->ignore($request->id, 'id')
      ],
      'status' => 'required|numeric',
      'serial_number' => 'required|numeric',
    ];

    if ($request->hasFile('image')) {
      $rules['image'] =  new ImageMimeTypeRule();
    }

    if ($request->hasFile('mobail_image')) {
      $rules['mobail_image'] =  'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048';
    }

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }


    if ($request->hasFile('image')) {
      $newImage = $request->file('image');
      $oldImage = $category->image;
      $imgName = UploadFile::update(public_path('assets/img/category/'), $newImage, $oldImage);
    }
    if ($request->hasFile('mobail_image')) {
      $mobail_imageNewImage = $request->file('mobail_image');
      $mobail_oldImage = $category->mobail_image;
      $mobail_image_name = UploadFile::update(public_path('assets/img/category/'), $mobail_imageNewImage, $mobail_oldImage);
    }
    $category->update([
      'name' => $request->name,
      'slug' => createSlug($request->name),
      'serial_number' => $request->serial_number,
      'status' => $request->status,
      'icon' => $request->icon ?? $category->icon,
      'background_color' => $request->background_color ?? $category->background_color,
      'image' => $request->hasFile('image') ? $imgName : $category->image,
      'mobail_image' => $request->hasFile('mobail_image') ? $mobail_image_name : $category->mobail_image,
    ]);

    session()->flash('success', __('Category updated successfully!'));

    return Response::json(['status' => 'success'], 200);
  }


  public function destroy($id)
  {
    $servicesCount = ServiceContent::where('category_id', $id)->count();

    if ($servicesCount > 0) {
      return redirect()->back()->with('warning', __('First delete all the services of this category!'));
    } else {

      $category = ServiceCategory::find($id);
      @unlink(public_path('assets/img/category/') . $category->image);
      @unlink(public_path('assets/img/category/') . $category->mobail_image);
      $category->delete();
      return redirect()->back()->with('success', __('Category deleted successfully!'));
    }
  }


  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;
    $errorOccured = false;


    foreach ($ids as $id) {
      $category = ServiceCategory::find($id);
      $servicesCount = ServiceContent::where('category_id', $id)->count();

      if ($servicesCount > 0) {
        $errorOccured = true;
        break;
      } else {
        @unlink(public_path('assets/img/category/') . $category->image);
        @unlink(public_path('assets/img/category/') . $category->mobail_image);
        $category->delete();
      }
    }
    if ($errorOccured == true) {
      session()->flash('warning', __('First delete all the services of these categories!'));
    } else {
      session()->flash('success', __('Service categories deleted successfully!'));
    }

    return Response::json(['status' => 'success'], 200);
  }
}
