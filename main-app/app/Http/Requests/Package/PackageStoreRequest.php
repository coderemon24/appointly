<?php

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;

class PackageStoreRequest extends FormRequest
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
  public function rules(): array
  {
    $ruleArray = [
      'title' => 'required|max:255',
      'term' => 'required',
      'price' => 'required',
      'status' => 'required',
      'staff_limit' => 'required',
      'number_of_service_add' => 'required',
      'number_of_service_image' => 'required',
      'number_of_appointment' => 'required',
      'trial_days' => 'required_if:is_trial,1',
      'icon' => 'required',
    ];
    return $ruleArray;
  }
  public function messages(): array
  {
    return [
      'number_of_service_add' => __('Number of service is required'),
      'number_of_service_image' => __('Number of servic image is required.'),
      'number_of_appointment' => __('Number of appointment is required.'),
      'staff_limit' => __('Staff limit field is required.'),
      'icon' => __('Icon field is required.'),
      'trial_days' => __('Trial days is required.'),
    ];
  }
  // public function attributes(): array
  // {
  //   return [
  //     'title' => __('Package Title'),
  //     'term' => __('term'),
  //     'price' => __('price'),
  //     'status' => __('status'),
  //   ];
  // }
}
