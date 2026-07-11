<?php

namespace App\Http\Controllers\Api\Vendor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class RecivedEmailController extends Controller
{
  public function mailToAdmin()
  {
    $data = DB::table('vendors')->where('id', Auth::guard('sanctum_vendor')->user()->id)->select('recived_email')->first();
    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }

  public function updateMailToAdmin(Request $request)
  {
    $rule = [
      'to_mail' => 'required'
    ];

    $message = [
      'to_mail.required' => __('The mail address field is required.')
    ];

    $validator = Validator::make($request->all(), $rule, $message);


    if ($validator->fails()) {
      return Response::json(
        [
          'errors' => $validator->getMessageBag()->toArray()
        ],
        400
      );
    }

    DB::table('vendors')->updateOrInsert(
      ['id' => Auth::guard('sanctum_vendor')->user()->id],
      ['recived_email' => $request->to_mail]
    );

    return response()->json([
      'status' => true,
      'message' => __('Email address updated successfully.')
    ]);
  }
}
