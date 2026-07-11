<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LanguageController extends Controller
{
  public function getLang($code)
  {
    $path = resource_path('lang/' . $code . '.json');
    $langData = json_decode(file_get_contents($path), true);

    return $langData;
  }

  public function getVendorLang($code)
  {
    $path = resource_path('lang/admin_' . $code . '.json');
    $langData = json_decode(file_get_contents($path), true);

    return $langData;
  }
}
