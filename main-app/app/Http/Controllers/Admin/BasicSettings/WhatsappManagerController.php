<?php

namespace App\Http\Controllers\Admin\BasicSettings;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class WhatsappManagerController extends Controller
{
  public function index()
  {
    $templates = DB::table('whatsapp_templates')->get();
    return view('admin.basic-settings.whatsapp.index', compact('templates'));
  }
  public function edit($id)
  {
    $template = DB::table('whatsapp_templates')->where('id', $id)->first();
    return view('admin.basic-settings.whatsapp.edit', compact('template'));
  }

  public function update($id, Request $request)
  {
    $request->validate([
      'name' => 'required',
      'language_code' => 'required',
      'type' => 'required',
      'params' => 'required|array'
    ]);


    DB::table('whatsapp_templates')->where('id', $id)->update([
      'name' => Str::lower($request->name),
      'language_code' => Str::lower($request->language_code),
      'params' => json_encode($request->params ?? []),
      'type' => $request->type
    ]);

    session()->flash('success', 'Template updated successfully!');
    return redirect()->back();
  }
}
