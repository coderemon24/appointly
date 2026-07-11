<?php

namespace App\Http\Requests\Service;

use DB;
use App\Models\Language;
use App\Rules\ImageMimeTypeRule;
use App\Models\Services\ServiceContent;
use App\Models\VendorPlugins\VendorPlugin;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Helpers\VendorPermissionHelper;

class ServiceStoreRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   *
   * @return bool
   */
  public function authorize()
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, mixed>
   */
  public function rules()
  {
    $vendorId = $this->vendor_id;

    $ruleArray = [
      'slider_images' => 'required|max:' . $this->getSliderImageCount(),
      'service_image' => ['required', new ImageMimeTypeRule()],
      'status' => 'required',
      'price' => 'required|numeric',
      'latitude' => 'required|numeric|between:-90,90',
      'longitude' => 'required|numeric|between:-180,180',
    ];

    if ($vendorId != 0) {
      $plugin = VendorPlugin::where('vendor_id', $vendorId)->first();
    } else {
      $plugin = DB::table('basic_settings')->select('zoom_account_id', 'zoom_client_id', 'zoom_client_secret', 'google_calendar', 'calender_id')->first();
    }

    if ($this->zoom_meeting == 1 && ($plugin == null || $plugin->zoom_account_id == "" || $plugin->zoom_client_id == "" || $plugin->zoom_client_secret == "")) {
      $ruleArray['zoom'] = 'required';
    }

    if ($this->calendar_status == 1 && ($plugin == null || $plugin->google_calendar == "" || $plugin->calender_id == "")) {
      $ruleArray['calendar'] = 'required';
    }


    if ($this->person_type == 0) {
      $ruleArray['person'] = 'required';
    }

    $defaultLanguage = Language::where('is_default', 1)->first();
    // Default language fields should always be required
    $ruleArray[$defaultLanguage->code . '_name'] = 'required|max:255';
    $ruleArray[$defaultLanguage->code . '_description'] = 'required';
    $ruleArray[$defaultLanguage->code . '_category_id'] = 'required';

    $languages = Language::all();
    foreach ($languages as $language) {
      $hasExistingContent = ServiceContent::where('language_id', $language->id)->where('service_id', $this->id)->exists();
      $code = $language->code;

      // Skip the default language as it's always required
      if ($language->id == $defaultLanguage->id) {
        continue;
      }

      // Check if any field for this language is filled
      if (
        $hasExistingContent ||
        $this->filled($code . '_name') ||
        $this->filled($code . '_description') ||
        $this->filled($code . '_address') ||
        $this->filled($code . '_features') ||
        $this->filled($code . '_meta_keyword') ||
        $this->filled($code . '_meta_description') ||
        $this->filled($code . '_category_id')
      ) {
        // If any field is filled, make all fields required for both the language and default language
        $ruleArray["$code" . '_name'] = 'required|max:255';
        $ruleArray["$code" . '_description'] = 'required';
        $ruleArray["$code" . '_category_id'] = 'required';
      }
    }

    return $ruleArray;
  }

  public function messages()
  {
    $messageArray = [];

    $url = route('vendor.plugins.index');
    $messageArray = [
      'slider_images.max' => __('The slider images must not exceed') . ' ' . $this->getSliderImageCount() . ' ' . __('files'),
      'service_image.required' => __('The featured image field is required.'),
      'zoom.required' => __('If you enable zoom, then you have to set your zoom credentials.') . ' <a href="' . $url . '" class="link-primary" target="_blank">' . __("Click Here") . '</a>.',
      'calendar.required' => __('If you enable the calendar, then you have to set your calendar credentials.') . ' <a href="' . $url . '" class="link-primary" target="_blank">' . __("Click Here") . '</a>.',
      'longitude.between' => 'The longitude must be between :min and :max degrees.',
      'latitude.between' => 'The latitude must be between :min and :max degrees.',
    ];

    $languages = Language::all();

    foreach ($languages as $language) {
      $code = $language->code;
      $name = $language->name;
      $messageArray["$code" . '_name.required'] = __('The title field is required for') . ' ' . $name . ' ' . __('language.');
      $messageArray["$code" . '_name.max'] = __('The title field cannot contain more than 255 characters for') . ' ' . $name . __('language.');
      $messageArray["$code" . '_name.unique'] = __('The title field must be unique for') . ' ' . $name . ' ' . __('language.');
      $messageArray["$code" . '_description.required'] = __('The description field is required for') . ' ' . $name . ' ' . __('language.');
      $messageArray["$code" . '_category_id.required'] = __('The category field is required for') . ' ' . $name . ' ' . __('language.');
    }

    return $messageArray;
  }

  private function getSliderImageCount()
  {
    $vendorId = $this->vendor_id;
    if ($vendorId == 0) {
      return 999999;
    } else {

      $currentPackage = VendorPermissionHelper::packagePermission($vendorId);
      return $currentPackage->number_of_service_image;
    }
  }
}
