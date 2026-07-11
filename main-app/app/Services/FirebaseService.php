<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\Language;
use Kreait\Firebase\Factory;
use App\Models\Services\ServiceBooking;
use App\Models\Services\ServiceContent;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
  public static function send($token, $firebase_admin_json, $booking_id, $title, $subtitle)
  {
    if (is_null($firebase_admin_json)) {
      return ['status' => 'error', 'message' => 'Firebase admin json file not found.'];
    }

    $language = Language::where('is_default', 1)->first();

    //initialize Firebase messaging service with service account
    $factory = (new Factory)
      ->withServiceAccount(public_path('assets/file/') . $firebase_admin_json);
    $messaging = $factory->createMessaging();

    $body = [];

    $booking = ServiceBooking::where('id', $booking_id)->first();
    $service = ServiceContent::where([['service_id', $booking->service_id], ['language_id', $language->id]])
      ->select('name', 'slug')
      ->first();
    $body['service_title'] = $service->name;
    $body['service_slug'] = $service->slug;
    $body['service_id'] = $booking->service_id;
    $body['user_id'] = $booking->user_id;
    $body['customer_name'] = $booking->customer_name;
    $body['customer_phone'] = $booking->customer_phone;
    $body['customer_email'] = $booking->customer_email;
    $body['customer_address'] = $booking->customer_address;
    $body['customer_country'] = $booking->customer_country;
    $body['booking_date'] = $booking->booking_date;
    $body['start_time'] = $booking->start_date;
    $body['end_time'] = $booking->end_date;
    $body['vendor_id'] = $booking->vendor_id;
    $body['payment_method'] = $booking->payment_method;
    $body['payment_status'] = $booking->payment_status;
    $body['order_status'] = $booking->order_status;
    $body['order_number'] = $booking->order_number;
    $body['zoom_info'] = $booking->zoom_info;
    $body['customer_paid'] = custom_format_price($booking->customer_paid, $booking->currency_symbol, $booking->currency_symbol_position);

    try {
      //create and send FCM notification to the given device token
      $message = CloudMessage::withTarget('token', $token)
        ->withNotification(Notification::create($title, $subtitle))
        ->withData($body);

      $messaging->send($message);
    } catch (\Kreait\Firebase\Exception\Messaging\InvalidArgument $e) {
      FcmToken::where('token', $token)->delete();
      return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (\Exception $e) {
      return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
    }

    return response()->json(['status' => 'success', 'message' => 'Notification sent successfully.']);
  }
}
