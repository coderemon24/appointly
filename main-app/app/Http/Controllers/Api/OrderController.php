<?php

namespace App\Http\Controllers\Api;

use App\Models\Language;
use App\Models\Shop\Product;
use Illuminate\Http\Request;
use App\Models\Shop\ProductOrder;
use App\Models\BasicSettings\Basic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\FrontEnd\MiscellaneousController;

class OrderController extends Controller
{
  //index
  public function order(Request $request)
  {
    $misc = new MiscellaneousController();

    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();


    $data['bgImg'] = asset('assets/img/' . $misc->getBreadcrumb()->breadcrumb);

    $data['pageHeading'] = $misc->getPageHeading($language);

    $data['orders'] = ProductOrder::join('product_purchase_items', 'product_purchase_items.product_order_id', '=', 'product_orders.id')
      ->join('product_contents', 'product_contents.product_id', 'product_purchase_items.product_id')
      ->where('product_contents.language_id', $language->id)
      ->where('product_orders.user_id', Auth::id())
      ->when($request->product, function ($query) use ($request) {
        $query->where(function ($subQuery) use ($request) {
          $subQuery->where('product_orders.order_number', 'like', '%' . $request->product . '%')
            ->orWhere('product_purchase_items.title', 'like', '%' . $request->product . '%');
        });
      })
      ->select(
        'product_contents.title',
        'product_contents.slug',
        'product_orders.payment_status',
        'product_orders.order_status',
        'product_orders.id',
        'product_orders.created_at',
      )
      ->orderBy('product_orders.id', 'desc')
      ->get();

    return response()->json($data);
  }
  //details
  public function details($id, Request $request)
  {
    $misc = new MiscellaneousController();

    //get language
    $locale = $request->header('Accept-Language');
    $language = $locale ? Language::where('code', $locale)->first()
      : Language::where('is_default', 1)->first();

    $breadcrumb = null;
    if (!is_null($misc->getBreadcrumb()->breadcrumb)) {
      $breadcrumb = $misc->getBreadcrumb()->breadcrumb;
    }
    $data['bgImg'] = asset('assets/img/' . $breadcrumb);


    $data['pageHeading'] = $misc->getPageHeading($language);

    $order = ProductOrder::query()->find($id);

    if (!$order) {
      return response()->json([
        'success' => false,
        'message' => 'Order not found'
      ]);
    }

    if ($order) {

      $data['order'] = $order;

      $data['tax'] = Basic::select('product_tax_amount')->first();

      $items = $order->item()->get();

      $items->map(function ($item) use ($language) {
        $product = $item->productInfo()->first();
        $item['price'] = $product->current_price;
        $item['productType'] = $product->product_type;
        $item['inputType'] = $product->input_type;
        $item['link'] = $product->link;
        $content = $product->content()->where('language_id', $language->id)->first();

        $item['productTitle'] = $content ? $content->title : '';
        $item['slug'] = $content ? $content->slug : '';
      });

      $data['items'] = $items;

      return response()->json($data);
    }

    return response()->json([
      'success' => false,
      'message' => 'Order not found'
    ]);
  }

  public function download($id)
  {
    $product = Product::find($id);
    $filePath = asset('assets/file/products/' . $product->file);

    // Return a download response
    return response()->json([
      'success' => true,
      'file' => $filePath
    ]);
  }
}
