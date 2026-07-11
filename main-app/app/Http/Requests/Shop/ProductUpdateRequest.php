<?php

namespace App\Http\Requests\Shop;

use App\Models\Language;
use App\Models\Shop\Product;
use App\Models\Shop\ProductContent;
use App\Rules\ImageMimeTypeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
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
   * @return array
   */
  public function rules()
  {
    $product = Product::find($this->id);
    $sliderImages = json_decode($product->slider_images);

    $ruleArray = [
      'slider_images' => count($sliderImages) == 0 && empty($this->slider_images) ? 'required' : '',
      'featured_image' => $this->hasFile('featured_image') ? new ImageMimeTypeRule() : '',
      'status' => 'required'
    ];

    $productType = $this->product_type;

    if ($productType == 'digital') {
      $ruleArray['input_type'] = 'required';

      if ($this->input_type == 'upload' && empty($product->file)) {
        $ruleArray['file'] = 'required';
      }
      if ($this->hasFile('file')) {
        $ruleArray['file'] = 'mimes:zip';
      }

      $ruleArray['link'] = 'required_if:input_type,link';
    } elseif ($productType == 'physical') {
      $ruleArray['stock'] = 'required|numeric';
    }

    $ruleArray['current_price'] = 'required|numeric';

    $defaultLanguage = Language::where('is_default', 1)->first();
    // Default language fields should always be required
    $ruleArray[$defaultLanguage->code . '_title'] = ['required', 'max:255', Rule::unique('product_contents', 'title')->ignore($this->id, 'product_id')];
    $ruleArray[$defaultLanguage->code . '_category_id'] = 'required';
    $ruleArray[$defaultLanguage->code . '_summary'] = 'required';
    $ruleArray[$defaultLanguage->code . '_content'] = 'required';


    $languages = Language::all();
    foreach ($languages as $language) {
      $hasExistingContent = ProductContent::where('language_id',$language->id)->where('product_id',$this->id)->exists();
      $code = $language->code;
      // Skip the default language as it's always required
      if ($language->id == $defaultLanguage->id) {
        continue;
      }

      if (
        $hasExistingContent ||
        $this->filled($code . '_title') ||
        $this->filled($code . '_summary') ||
        $this->filled($code . '_content') ||
        $this->filled($code . '_meta_keywords') ||
        $this->filled($code . '_meta_description') ||
        $this->filled($code . '_category_id')
      ) {
        $ruleArray[$code . '_title'] = [
          'required',
          'max:255',
          Rule::unique('product_contents', 'title')->ignore($this->id, 'product_id')
        ];
        $ruleArray[$code . '_category_id'] = 'required';
        $ruleArray[$code . '_summary'] = 'required';
        $ruleArray[$code . '_content'] = 'required';
      }
    }

    return $ruleArray;
  }

  /**
   * Get the validation messages that apply to the request.
   *
   * @return array
   */
  public function messages()
  {
    $messageArray = [];

    $productType = $this->product_type;

    if ($productType == 'digital') {
      $messageArray['file.required_if'] = __('The downloadable file is required when input type is upload.');
      $messageArray['file.mimes'] = __('Only .zip file is allowed for product\'s file.');
      $messageArray['link.required_if'] = __('The file download link is required when input type is link.');
    }

    $languages = Language::all();

    foreach ($languages as $language) {
      $code = $language->code;
      $name = ' ' . $language->name . ' ' . __('language.');

      $messageArray[$code . '_title.required'] = __('The title field is required for') . $name;
      $messageArray[$code . '_title.max'] = __('The title field cannot contain more than 255 characters for') . $name;
      $messageArray[$code . '_title.unique'] = 'The title field must be unique for ' . $name . ' language.';
      $messageArray[$code . '_category_id.required'] = __('The category field is required for') . $name;
      $messageArray[$code . '_summary.required'] = __('The summary field is required for') . $name;
      $messageArray[$code . '_content.required'] =  __('The content field is required for') . $name;
    }

    return $messageArray;
  }
}
