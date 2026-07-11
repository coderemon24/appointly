<?php

namespace App\Http\Controllers\Payment;

use Config;
use App\Models\Package;
use App\Models\Membership;
use Illuminate\Http\Request;
use App\Http\Helpers\MegaMailer;
use Basel\MyFatoorah\MyFatoorah;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\VendorCheckoutController;

class MyFatoorahController extends Controller
{
  private $myfatoorah;

  public function __construct()
  {
    $info = OnlineGateway::where('keyword', 'myfatoorah')->firstOrFail();
    $bs = Basic::first();

    $information = json_decode($info->information, true);
    config([
      'myfatoorah.token' => $information['token'] ?? '',
      'myfatoorah.DisplayCurrencyIso' => $bs->base_currency_text ?? 'KWD',
      'myfatoorah.CallBackUrl' => route('membership.myfatoorah.success'),
      'myfatoorah.ErrorUrl' => route('membership.myfatoorah.cancel'),
    ]);

    $sandboxMode = isset($information['sandbox_status']) && $information['sandbox_status'] == 1;

    $this->myfatoorah = MyFatoorah::getInstance($sandboxMode);
  }

  public function paymentProcess($request, $_amount, $_cancel_url)
  {
    $cancel_url = $_cancel_url;

    $info = OnlineGateway::where('keyword', 'myfatoorah')->first();
    $information = json_decode($info->information, true);
    $paymentFor = Session::get('paymentFor');

    $random_1 = rand(999, 9999);
    $random_2 = rand(9999, 99999);
    try {
      $result = $this->myfatoorah->sendPayment(
        Auth::guard('vendor')->user()->username,
        intval($_amount),
        [
          'CustomerMobile' => $information['sandbox_status'] == 1 ? '56562123544' : Auth::guard('vendor')->user()->phone,
          'CustomerReference' => "$random_1",  //orderID
          'UserDefinedField' => "$random_2", //clientID
          "InvoiceItems" => [
            [
              "ItemName" => "Package Purchase or Extends",
              "Quantity" => 1,
              "UnitPrice" => intval($_amount)
            ]
          ]
        ]
      );
    } catch (\Exception $e) {
      // dd($e);
    }
    if ($result && $result['IsSuccess'] == true) {
      Session::put('myfatoorah_payment_type', $paymentFor);
      Session::put("request", $request->all());
      return redirect($result['Data']['InvoiceURL']);
    } else {
      return redirect($cancel_url);
    }
  }

  public function successPayment(Request $request)
  {
    $requestData = Session::get('request');
    $bs = Basic::first();
    /** Get the payment ID before session clear **/
    if (!empty($request->paymentId)) {
      $result = $this->myfatoorah->getPaymentStatus('paymentId', $request->paymentId);


      if ($result && $result['IsSuccess'] == true && $result['Data']['InvoiceStatus'] == "Paid") {
        // transaction create
        $after_balance = null;
        $pre_balance = null;

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
        $transaction_details = null;

        if ($paymentFor == "membership") {
          $amount = $requestData['price'];
          $password = $requestData['password'];
          $checkout = new VendorCheckoutController();

          $vendor = $checkout->store($requestData, $transaction_id, $transaction_details, $amount, $bs, $password);

          $lastMemb = $vendor->memberships()->orderBy('id', 'DESC')->first();
          $activation = \Carbon\Carbon::parse($lastMemb->start_date);
          $expire = \Carbon\Carbon::parse($lastMemb->expire_date);

          $file_name = $this->makeInvoice(
            $requestData,
            "membership",
            $vendor,
            $password,
            $amount,
            "Paypal",
            $requestData['phone'],
            $bs->base_currency_symbol_position,
            $bs->base_currency_symbol,
            $bs->base_currency_text,
            $transaction_id,
            $package->title,
            $lastMemb
          );

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
            'expire_date' => $expire->format('Y') == '9999' ? 'Lifetime' : $expire->toFormattedDateString(),
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

          $file_name = $this->makeInvoice(
            $requestData,
            "extend",
            $vendor,
            $password,
            $amount,
            $requestData["payment_method"],
            $vendor->phone,
            $bs->base_currency_symbol_position,
            $bs->base_currency_symbol,
            $bs->base_currency_text,
            $transaction_id,
            $package->title,
            $lastMemb
          );

          $mailer = new MegaMailer();
          $data = [
            'toMail' => $vendor->email,
            'toName' => $vendor->fname,
            'username' => $vendor->username,
            'package_title' => $package->title,
            'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
            'activation_date' => $activation->toFormattedDateString(),
            'expire_date' => $expire->format('Y') == '9999' ? 'Lifetime' : $expire->toFormattedDateString(),
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
      }
    }

    // If paymentId is missing or not paid, redirect to cancel
    return redirect()->route('payment.cancel');
  }


  public function cancelPayment()
  {
    session()->flash('warning', __('cancel_payment'));
    return redirect()->route('vendor.plan.extend.checkout', ['package_id' => session()->get('request')['package_id']])->withInput(session()->get('request'));
  }
}
