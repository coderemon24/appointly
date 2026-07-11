<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
  public static function send($to, $invoice, $template, $templateParams, $params = [])
  {
    $url = "https://graph.facebook.com/v22.0/" . env('WHATSAPP_PHONE_NUMBER_ID') . "/messages";
    $token = env('WHATSAPP_ACCESS_TOKEN');

    $data = [
      "messaging_product" => "whatsapp",
      "to" => $to,
      'type' => 'template',
      "template" => [
        "name" => $template->name,
        "language" => [
          "code" => $template->language_code
        ],
        "components" => []
      ],
    ];

    //if template has invoice
    if (in_array('invoice', $templateParams)) {
      $invoiceFile = $invoice ? url('assets/file/invoices/service/' . $invoice) : null;
      if ($invoiceFile) {
        $data['template']['components'][] = [
          "type" => "header",
          "parameters" => [
            [
              "type" => "document",
              "document" => [
                "link" => $invoiceFile,
                "filename" => $invoice
              ]
            ]
          ]
        ];
      }
    }


    //send components parameters
    if (!empty($params)) {
      $data['template']['components'][] = [
        "type" => "body",
        "parameters" => $params
      ];
    }

    $response = Http::withHeaders([
      'Authorization' => 'Bearer ' . $token,
      'Content-Type' => 'application/json'
    ])->post($url, $data);

    //check response
    $responseData = $response->json();
    $error = $responseData['error'] ?? null;
    if ($error) {
      return ['status' => 'error', 'message' => $error];
    }

    return ['status' => 'success', 'response' => $responseData];
  }
}
