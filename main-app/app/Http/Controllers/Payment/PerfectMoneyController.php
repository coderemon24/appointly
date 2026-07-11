<?php

namespace App\Http\Controllers\Payment;

use App\Models\Package;
use App\Models\Membership;
use Illuminate\Http\Request;
use App\Http\Helpers\MegaMailer;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\PaymentGateway\OnlineGateway;
use App\Http\Controllers\Vendor\VendorCheckoutController;

class PerfectMoneyController extends Controller
{
  public function paymentProcess($request, $_amount, $_success_url, $_cancel_url)
  {
    Session::put('request', $request->all());
    $paymentMethod = OnlineGateway::where('keyword', 'perfect_money')->first();
    $paydata = json_decode($paymentMethod->information, true);
    $randomNo = substr(uniqid(), 0, 8);
    $bs = Basic::select('base_currency_text', 'base_currency_rate', 'website_title')->first();

    $val['PAYEE_ACCOUNT'] = $paydata['perfect_money_wallet_id'];;
    $val['PAYEE_NAME'] = $bs->website_title;
    $val['PAYMENT_ID'] = "$randomNo"; //random id
    $val['PAYMENT_AMOUNT'] = $_amount;
    $val['PAYMENT_UNITS'] = "$bs->base_currency_text";

    $val['STATUS_URL'] = $_success_url;
    $val['PAYMENT_URL'] = $_success_url;
    $val['PAYMENT_URL_METHOD'] = 'GET';
    $val['NOPAYMENT_URL'] = $_cancel_url;
    $val['NOPAYMENT_URL_METHOD'] = 'GET';
    $val['SUGGESTED_MEMO'] = "$request->email";
    $val['BAGGAGE_FIELDS'] = 'IDENT';

    $data['val'] = $val;
    $data['method'] = 'post';
    $data['website_title'] = $bs->website_title;
    $data['url'] = 'https://perfectmoney.com/api/step1.asp';

    Session::put('payment_id', $randomNo);
    Session::put('amount', $_amount);
    return view('payments.perfect-money')->with('data', $data);
  }
  public function successPayment(Request $request)
  {
    $requestData = Session::get('request');
    $amo = $request['PAYMENT_AMOUNT'];
    $track = $request['PAYMENT_ID'];
    $id = Session::get('payment_id');
    $final_amount = Session::get('amount');
    $paymentMethod = OnlineGateway::where('keyword', 'perfect_money')->first();
    $perfectMoneyInfo = json_decode($paymentMethod->information, true);
    $bs = Basic::select('base_currency_text', 'base_currency_rate', 'website_title')->first();

    if ($request->PAYEE_ACCOUNT == $perfectMoneyInfo['perfect_money_wallet_id']  && $track == $id && $amo == round($final_amount, 2)) {
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
      $bs = Basic::select('base_currency_text', 'base_currency_rate')->first();
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
