<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

class Authenticate extends Middleware
{
  /**
   * Get the path the user should be redirected to when they are not authenticated.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return string|null
   */
  // protected function redirectTo($request)
  // {
  //   if (!$request->expectsJson()) {
  //     if (Route::is('admin.*')) {
  //       return route('admin.login');
  //     }

  //     if (Route::is('user.*')) {
  //       return route('user.login');
  //     }

  //     if (Route::is('vendor.*')) {
  //       return route('vendor.login');
  //     }

  //     if (Route::is('staff.*')) {
  //       return route('staff.login');
  //     }
  //   }
  // }

  protected function unauthenticated($request, array $guards)
  {
    if ($request->expectsJson()) {
      abort(401, 'Unauthenticated.');
    }
    // dd($request->route());


     if ($request->routeIs('api.staff.*')) {
      return response()->json([
        'status' => 'authentication_error',
        'message' => 'Unauthenticated',
      ], 401);
    }

    if ($request->expectsJson()) {
      abort(401, 'Unauthenticated.');
    }

    if ($request->is('admin') || $request->is('admin/*')) {
      redirect()->route('admin.login')->send();
      exit;
    }

    if ($request->routeIs('staff.*')) {
      redirect()->route('staff.login')->send();
      exit;
    }


    return response()->json([
      'status' => 'authentication_error',
      'message' => 'Unauthenticated',
    ], 401);
    // redirect()->route('staff.login')->send();
    exit;
  }
}
