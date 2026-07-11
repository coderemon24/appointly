<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLangMiddleware
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
   * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
   */
  public function handle(Request $request, Closure $next)
  {
    if (Auth::guard('admin')->check()) {
      $locale = Auth::guard('admin')->user()->lang_code;
    }

    if (empty($locale)) {
      // set the default language as system locale
      $code = Language::query()->where('is_default', '=', 1)
        ->pluck('code')
        ->first();
      $languageCode = 'admin_' . $code;

      app()->setLocale($languageCode);
    } else {
      // set the selected language as system locale
      app()->setLocale($locale);
    }
    return $next($request);
  }
}
