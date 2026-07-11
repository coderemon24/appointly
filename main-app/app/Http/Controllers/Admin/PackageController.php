<?php

namespace App\Http\Controllers\Admin;

use App\Models\Package;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Response;
use App\Http\Requests\Package\PackageStoreRequest;
use App\Http\Requests\Package\PackageUpdateRequest;

class PackageController extends Controller
{
  public function settings()
  {
    $data['abe'] = Basic::first();
    return view('admin.packages.settings', $data);
  }

  public function updateSettings(Request $request)
  {
    $be = Basic::first();
    $be->expiration_reminder = $request->expiration_reminder;
    $be->save();

    Session::flash('success', __('Settings updated successfully!'));
    return back();
  }

  /**
   * Display a listing of the resource.
   *
   *
   */
  public function index(Request $request)
  {
    if (session()->has('lang')) {
      $currentLang = Language::where('code', session()->get('lang'))->first();
    } else {
      $currentLang = Language::where('is_default', 1)->first();
    }
    $search = $request->search;
    $data['bex'] = $currentLang->basic_extended;
    $data['packages'] = Package::query()->when($search, function ($query, $search) {
      return $query->where('title', 'like', '%' . $search . '%');
    })->orderBy('created_at', 'DESC')->get();

    $data['whatsapp_manager_status'] = DB::table('basic_settings')
      ->value('whatsapp_manager_status');

    return view('admin.packages.index', $data);
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create()
  {
    //
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param \Illuminate\Http\Request $request
   *
   */
  public function store(PackageStoreRequest $request)
  {
    try {
      return DB::transaction(function () use ($request) {
        Package::create($request->all());
        Session::flash('success', __("Package Created Successfully"));
        return Response::json(['status' => 'success'], 200);
      });
    } catch (\Throwable $e) {
      return $e;
    }
  }

  /**
   * Display the specified resource.
   *
   * @param int $id
   * @return \Illuminate\Http\Response
   */
  public function show($id)
  {
    //
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param int $id
   * @return
   */
  public function edit($id)
  {
    if (session()->has('lang')) {
      $currentLang = Language::where('code', session()->get('lang'))->first();
    } else {
      $currentLang = Language::where('is_default', 1)->first();
    }
    $data['bex'] = $currentLang->basic_extended;
    $data['package'] = Package::query()->findOrFail($id);
    $data['whatsapp_manager_status'] = DB::table('basic_settings')
      ->value('whatsapp_manager_status');
    return view("admin.packages.edit", $data);
  }

  /**
   * Update the specified resource in storage.
   *
   * @param \Illuminate\Http\Request $request
   * @param int $id
   *
   */
  public function update(PackageUpdateRequest $request)
  {
    try {
      if (!array_key_exists('is_trial', $request->all())) {
        $request['is_trial'] = "0";
        $request['trial_days'] = 0;
      }
      return DB::transaction(function () use ($request) {
        Package::query()->findOrFail($request->package_id)
          ->update($request->all());
        Session::flash('success', __("Package Update Successfully"));
        return Response::json(['status' => 'success'], 200);
      });
    } catch (\Throwable $e) {
      return $e;
    }
  }


  public function delete(Request $request)
  {
    $pacakge_count = Package::get()->count();
    if ($pacakge_count <= 1) {
      Session::flash('warning', __('You have to keep at least one package.'));
      return back();
    }
    try {
      return DB::transaction(function () use ($request) {
        $package = Package::query()->findOrFail($request->package_id);
        if ($package->memberships()->count() > 0) {
          foreach ($package->memberships as $key => $membership) {
            @unlink(public_path('assets/front/img/membership/receipt/') . $membership->receipt);
            $membership->delete();
          }
        }
        $package->delete();
        Session::flash('success', __('Package deleted successfully!'));
        return back();
      });
    } catch (\Throwable $e) {
      return $e;
    }
  }

  public function bulkDelete(Request $request)
  {
    try {
      return DB::transaction(function () use ($request) {
        $ids = $request->ids;
        foreach ($ids as $id) {
          $package = Package::query()->findOrFail($id);
          if ($package->memberships()->count() > 0) {
            foreach ($package->memberships as $key => $membership) {
              @unlink(public_path('assets/front/img/membership/receipt/') . $membership->receipt);
              $membership->delete();
            }
          }
          $package->delete();
        }
        session()->flash('success', __('Package bulk deletion is successful!'));
        return Response::json(['status' => 'success'], 200);
      });
    } catch (\Throwable $e) {
      session()->flash('warning', __('Something went wrong!'));
      return Response::json(['status' => 'success'], 200);
    }
  }
}
