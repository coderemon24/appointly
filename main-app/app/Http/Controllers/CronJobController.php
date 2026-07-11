<?php

namespace App\Http\Controllers;

use PDF;
use Carbon\Carbon;
use App\Models\Vendor;
use App\Models\Package;
use App\Models\Language;
use App\Models\Membership;
use App\Models\VendorInfo;
use App\Http\Helpers\MegaMailer;
use App\Http\Helpers\BasicMailer;
use App\Models\Shop\ProductOrder;
use App\Models\BasicSettings\Basic;
use App\Jobs\SubscriptionExpiredMail;
use App\Jobs\SubscriptionReminderMail;
use App\Models\Services\ServiceBooking;
use App\Models\BasicSettings\MailTemplate;
use App\Http\Helpers\VendorPermissionHelper;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\FeaturedService\ServicePromotion;
use App\Http\Controllers\Admin\Transaction\TransactionController;

class CronJobController extends Controller
{
  public function expired()
  {
    try {
      $bs = Basic::first();

      $expired_members = Membership::whereDate('expire_date', Carbon::now()->subDays(1))->get();
      foreach ($expired_members as $key => $expired_member) {
        if (!empty($expired_member->vendor)) {
          $vendor = $expired_member->vendor;
          $current_package = VendorPermissionHelper::userPackage($vendor->id);
          if (is_null($current_package)) {
            SubscriptionExpiredMail::dispatch($vendor, $bs);
          }
        }
      }

      $remind_members = Membership::whereDate('expire_date', Carbon::now()->addDays($bs->expiration_reminder))->get();
      foreach ($remind_members as $key => $remind_member) {
        if (!empty($remind_member->vendor)) {
          $vendor = $remind_member->vendor;

          $nextPacakgeCount = Membership::where([
            ['vendor_id', $vendor->id],
            ['start_date', '>', Carbon::now()->toDateString()]
          ])->where('status', '<>', 2)->count();

          if ($nextPacakgeCount == 0) {
            SubscriptionReminderMail::dispatch($vendor, $bs, $remind_member->expire_date);
          }
        }
        \Artisan::call("queue:work --stop-when-empty");
      }
    } catch (\Exception $e) {
    }
  }

  public function check_payment()
  {
    //check iyzico pending payments for membership
    $iyzico_pending_memberships = Membership::where([['status', 0], ['payment_method', 'Iyzico']])->get();
    foreach ($iyzico_pending_memberships as $iyzico_pending_membership) {
      if (!is_null($iyzico_pending_membership->conversation_id)) {
        $result = $this->IyzicoPaymentStatus($iyzico_pending_membership->conversation_id);
        if ($result == 'success') {
          $this->updateIyzicoPendingMemership($iyzico_pending_membership->id, 1);
        } else {
          $this->updateIyzicoPendingMemership($iyzico_pending_membership->id, 2);
        }
      }
    }

    //check iyzico pending payments for service booking
    $iyzico_pending_bookings = ServiceBooking::where([['payment_status', 'pending'], ['payment_method', 'Iyzico']])->get();
    foreach ($iyzico_pending_bookings as $iyzico_pending_booking) {
      if (!is_null($iyzico_pending_booking->conversation_id)) {
        $result = $this->IyzicoPaymentStatus($iyzico_pending_booking->conversation_id);
        if ($result == 'success') {
          $this->updateIyzicoPendingBooking($iyzico_pending_booking->id, 'completed');
        } else {
          $this->updateIyzicoPendingBooking($iyzico_pending_booking->id, 'rejected');
        }
      }
    }

    //check iyzico pending payments for service featured
    $iyzico_pending_featureds = ServicePromotion::where([['payment_status', 'pending'], ['payment_method', 'Iyzico']])->get();
    foreach ($iyzico_pending_featureds as $iyzico_pending_featured) {
      if (!is_null($iyzico_pending_featured->conversation_id)) {
        $result = $this->IyzicoPaymentStatus($iyzico_pending_featured->conversation_id);
        if ($result == 'success') {
          $this->updateIyzicoPendingFeatured($iyzico_pending_featured->id, 'completed');
        } else {
          $this->updateIyzicoPendingFeatured($iyzico_pending_featured->id, 'rejected');
        }
      }
    }

    //check iyzico pending payments for product purchase
    $iyzico_pending_orders = ProductOrder::where([['payment_status', 'pending'], ['payment_method', 'Iyzico']])->get();
    foreach ($iyzico_pending_orders as $iyzico_pending_order) {
      if (!is_null($iyzico_pending_order->conversation_id)) {
        $result = $this->IyzicoPaymentStatus($iyzico_pending_order->conversation_id);
        if ($result == 'success') {
          $this->updateIyzicoPendingOrder($iyzico_pending_order->id, 'completed');
        } else {
          $this->updateIyzicoPendingOrder($iyzico_pending_order->id, 'rejected');
        }
      }
    }
  }


  /**
   * Iyzico Payment Status Check For Membership
   */
  private function IyzicoPaymentStatus($conversation_id)
  {
    $paymentMethod = OnlineGateway::where('keyword', 'iyzico')->first();
    $paydata = $paymentMethod->convertAutoData();


    $options = new \Iyzipay\Options();
    $options->setApiKey($paydata['api_key']);
    $options->setSecretKey($paydata['secret_key']);
    if ($paydata['sandbox_status'] == 1) {
      $options->setBaseUrl("https://sandbox-api.iyzipay.com");
    } else {
      $options->setBaseUrl("https://api.iyzipay.com"); // production mode
    }

    $request = new \Iyzipay\Request\ReportingPaymentDetailRequest();
    $request->setPaymentConversationId($conversation_id);

    $paymentResponse = \Iyzipay\Model\ReportingPaymentDetail::create($request, $options);
    $result = (array) $paymentResponse;

    foreach ($result as $key => $data) {
      $data = json_decode($data, true);
      if ($data['status'] == 'success' && !empty($data['payments'])) {
        if (is_array($data['payments'])) {
          if ($data['payments'][0]['paymentStatus'] == 1) {
            return 'success';
          } else {
            return 'not found';
          }
        } else {
          return 'not found';
        }
      } else {
        return 'not found';
      }
    }
    return 'not found';
  }

  /**
   * update iyzico pending membership
   */
  private function updateIyzicoPendingMemership($id, $status)
  {
    $bs = Basic::first();
    $membership = Membership::query()->where('id', $id)->first();
    $vendor = Vendor::query()->where('id', $membership->vendor_id)->first();

    $package = Package::query()->where('id', $membership->package_id)->first();
    $count_membership = Membership::query()->where('vendor_id', $membership->vendor_id)->count();

    //comparison date
    $date1 = Carbon::createFromFormat('m/d/Y', \Carbon\Carbon::parse($membership->start_date)->format('m/d/Y'));
    $date2 = Carbon::createFromFormat('m/d/Y', \Carbon\Carbon::now()->format('m/d/Y'));

    if ($status == 1) {
      $member['first_name'] = $vendor->first_name;
      $member['last_name'] = $vendor->last_name;
      $member['username'] = $vendor->username;
      $member['email'] = $vendor->email;
      $data['payment_method'] = $membership->payment_method;


      $result = $date1->gte($date2);
      if ($result) {
        $data['start_date'] = $membership->start_date;
        $data['expire_date'] = $membership->expire_date;
      } else {
        $data['start_date'] = Carbon::today()->format('d-m-Y');
        if ($package->term === "daily") {
          $data['expire_date'] = Carbon::today()->addDay()->format('d-m-Y');
        } elseif ($package->term === "weekly") {
          $data['expire_date'] = Carbon::today()->addWeek()->format('d-m-Y');
        } elseif ($package->term === "monthly") {
          $data['expire_date'] = Carbon::today()->addMonth()->format('d-m-Y');
        } elseif ($package->term === "lifetime") {
          $data['expire_date'] = Carbon::maxValue()->format('d-m-Y');
        } else {
          $data['expire_date'] = Carbon::today()->addYear()->format('d-m-Y');
        }
        $membership->update(['start_date' =>  Carbon::parse($data['start_date'])]);
        $membership->update(['expire_date' =>  Carbon::parse($data['expire_date'])]);
      }

      // if previous membership package is lifetime, then exipre that membership
      $previousMembership = Membership::query()
        ->where([
          ['vendor_id', $vendor->id],
          ['start_date', '<=', Carbon::now()->toDateString()],
          ['expire_date', '>=', Carbon::now()->toDateString()]
        ])
        ->where('status', 1)
        ->orderBy('created_at', 'DESC')
        ->first();
      if (!is_null($previousMembership)) {
        $previousPackage = Package::query()
          ->select('term')
          ->where('id', $previousMembership->package_id)
          ->first();
        if ($previousPackage->term === 'lifetime' || $previousMembership->is_trial == 1) {
          $yesterday = Carbon::yesterday()->format('d-m-Y');
          $previousMembership->expire_date = Carbon::parse($yesterday);
          $previousMembership->save();
        }
      }

      if ($count_membership > 1) {
        $mailTemplate = 'package_purchase_membership_accepted';
        $mailType = 'paymentAcceptedForMembershipExtensionOfflineGateway';
      } else {
        $mailTemplate = 'package_purchase_membership_accepted';
        $mailType = 'paymentAcceptedForRegistrationOfflineGateway';
        $vendor->update([
          'status' => 1
        ]);
      }
      $filename = $this->makeInvoice($data, "membership", $member, null, $membership->price, "offline", $vendor->phone, $bs->base_currency_symbol_position, $bs->base_currency_symbol, $bs->base_currency_text, $membership->transaction_id, $package->title, $membership);

      $mailer = new MegaMailer();
      $data = [
        'toMail' => $vendor->email,
        'toName' => $vendor->fname,
        'username' => $vendor->username,
        'package_title' => $package->title,
        'package_price' => ($bs->base_currency_text_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_text_position == 'right' ? ' ' . $bs->base_currency_text : ''),
        'activation_date' => $data['start_date'],
        'expire_date' => $package->term == "lifetime" ? 'Lifetime' : $data['expire_date'],
        'membership_invoice' => $filename,
        'website_title' => $bs->website_title,
        'templateType' => $mailTemplate,
        'type' => $mailType
      ];

      //transaction create
      $after_balance = NULL;
      $pre_balance = NULL;
      $transactionData = [
        'vendor_id' => $vendor->id,
        'transaction_type' => 'membership_buy',
        'pre_balance' => $pre_balance,
        'actual_total' => $membership->price,
        'after_balance' => $after_balance,
        'admin_profit' => $membership->price,
        'payment_method' => $membership->payment_method,
        'currency_symbol' => $bs->base_currency_symbol,
        'currency_symbol_position' => $bs->base_currency_symbol_position,
        'payment_status' => 'completed',
      ];
      store_transaction($transactionData);

      $mailer->mailFromAdmin($data);
      @unlink(public_path('assets/front/invoices/' . $filename));
    } elseif ($status == 2) {
      if ($count_membership > 1) {

        $mailTemplate = 'package_purchase_membership_rejected';
        $mailType = 'paymentRejectedForMembershipExtensionOfflineGateway';
      } else {

        $mailTemplate = 'package_purchase_membership_rejected';
        $mailType = 'paymentRejectedForRegistrationOfflineGateway';
      }

      $mailer = new MegaMailer();
      $data = [
        'toMail' => $vendor->email,
        'toName' => $vendor->fname,
        'username' => $vendor->username,
        'package_title' => $package->title,
        'package_price' => ($bs->base_currency_symbol_position == 'left' ? $bs->base_currency_text . ' ' : '') . $package->price . ($bs->base_currency_symbol_position == 'right' ? ' ' . $bs->base_currency_text : ''),
        'website_title' => $bs->website_title,
        'templateType' => $mailTemplate,
        'type' => $mailType
      ];
      $mailer->mailFromAdmin($data);
    }


    $membership->update(['status' => $status]);
  }

  /**
   * update iyzico pending appointment
   */
  private function updateIyzicoPendingBooking($id, $status)
  {
    $language = Language::where('is_default', 1)->first();
    $language_id = $language->id;
    $appointment = ServiceBooking::with(['serviceContent' => function ($q) use ($language_id) {
      $q->where('language_id', $language_id);
    }])
      ->findOrFail($id);


    if ($status == 'completed') {
      $appointment->update([
        'payment_status' => 'completed'
      ]);
      //send mail
      $type = 'service_payment_approved';
      payemntStatusMail($type, $id);

      $arrData = array(
        'customer_paid' => $appointment->customer_paid,
        'paymentMethod' => $appointment->payment_method,
        'currencySymbol' => $appointment->currency_symbol,
        'currencySymbolPosition' => $appointment->currency_symbol_position,
        'paymentStatus' => $appointment->payment_status,
        'vendor_id' => $appointment->vendor_id,
      );
      $transaction = new TransactionController();
      $transaction->storeTransaction($arrData);
    } else {
      //after reject
      $appointment->update([
        'payment_status' => 'rejected'
      ]);

      //send mail
      $type = 'service_payment_rejected';
      payemntStatusMail($type, $id);
    }
  }

  /**
   * update iyzico pending featured
   */
  private function updateIyzicoPendingFeatured($id, $status)
  {
    $featuredRequest = ServicePromotion::find($id);
    if (!$featuredRequest) return;

    // Get default language
    $language = Language::where('is_default', 1)->first();
    $service = $featuredRequest->serviceContent()
      ->where('language_id', $language->id)
      ->select('name', 'slug')
      ->first();

    $url = $service ? route('frontend.service.details', ['slug' => $service->slug, 'id' => $featuredRequest->service_id]) : null;
    $serviceName = $service ? truncateString($service->name, 50) : null;

    // Basic info
    $websiteTitle = optional(Basic::select('website_title')->first())->website_title ?? 'Website';
    $vendorName = optional(VendorInfo::where('vendor_id', $featuredRequest->vendor_id)->first())->name ?? 'Vendor';
    $vendorEmail = optional($featuredRequest->vendor)->email;

    // Update payment status
    $paymentStatus = $status;
    $featuredRequest->payment_status = $paymentStatus;

    // Generate invoice and save
    $invoice = $this->generateInvoice($featuredRequest);
    $featuredRequest->invoice = $invoice;
    $featuredRequest->save();

    // Create transaction if approved
    if ($status === 'approved') {
      $transactionData = [
        'vendor_id' => $featuredRequest->vendor_id,
        'transaction_type' => 'featured_service',
        'pre_balance' => null,
        'actual_total' => $featuredRequest->amount,
        'after_balance' => null,
        'admin_profit' => $featuredRequest->amount,
        'payment_method' => $featuredRequest->payment_method,
        'currency_symbol' => $featuredRequest->currency_symbol,
        'currency_symbol_position' => $featuredRequest->currency_symbol_position,
        'payment_status' => $featuredRequest->payment_status,
      ];
      store_transaction($transactionData);
    }

    // Mail template type
    $mailType = $status === 'approved' ? 'featured_request_payment_approved' : 'featured_request_payment_rejected';

    $mailTemplate = MailTemplate::where('mail_type', $mailType)->first();
    if ($mailTemplate && $vendorEmail) {
      $replacements = [
        '{service_title}' => $url ? "<a href='{$url}'>{$serviceName}</a>" : '',
        '{amount}' => symbolPrice($featuredRequest->amount),
        '{username}' => $vendorName,
        '{website_title}' => $websiteTitle,
      ];

      $mailData = [
        'subject' => $mailTemplate->mail_subject,
        'body' => str_replace(array_keys($replacements), array_values($replacements), $mailTemplate->mail_body),
        'recipient' => $vendorEmail,
        'invoice' => public_path('assets/file/invoices/featured/service/') . $invoice,
      ];

      try {
        BasicMailer::sendMail($mailData);
      } catch (\Exception $e) {
        \Log::error('Failed to send featured service status email: ' . $e->getMessage());
      }
    }
  }
  /**
   * featured service invoice
   */
  public function generateInvoice($requestInfo)
  {
    $fileName = $requestInfo->order_number . '.pdf';
    $data['orderInfo'] = $requestInfo;

    $directory = public_path('assets/file/invoices/featured/service/');
    @mkdir($directory, 0775, true);

    $fileLocated = $directory . $fileName;

    try {
      PDF::loadView('frontend.services.featured-service.invoice', $data)->save($fileLocated);
    } catch (\Exception $e) {
      \Log::error('Invoice PDF generation failed: ' . $e->getMessage());
    }

    return $fileName;
  }

  /**
   * update iyzico pending orders
   */
  private function updateIyzicoPendingOrder($id, $status)
  {
    $order = ProductOrder::find($id);

    if ($status == 'completed') {
      if ($order->payment_status == 'rejected' && $order->order_status != 'rejected') {
        $this->changeProductQuantity($order, 'decrease');
      }

      $order->update([
        'payment_status' => 'completed'
      ]);

      $statusMsg = 'Your payment is complete.';

      // generate an invoice in pdf format
      $invoice = $this->productInvoice($order);

      // then, update the invoice field info in database
      $order->update([
        'invoice' => $invoice
      ]);

      //transaction create
      $after_balance = NULL;
      $pre_balance = NULL;
      $transactionData = [
        'transaction_id' => time(),
        'vendor_id' => $arrData['vendor_id'] ?? 0,
        'transaction_type' => 'product_purchase',
        'pre_balance' => $pre_balance,
        'actual_total' => $order->grand_total,
        'after_balance' => $after_balance,
        'admin_profit' => $order->grand_total,
        'payment_method' => $order->payment_method,
        'currency_symbol' => $order->currency_symbol,
        'currency_symbol_position' => $order->currency_symbol_position,
        'payment_status' => 'completed',
      ];
      store_transaction($transactionData);
    } else {
      if ($order->payment_status != 'rejected' && $order->order_status != 'rejected') {
        $this->changeProductQuantity($order, 'increase');
      }

      $order->update([
        'payment_status' => 'rejected'
      ]);

      $statusMsg = 'Your payment has been rejected.';
    }

    $mailData = [];

    if (isset($invoice)) {
      $mailData['invoice'] = public_path('assets/file/invoices/product/') . $invoice;
    }

    $mailData['subject'] = 'Notification of payment status';

    $mailData['body'] = 'Hi ' . $order->billing_first_name . ' ' . $order->billing_last_name . ',<br/><br/>This email is to notify the payment status of your product purchase. ' . $statusMsg;

    $mailData['recipient'] = $order->billing_email;

    $mailData['sessionMessage'] = __('Payment status updated & mail has been sent successfully!');

    BasicMailer::sendMail($mailData);
  }

  /**
   * product invoice
   */
  public function productInvoice($orderInfo)
  {
    $fileName = $orderInfo->order_number . '.pdf';

    $data['orderInfo'] = $orderInfo;

    $items = $orderInfo->item()->get();

    $items->map(function ($item) {
      $product = $item->productInfo()->first();
      $item['price'] = $product->current_price * $item->quantity;
    });

    $data['productList'] = $items;

    $directory = public_path('assets/file/invoices/product/');
    @mkdir($directory, 0775, true);

    $fileLocated = $directory . $fileName;

    $data['taxData'] = Basic::select('product_tax_amount')->first();

    PDF::loadView('frontend.shop.invoice', $data)->save($fileLocated);

    return $fileName;
  }
}
