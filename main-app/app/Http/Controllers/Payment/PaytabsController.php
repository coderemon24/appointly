<?php

namespace App\Http\Controllers\Payment;

use App\Models\Package;
use App\Models\Membership;
use Illuminate\Http\Request;
use App\Http\Helpers\MegaMailer;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use App\Http\Helpers\VendorPermissionHelper;
use App\Http\Controllers\Vendor\VendorCheckoutController;

class PaytabsController extends Controller
{
  public function paymentProcess(Request $request, $_amount, $_success_url, $_cancel_url)
  {
    Session::put('request', $request->all());

    $paytabInfo = paytabInfo();
    $description = 'Package Purchase via paytabs';

    try {
      $response = Http::withHeaders([
        'Authorization' => $paytabInfo['server_key'], // Server Key
        'Content-Type' => 'application/json',
      ])->post($paytabInfo['url'], [
        'profile_id' => $paytabInfo['profile_id'], // Profile ID
        'tran_type' => 'sale',
        'tran_class' => 'ecom',
        'cart_id' => uniqid(),
        'cart_description' => $description,
        'cart_currency' => $paytabInfo['currency'], // set currency by region
        'cart_amount' => round($_amount, 2),
        'return' => $_success_url,
      ]);

      $responseData = $response->json();
      // put some data in session before redirect to paytm url
      return redirect()->to($responseData['redirect_url']);
    } catch (\Exception $e) {
      return redirect($_cancel_url);
    }
  }
  public function successPayment(Request $request)
  {
    $requestData = Session::get('request');
    $resp = $request->all();
    $bs = Basic::select('base_currency_text', 'base_currency_rate')->first();
    if ($resp['respStatus'] == "A" && $resp['respMessage'] == 'Authorised') {
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
      $transaction_details = NULL;
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
