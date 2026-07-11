<?php

namespace App\Http\Controllers\Payment;

use App\Models\Package;
use App\Models\Membership;
use Illuminate\Http\Request;
use App\Http\Helpers\MegaMailer;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\VendorCheckoutController;

class XenditController extends Controller
{
  public function paymentProcess($request, $_amount, $_success_url, $_cancel_url, $bs)
  {
    $paymentMethod = OnlineGateway::where('keyword', 'xendit')->first();
    $paydata = json_decode($paymentMethod->information, true);

    $external_id = \Str::random(10);
    $secret_key = 'Basic ' . base64_encode($paydata['secret_key'] . ':');
    $data_request = Http::withHeaders([
      'Authorization' => $secret_key
    ])->post('https://api.xendit.co/v2/invoices', [
      'external_id' => $external_id,
      'amount' => $_amount,
      'currency' => $bs->base_currency_text,
      'success_redirect_url' => $_success_url
    ]);
    $response = $data_request->json();
    if (isset($response['error_code']) && isset($response['message'])) {
      session()->flash('warning', $response['message']);
      return redirect()->back();
    }
    session()->put("request", $request->all());

    if (!empty($response['invoice_url'])) {
      session()->put('cancel_url', $_cancel_url);
      session()->put('xendit_id', $response['id']);
      session()->put('secret_key', $secret_key);
      return redirect($response['invoice_url']);
    } else {
      return redirect($_cancel_url);
    }
  }


  public function successPayment(Request $request)
  {
    $requestData = session()->get('request');
    $xendit_id = session()->get('xendit_id');
    $secret_key = session()->get('secret_key');
    $paymentMethod = OnlineGateway::where('keyword', 'xendit')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $p_secret_key = 'Basic ' . base64_encode($paydata['secret_key'] . ':');
    $bs = Basic::select('base_currency_text', 'base_currency_rate')->first();
    if (!is_null($xendit_id) && $secret_key == $p_secret_key) {
      //transaction create
      $after_balance = NULL;
      $pre_balance = NULL;
      $transactionData = [
        'vendor_id' => $requestData['vendor_id'],
        'transaction_type' => 'membership_buy',
        'pre_balance' => $pre_balance,
        'actual_total' => $requestData['price'],
        'after_balance' => $after_balance,
        'admin_profit' => $requestData['price'],
        'payment_method' => $requestData['payment_method'],
        'currency_symbol' => $bs->base_currency_symbol,
        'currency_symbol_position' => $bs->base_currency_symbol_position,
        'payment_status' => 'completed',
      ];
      store_transaction($transactionData);

      $paymentFor = session()->get('paymentFor');
      $package = Package::find($requestData['package_id']);
      $transaction_id = VendorPermissionHelper::uniqidReal(8);
      $transaction_details = json_encode($request['trxref']);
      if ($paymentFor == "membership") {
        $amount = $requestData['price'];
        $password = $requestData['password'];
        $checkout = new VendorCheckoutController();

        $vendor = $checkout->store($requestData, $transaction_id, $transaction_details, $amount, $bs, $password);

        $lastMemb = $vendor->memberships()->orderBy('id', 'DESC')->first();

        $activation = \Carbon\Carbon::parse($lastMemb->start_date);
        $expire = \Carbon\Carbon::parse($lastMemb->expire_date);
        $file_name = $this->makeInvoice($requestData, "membership", $vendor, $password, $amount, "Paypal", $requestData['phone'], $bs->base_currency_symbol_position, $bs->base_currency_symbol, $bs->base_currency_text, $transaction_id, $package->title, $lastMemb);

        $mailer = new MegaMailer();
        $data = [
          'toMail' => $vendor->email,
          'toName' => $vendor->fname,
          'username' => $vendor->username,
          'package_title' => $package->title,
          'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
          'discount' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $lastMemb->discount . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
          'total' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $lastMemb->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
          'activation_date' => $activation->toFormattedDateString(),
          'expire_date' => \Carbon\Carbon::parse($expire->toFormattedDateString())->format('Y') == '9999' ? 'Lifetime' : $expire->toFormattedDateString(),
          'membership_invoice' => $file_name,
          'website_title' => $bs->website_title,
          'templateType' => 'package_purchase',
          'type' => 'registrationWithPremiumPackage'
        ];
        $mailer->mailFromAdmin($data);
        @unlink(public_path('assets/front/invoices/' . $file_name));

        session()->flash('success', 'Your payment has been completed.');
        session()->forget('request');
        session()->forget('paymentFor');
        return redirect()->route('success.page');
      } elseif ($paymentFor == "extend") {
        $amount = $requestData['price'];
        $password = uniqid('qrcode');
        $checkout = new VendorCheckoutController();
        $vendor = $checkout->store($requestData, $transaction_id, $transaction_details, $amount, $bs, $password);

        $lastMemb = Membership::where('vendor_id', $vendor->id)->orderBy('id', 'DESC')->first();
        $activation = \Carbon\Carbon::parse($lastMemb->start_date);
        $expire = \Carbon\Carbon::parse($lastMemb->expire_date);

        $file_name = $this->makeInvoice($requestData, "extend", $vendor, $password, $amount, $requestData["payment_method"], $vendor->phone, $bs->base_currency_symbol_position, $bs->base_currency_symbol, $bs->base_currency_text, $transaction_id, $package->title, $lastMemb);

        $mailer = new MegaMailer();
        $data = [
          'toMail' => $vendor->email,
          'toName' => $vendor->fname,
          'username' => $vendor->username,
          'package_title' => $package->title,
          'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
          'activation_date' => $activation->toFormattedDateString(),
          'expire_date' => \Carbon\Carbon::parse($expire->toFormattedDateString())->format('Y') == '9999' ? 'Lifetime' : $expire->toFormattedDateString(),
          'membership_invoice' => $file_name,
          'website_title' => $bs->website_title,
          'templateType' => 'package_purchase',
          'type' => 'membershipExtend'
        ];
        $mailer->mailFromAdmin($data);
        @unlink(public_path('assets/front/invoices/' . $file_name));

        session()->forget('request');
        session()->forget('paymentFor');
        return redirect()->route('success.page');
      }
    } else {
      $requestData = session()->get('request');
      $paymentFor = session()->get('paymentFor');
      session()->flash('warning', __('cancel_payment'));
      if ($paymentFor == "membership") {
        return redirect()->route('front.register.view', ['status' => $requestData['package_type'], 'id' => $requestData['package_id']])->withInput($requestData);
      } else {
        return redirect()->route('vendor.plan.extend.checkout', ['package_id' => $requestData['package_id']])->withInput($requestData);
      }
    }
  }


  public function cancelPayment()
  {
    session()->flash('warning', __('cancel_payment'));
    return redirect()->route('vendor.plan.extend.checkout', ['package_id' => session()->get('request')['package_id']])->withInput(session()->get('request'));
  }
}
