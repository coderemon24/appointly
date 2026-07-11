<?php

namespace App\Http\Controllers\Api;

use App\Models\FcmToken;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FcmTokenController extends Controller
{
  /**
   * store fcm token after login
   */
  public function store(Request $request)
  {
    $request->validate(['token' => 'required|string', 'platform' => 'nullable|string']);

    FcmToken::updateOrCreate(
      ['token' => $request->token],
      ['user_id' => $request->user_id, 'platform' => $request->platform]
    );

    return response()->json(['status' => 'success', 'message' => 'Token saved']);
  }
}
