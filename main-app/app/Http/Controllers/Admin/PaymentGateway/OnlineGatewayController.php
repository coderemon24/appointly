<?php

namespace App\Http\Controllers\Admin\PaymentGateway;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use App\Models\PaymentGateway\OnlineGateway;

class OnlineGatewayController extends Controller
{
  public function index()
  {
    $data['paypal'] = OnlineGateway::where('keyword', 'paypal')->first();
    $data['instamojo'] = OnlineGateway::where('keyword', 'instamojo')->first();
    $data['paystack'] = OnlineGateway::where('keyword', 'paystack')->first();
    $data['flutterwave'] = OnlineGateway::where('keyword', 'flutterwave')->first();
    $data['razorpay'] = OnlineGateway::where('keyword', 'razorpay')->first();
    $data['mercadopago'] = OnlineGateway::where('keyword', 'mercadopago')->first();
    $data['mollie'] = OnlineGateway::where('keyword', 'mollie')->first();
    $data['stripe'] = OnlineGateway::where('keyword', 'stripe')->first();
    $data['paytm'] = OnlineGateway::where('keyword', 'paytm')->first();
    $data['anet'] = OnlineGateway::where('keyword', 'authorize.net')->first();
    $data['iyzico'] = OnlineGateway::where('keyword', 'iyzico')->first();
    $data['phonepe'] = OnlineGateway::where('keyword', 'phonepe')->first();
    $data['paytabs'] = OnlineGateway::where('keyword', 'paytabs')->first();
    $data['midtrans'] = OnlineGateway::where('keyword', 'midtrans')->first();
    $data['toyyibpay'] = OnlineGateway::where('keyword', 'toyyibpay')->first();
    $data['myfatoorah'] = OnlineGateway::where('keyword', 'myfatoorah')->first();
    $data['perfect_money'] = OnlineGateway::where('keyword', 'perfect_money')->first();
    $data['xendit'] = OnlineGateway::where('keyword', 'xendit')->first();
    $data['yoco'] = OnlineGateway::where('keyword', 'yoco')->first();

    return view('admin.payment-gateways.online-gateways', $data);
  }

  /**
   * update paypal info
   */
  public function updatePayPalInfo(Request $request)
  {
    $rules = [
      'paypal_status' => 'required',
      'paypal_sandbox_status' => 'required',
      'paypal_client_id' => 'required',
      'paypal_client_secret' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['sandbox_status'] = $request->paypal_sandbox_status;
    $information['client_id'] = $request->paypal_client_id;
    $information['client_secret'] = $request->paypal_client_secret;

    $paypalInfo = OnlineGateway::where('keyword', 'paypal')->first();

    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for paypal info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['PAYPAL_CLIENT_ID'] = $request->paypal_client_id;
      $publicConfig['PAYPAL_SECRET'] = $request->paypal_client_secret;
      $publicConfig['PAYPAL_BASE'] = $request->paypal_sandbox_status == 1
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);

      $paypalInfo->update([
        'mobile_information' => json_encode($information),
        'mobile_status' => $request->paypal_status
      ]);
    } else {
      $paypalInfo->update([
        'information' => json_encode($information),
        'status' => $request->paypal_status
      ]);
    }

    session()->flash('success', __("PayPal's information updated successfully!"));
    return redirect()->back();
  }

  /**
   * update xendit info
   */
  public function xenditUpdate(Request $request)
  {
    $data = OnlineGateway::where('keyword', 'xendit')->first();

    $information = [
      "secret_key" => $request->secret_key
    ];
    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for xendit info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['XENDIT_SECRET_KEY'] = $request->secret_key;
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);

      $data->mobile_status = $request->status;
      $data->mobile_information = json_encode($information);
      $data->save();
    } else {
      $data->status = $request->status;
      $data->information = json_encode($information);
      $data->save();
    }

    session()->flash('success', __('Updated Successfully'));
    return back();
  }

  /**
   * update yoco info
   */
  public function yocoUpdate(Request $request)
  {
    $data = OnlineGateway::where('keyword', 'yoco')->first();
    $information = [
      "secret_key" => $request->secret_key
    ];
    $data->status = $request->status;
    $data->information = json_encode($information);
    $data->save();
    session()->flash('success', __('Updated Successfully'));
    return back();
  }

  /**
   * update instamojo info
   */
  public function updateInstamojoInfo(Request $request)
  {
    $rules = [
      'instamojo_status' => 'required',
      'instamojo_sandbox_status' => 'required',
      'instamojo_key' => 'required',
      'instamojo_token' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['sandbox_status'] = $request->instamojo_sandbox_status;
    $information['key'] = $request->instamojo_key;
    $information['token'] = $request->instamojo_token;

    $instamojoInfo = OnlineGateway::where('keyword', 'instamojo')->first();

    $instamojoInfo->update([
      'information' => json_encode($information),
      'status' => $request->instamojo_status
    ]);

    session()->flash('success', __("Instamojo's information updated successfully!"));

    return redirect()->back();
  }

  /**
   * update paystack info
   */
  public function updatePaystackInfo(Request $request)
  {
    $rules = [
      'paystack_status' => 'required',
      'paystack_key' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }


    $information['key'] = $request->paystack_key;
    $paystackInfo = OnlineGateway::where('keyword', 'paystack')->first();

    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for paystack info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['PAYSTACK_SECRET_KEY'] = $request->paystack_key;
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);

      $paystackInfo->update([
        'mobile_information' => json_encode($information),
        'mobile_status' => $request->paystack_status
      ]);
    } else {
      $paystackInfo->update([
        'information' => json_encode($information),
        'status' => $request->paystack_status
      ]);
    }

    session()->flash('success', __("Paystack's information updated successfully!"));
    return redirect()->back();
  }

  /**
   * update nowpayments info
   */
  public function updateNowPayments(Request $request)
  {
    $rules = [
      'status' => 'required',
      'api_key' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $nowPaymentsInfo = OnlineGateway::where('keyword', 'now_payments')->first();
    $information['api_key'] = $request->api_key;


    if (isset($request->is_mobile) && $request->is_mobile == 1) {

      //update public/config file for now_payments info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['NOWPAYMENTS_API_KEY'] = $request->api_key;
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);


      $nowPaymentsInfo->update([
        'mobile_information' => json_encode($information),
        'mobile_status' => $request->status
      ]);
    } else {
      $nowPaymentsInfo->update([
        'information' => json_encode($information),
        'status' => $request->status
      ]);
    }


    session()->flash('success', __("NowPayments's information updated successfully!"));
    return redirect()->back();
  }

  /**
   * update flutterwave info
   */
  public function updateFlutterwaveInfo(Request $request)
  {
    $rules = [
      'flutterwave_status' => 'required',
      'flutterwave_public_key' => 'required',
      'flutterwave_secret_key' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information = [
      'public_key' => $request->flutterwave_public_key,
      'secret_key' => $request->flutterwave_secret_key,
    ];

    $flutterwaveInfo = OnlineGateway::where('keyword', 'flutterwave')->first();

    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for flutterwave info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['FLW_SECRET_KEY'] = $request->flutterwave_secret_key;
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);


      $flutterwaveInfo->mobile_status = $request->flutterwave_status;
      $flutterwaveInfo->mobile_information = json_encode($information);
    } else {
      $flutterwaveInfo->status = $request->flutterwave_status;
      $flutterwaveInfo->information = json_encode($information);

      // Update .env for web config
      $array = [
        'FLW_PUBLIC_KEY' => $request->flutterwave_public_key,
        'FLW_SECRET_KEY' => $request->flutterwave_secret_key
      ];

      setEnvironmentValue($array);
      Artisan::call('config:clear');
    }

    $flutterwaveInfo->save();

    session()->flash('success', __("Flutterwave's information updated successfully!"));
    return redirect()->back();
  }

  /**
   * update razorpay info
   */
  public function updateRazorpayInfo(Request $request)
  {
    $rules = [
      'razorpay_status' => 'required',
      'razorpay_key' => 'required',
      'razorpay_secret' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }


    $information['key'] = $request->razorpay_key;
    $information['secret'] = $request->razorpay_secret;

    $razorpayInfo = OnlineGateway::where('keyword', 'razorpay')->first();

    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      $razorpayInfo->mobile_information = json_encode($information);
      $razorpayInfo->mobile_status = $request->razorpay_status;
      $razorpayInfo->save();
    } else {
      $razorpayInfo->information = json_encode($information);
      $razorpayInfo->status = $request->razorpay_status;
      $razorpayInfo->save();
    }

    session()->flash('success', __("Razorpay's information updated successfully!"));
    return redirect()->back();
  }

  /**
   * update mercado pago info
   */
  public function updateMercadoPagoInfo(Request $request)
  {
    $rules = [
      'mercadopago_status' => 'required',
      'mercadopago_sandbox_status' => 'required',
      'mercadopago_token' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['sandbox_status'] = $request->mercadopago_sandbox_status;
    $information['token'] = $request->mercadopago_token;

    $mercadopagoInfo = OnlineGateway::where('keyword', 'mercadopago')->first();

    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for mercadopago info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['MP_ACCESS_TOKEN'] = $request->mercadopago_token;
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);


      $mercadopagoInfo->update([
        'mobile_information' => json_encode($information),
        'mobile_status' => $request->mercadopago_status
      ]);
    } else {
      $mercadopagoInfo->update([
        'information' => json_encode($information),
        'status' => $request->mercadopago_status
      ]);
    }



    session()->flash('success', __("MercadoPago's information updated successfully!"));
    return redirect()->back();
  }

  /**
   * update mollie info
   */
  public function updateMollieInfo(Request $request)
  {
    $rules = [
      'mollie_status' => 'required',
      'mollie_key' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['key'] = $request->mollie_key;

    $mollieInfo = OnlineGateway::where('keyword', 'mollie')->first();

    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for mollie info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['MOLLIE_API_KEY'] = $request->mollie_key;
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);

      $mollieInfo->update([
        'mobile_information' => json_encode($information),
        'mobile_status' => $request->mollie_status
      ]);
    } else {
      $mollieInfo->update([
        'information' => json_encode($information),
        'status' => $request->mollie_status
      ]);

      $array = ['MOLLIE_KEY' => $request->mollie_key];

      setEnvironmentValue($array);
      Artisan::call('config:clear');
    }

    session()->flash('success', __("Mollie's information updated successfully!"));
    return redirect()->back();
  }

  /**
   * update stripe info
   */
  public function updateStripeInfo(Request $request)
  {
    $rules = [
      'stripe_status' => 'required',
      'stripe_key' => 'required',
      'stripe_secret' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information = [
      'key' => $request->stripe_key,
      'secret' => $request->stripe_secret,
    ];

    $stripeInfo = OnlineGateway::where('keyword', 'stripe')->first();

    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      // Update public/config.php for apps
      $publicConfig = include base_path('public/config.php');
      $publicConfig['STRIPE_SECRET_KEY'] = $request->stripe_secret;
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);

      $stripeInfo->mobile_status = $request->stripe_status;
      $stripeInfo->mobile_information = json_encode($information);
    } else {
      $stripeInfo->status = $request->stripe_status;
      $stripeInfo->information = json_encode($information);

      // Update .env for web config
      $array = [
        'STRIPE_KEY' => $request->stripe_key,
        'STRIPE_SECRET' => $request->stripe_secret,
      ];

      setEnvironmentValue($array);
      Artisan::call('config:clear');
    }

    $stripeInfo->save();

    session()->flash('success', __("Stripe's information updated successfully!"));
    return redirect()->back();
  }

  /**
   * update paytm info
   */
  public function updatePaytmInfo(Request $request)
  {
    $rules = [
      'paytm_status' => 'required',
      'paytm_environment' => 'required',
      'paytm_merchant_key' => 'required',
      'paytm_merchant_mid' => 'required',
      'paytm_merchant_website' => 'required',
      'paytm_industry_type' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['environment'] = $request->paytm_environment;
    $information['merchant_key'] = $request->paytm_merchant_key;
    $information['merchant_mid'] = $request->paytm_merchant_mid;
    $information['merchant_website'] = $request->paytm_merchant_website;
    $information['industry_type'] = $request->paytm_industry_type;

    $paytmInfo = OnlineGateway::where('keyword', 'paytm')->first();

    $paytmInfo->update([
      'information' => json_encode($information),
      'status' => $request->paytm_status
    ]);

    $array = [
      'PAYTM_ENVIRONMENT' => $request->paytm_environment,
      'PAYTM_MERCHANT_KEY' => $request->paytm_merchant_key,
      'PAYTM_MERCHANT_ID' => $request->paytm_merchant_mid,
      'PAYTM_MERCHANT_WEBSITE' => $request->paytm_merchant_website,
      'PAYTM_INDUSTRY_TYPE' => $request->paytm_industry_type
    ];

    setEnvironmentValue($array);
    Artisan::call('config:clear');

    session()->flash('success', __("Paytm's information updated successfully!"));

    return redirect()->back();
  }

  /**
   * update authorize.net info
   */
  public function updateAnetInfo(Request $request)
  {
    $information = [];
    $information['login_id'] = $request->login_id;
    $information['transaction_key'] = $request->transaction_key;
    $information['public_key'] = $request->public_key;
    $information['sandbox_check'] = $request->sandbox_check;
    $information['text'] = "Pay via your Authorize.net account.";


    $anet = OnlineGateway::where('keyword', 'authorize.net')->first();

    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for authorize info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['AUTHORIZE_LOGIN_ID'] = $request->login_id;
      $publicConfig['AUTHORIZE_TRANSACTION_KEY'] = $request->transaction_key;
      $publicConfig['AUTHORIZE_ENV'] = $request->sandbox_check ? 'sandbox' : 'production';
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);

      $anet->mobile_status = $request->status;
      $anet->mobile_information = json_encode($information);
      $anet->save();
    } else {
      $anet->status = $request->status;
      $anet->information = json_encode($information);
      $anet->save();
    }






    session()->flash('success', __("Authorize.net informations updated successfully!"));
    return back();
  }

  /**
   * update iyzico info
   */
  public function updateiyzicoInfo(Request $request)
  {
    $data = OnlineGateway::where('keyword', 'iyzico')->first();
    $information = [
      "sandbox_status" => $request->sandbox_status,
      "api_key" => $request->api_key,
      "secret_key" => $request->secret_key
    ];
    $data->status = $request->status;
    $data->information = json_encode($information);
    $data->save();
    session()->flash('success', __('Updated Successfully'));
    return back();
  }

  /**
   * update phonepe info
   */
  public function phonepeUpdate(Request $request)
  {
    $data = OnlineGateway::where('keyword', 'phonepe')->first();

    $information = [
      "sandbox_status" => $request->sandbox_status,
      "merchant_id" => $request->merchant_id,
      "salt_key" => $request->salt_key,
      "salt_index" => $request->salt_index
    ];

    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for phonepe info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['PHONEPE_MERCHANT_ID'] = $request->merchant_id;
      $publicConfig['PHONEPE_SALT_KEY'] = $request->salt_key;
      $publicConfig['PHONEPE_SALT_INDEX'] = $request->salt_index;
      $publicConfig['PHONEPE_BASE'] = $request->sandbox_status == 1 ? 'https://api-preprod.phonepe.com/apis/pg-sandbox' : 'https://api.phonepe.com/apis/hermes';
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);


      $data->mobile_status = $request->status;
      $data->mobile_information = json_encode($information);
      $data->save();
    } else {
      $data->status = $request->status;
      $data->information = json_encode($information);
      $data->save();
    }



    session()->flash('success', __('Updated Successfully'));
    return back();
  }

  /**
   * update monnify info
   */
  public function updateMonify(Request $request)
  {
    $data = OnlineGateway::where('keyword', 'monnify')->first();

    $information = [
      "sandbox_status" => $request->sandbox_status,
      "api_key" => $request->api_key,
      "secret_key" => $request->secret_key,
      "wallet_account_number" => $request->wallet_account_number
    ];


    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for monnify info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['MONNIFY_API_KEY'] = $request->api_key;
      $publicConfig['MONNIFY_SECRET_KEY'] = $request->secret_key;
      $publicConfig['MONNIFY_CONTRACT_CODE'] = $request->wallet_account_number;
      $publicConfig['MONNIFY_BASE'] = $request->sandbox_status == 1 ? 'https://sandbox.monnify.com' : 'https://api.monnify.com';
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);


      $data->mobile_status = $request->status;
      $data->mobile_information = json_encode($information);
      $data->save();
    } else {
      $data->status = $request->status;
      $data->information = json_encode($information);
      $data->save();
    }

    session()->flash('success', __('Updated Successfully'));
    return back();
  }

  /**
   * update perfect money info
   */
  public function perfect_moneyUpdate(Request $request)
  {
    $data = OnlineGateway::where('keyword', 'perfect_money')->first();
    $information = [
      "perfect_money_wallet_id" => $request->perfect_money_wallet_id
    ];
    $data->status = $request->status;
    $data->information = json_encode($information);
    $data->save();
    session()->flash('success', __('Updated Successfully'));
    return back();
  }

  /**
   * update paytabs info
   */
  public function paytabsUpdate(Request $request)
  {
    $data = OnlineGateway::where('keyword', 'paytabs')->first();
    $information = [
      "country" => $request->country,
      "server_key" => $request->server_key,
      "profile_id" => $request->profile_id,
      "api_endpoint" => $request->api_endpoint
    ];
    $data->status = $request->status;
    $data->information = json_encode($information);
    $data->save();
    session()->flash('success', __('Updated Successfully'));
    return back();
  }

  /**
   * update midtrans info
   */
  public function midtransUpdate(Request $request)
  {
    $data = OnlineGateway::where('keyword', 'midtrans')->first();

    $information = [
      "is_production" => $request->is_production,
      "server_key" => $request->server_key
    ];
    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for midtrans info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['MIDTRANS_SERVER_KEY'] = $request->server_key;
      $publicConfig['MIDTRANS_BASE'] = $request->is_production == 1 ? 'https://app.sandbox.midtrans.com/snap/v1/transactions' :
        'https://app.midtrans.com/snap/v1/transactions';
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);

      $data->mobile_status = $request->status;
      $data->mobile_information = json_encode($information);
      $data->save();
    } else {
      $data->status = $request->status;
      $data->information = json_encode($information);
      $data->save();
    }


    session()->flash('success', __('Updated Successfully'));
    return back();
  }

  /**
   * update toyyibpay info
   */
  public function toyyibpayUpdate(Request $request)
  {
    $information = [
      "sandbox_status" => $request->sandbox_status,
      "secret_key" => $request->secret_key,
      "category_code" => $request->category_code
    ];

    $data = OnlineGateway::where('keyword', 'toyyibpay')->first();
    if (isset($request->is_mobile) && $request->is_mobile == 1) {

      //update public/config file for toyyibpay info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['TOYYIBPAY_SECRET_KEY'] = $request->secret_key;
      $publicConfig['TOYYIBPAY_CATEGORY_CODE'] = $request->category_code;
      $publicConfig['TOYYIBPAY_BASE'] = $request->sandbox_status == 1 ? 'https://dev.toyyibpay.com' : 'https://www.toyyibpay.com';
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);

      $data->mobile_status = $request->status;
      $data->mobile_information = json_encode($information);
      $data->save();
    } else {
      $data->status = $request->status;
      $data->information = json_encode($information);
      $data->save();
    }


    session()->flash('success', __('Updated Successfully'));
    return back();
  }

  /**
   * update myfatoorah info
   */
  public function myfatoorahUpdate(Request $request)
  {
    $data = OnlineGateway::where('keyword', 'myfatoorah')->first();
    $information = [
      "token" => $request->token,
      "sandbox_status" => $request->sandbox_status,
    ];
    if (isset($request->is_mobile) && $request->is_mobile == 1) {
      //update public/config file for midtrans info(used it only for apps)
      $publicConfig = include base_path('public/config.php');
      $publicConfig['MYFATOORAH_API_KEY'] = $request->token;
      $publicConfig['MYFATOORAH_BASE'] = $request->sandbox_status == 1
        ? 'https://apitest.myfatoorah.com'
        : 'https://api.myfatoorah.com';
      $configContent = "<?php\n\nreturn " . var_export($publicConfig, true) . ";\n";
      file_put_contents(base_path('public/config.php'), $configContent);

      $data->mobile_status = $request->status;
      $data->mobile_information = json_encode($information);
      $data->save();
    } else {
      $data->status = $request->status;
      $data->information = json_encode($information);
      $data->save();
      $array = [
        'MYFATOORAH_TOKEN' => $request->token,
      ];
      setEnvironmentValue($array);
      Artisan::call('config:clear');
    }



    session()->flash('success', __('Updated Successfully'));
    return back();
  }
}
