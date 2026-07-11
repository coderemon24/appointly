<?php

namespace App\Http\Controllers\Admin\BasicSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\PageHeadingRequest;
use App\Models\BasicSettings\PageHeading;
use App\Models\CustomPage\Page;
use App\Models\Language;
use Illuminate\Http\Request;

class PageHeadingController extends Controller
{
  public function pageHeadings(Request $request)
  {
    // first, get the language info from db
    $language = Language::query()->where('code', '=', $request->language)->firstOrFail();
    $information['language'] = $language;

    //additional page
    $information['pages'] = Page::query()->get();
    // then, get the page headings info of that language from db
    $information['data'] = $language->pageName()->first();

    if ($information['data']) {
      $information['decodedHeadings'] = json_decode($information['data']->custom_page_heading, true);
    } else {
      $information['decodedHeadings'] = [];
    }

    // get all the languages from db
    $information['langs'] = Language::all();

    return view('admin.basic-settings.page-headings', $information);
  }

  public function updatePageHeadings(Request $request)
  {
    // Get the language info from db
    $language = Language::query()->where('code', '=', $request->language)->firstOrFail();

    // Get the page heading info of that language from db
    $heading = $language->pageName()->first();

    // Prepare the data to be saved
    $data = $request->except('language_id');
    $data['language_id'] = $language->id;

    // Ensure custom_page_heading is encoded to JSON if it's an array
    if (isset($data['custom_page_heading']) && is_array($data['custom_page_heading'])) {
      $data['custom_page_heading'] = json_encode($data['custom_page_heading']);
    }

    // Check if there's existing heading data
    if (empty($heading)) {
      PageHeading::query()->create($data);
    } else {
      $heading->update($data);
    }

    session()->flash('success', 'Page headings updated successfully!');

    return redirect()->back();
  }
}
