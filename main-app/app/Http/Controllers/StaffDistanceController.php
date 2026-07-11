<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\Staff\Staff;
use App\Models\Staff\StaffContent;
use App\Models\VendorInfo;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;

class StaffDistanceController extends Controller
{
  public function getCoordinates($address)
  {
    $bs = Basic::select('google_map_api_key', 'google_map_status', 'google_map_radius')->first();
    $apiKey = $bs->google_map_api_key;

    // Check cache first
    $cacheKey = 'coordinates:' . md5($address);
    $coordinates = Cache::get($cacheKey);

    if ($coordinates) {
      return $coordinates;
    }

    $client = new Client();
    try {
      $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
        'query' => [
          'address' => $address,
          'key' => $apiKey,
        ],
      ]);

      $data = json_decode($response->getBody()->getContents());

      if ($data->status == 'OK') {
        $location = $data->results[0]->geometry->location;
        $coordinates = [
          'lat' => $location->lat,
          'lng' => $location->lng,
        ];

        // Cache the coordinates for 1 hour
        Cache::put($cacheKey, $coordinates, 1440);
        return $coordinates;
      }
    } catch (RequestException $e) {
      // Log error or handle it as needed
      \Log::error('Guzzle request failed: ' . $e->getMessage());
    }

    return null;  // Unable to get coordinates
  }

  function calculateDistance($lat1, $lon1, $lat2, $lon2)
  {
    $earthRadius = 6371000;  // Earth radius in meters

    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    $latDiff = $lat2 - $lat1;
    $lonDiff = $lon2 - $lon1;

    $a = sin($latDiff / 2) * sin($latDiff / 2) +
      cos($lat1) * cos($lat2) *
      sin($lonDiff / 2) * sin($lonDiff / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;  // Distance in meters
  }

  public function getDistanceBetweenAddresses($address1, $address2)
  {
    $coordinates1 = $this->getCoordinates($address1);
    $coordinates2 = $this->getCoordinates($address2);

    if ($coordinates1 && $coordinates2) {
      $distance = $this->calculateDistance(
        $coordinates1['lat'],
        $coordinates1['lng'],
        $coordinates2['lat'],
        $coordinates2['lng']
      );
      return $distance;  // Distance in meters
    }

    return null;  // Unable to calculate the distance
  }

  public function index($address, $languageId, $id)
  {
    $bs = Basic::select('google_map_api_key', 'google_map_status', 'google_map_radius')->first();
    $vendorDistanceMap = [];  // This will map distances to service IDs

    $address1 = $address;

    // Fetch services with addresses for the specified language
    $contents = Staff::join('staff_contents', 'staff.id', '=', 'staff_contents.staff_id')
      ->where('staff_contents.language_id', $languageId)
      ->select('staff_contents.location', 'staff_contents.staff_id')
      ->get();

    foreach ($contents as $content) {
      $address2 = $content->location;
      $distance = null;

      if ($address2) {
        // Calculate distance between the two addresses
        $distance = $this->getDistanceBetweenAddresses($address1, $address2);

        if ($distance !== null && $distance <= $bs->google_map_radius) {
          // Map the distance to the corresponding service ID
          $vendorDistanceMap[$content->staff_id] = $distance;
        }
      }
    }

    // Extract the sorted service IDs (keys) from the sorted map
    $sortedServiceIds = array_keys($vendorDistanceMap);
    return $sortedServiceIds;
  }
}
