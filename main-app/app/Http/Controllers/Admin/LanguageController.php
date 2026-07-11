<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Language\StoreRequest;
use App\Http\Requests\Language\UpdateRequest;
use App\Models\CustomPage\Page;
use App\Models\CustomPage\PageContent;
use App\Models\Journal\Blog;
use App\Models\Journal\BlogInformation;
use App\Models\Language;
use App\Models\MenuBuilder;
use App\Models\Shop\Product;
use App\Models\Shop\ProductContent;
use App\Models\Shop\ProductOrder;
use App\Models\Shop\ProductPurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class LanguageController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    $languages = Language::all();

    return view('admin.language.index', compact('languages'));
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(StoreRequest $request)
  {
    // get all the keywords from the default file of language
    $data = file_get_contents(resource_path('lang/') . 'default.json');
    $adminData = file_get_contents(resource_path('lang/') . 'admin_default.json');

    // make a new json file for the new language
    $fileName = strtolower($request->code) . '.json';
    $AdminFileName = 'admin_' . strtolower($request->code) . '.json';

    // create the path where the new language json file will be stored
    $fileLocated = resource_path('lang/') . $fileName;
    $AdminFileLocated = resource_path('lang/') . $AdminFileName;

    // finally, put the keywords in the new json file and store the file in lang folder
    file_put_contents($fileLocated, $data);
    file_put_contents($AdminFileLocated, $adminData);
    $in = $request->all();
    $in['code'] = strtolower($request->code);

    // then, store data in db
    $language = Language::query()->create($in);

    $data = [];

    $data[] = [
      'text' => 'Home',
      "href" => "",
      "icon" => "empty",
      "target" => "_self",
      "title" => "",
      "type" => "home"
    ];
    $data[] = [
      'text' => 'Vendors',
      "href" => "",
      "icon" => "empty",
      "target" => "_self",
      "title" => "",
      "type" => "vendors"
    ];
    $data[] = [
      'text' => 'Shop',
      "href" => "",
      "icon" => "empty",
      "target" => "_self",
      "title" => "",
      "type" => "shop"
    ];

    $data[] = [
      'text' => 'Blog',
      "href" => "",
      "icon" => "empty",
      "target" => "_self",
      "title" => "",
      "type" => "blog"
    ];
    $data[] = [
      'text' => 'FAQ',
      "href" => "",
      "icon" => "empty",
      "target" => "_self",
      "title" => "",
      "type" => "faq"
    ];
    $data[] = [
      'text' => 'About Us',
      "href" => "",
      "icon" => "empty",
      "target" => "_self",
      "title" => "",
      "type" => "about-us"
    ];
    $data[] = [
      'text' => 'Contact',
      "href" => "",
      "icon" => "empty",
      "target" => "_self",
      "title" => "",
      "type" => "contact"
    ];
    MenuBuilder::create([
      'language_id' => $language->id,
      'menus' => json_encode($data, true)
    ]);
    // define the path for the language folder
    $langFolderPath = resource_path('lang/' . $language->code);
    if (!file_exists($langFolderPath)) {
      mkdir($langFolderPath, 0755, true);
    }
    // define the source path for the existing language files
    $sourcePath = resource_path('lang/admin_' . $language->code);
    // Check if the source directory exists
    if (is_dir($sourcePath)) {
      $files = scandir($sourcePath);
      foreach ($files as $file) {
        // Skip the current and parent directory indicators
        if ($file !== '.' && $file !== '..') {
          // Copy each file to the new language folder
          $sourceFilePath = $sourcePath . '/' . $file;
          $destinationFilePath = $langFolderPath . '/' . $file;

          copy($sourceFilePath, $destinationFilePath);
        }
      }
    }
    //update attributes with current keyword values
    $validationFilePath = resource_path('lang/admin_' . $language->code . '/validation.php');
    //update existing keywords for validation attributes
    $newKeys = $this->dashboardAttribute();
    $this->updateValidationAttribute($newKeys, $adminData, $validationFilePath);

    Session::flash('success', __('Language added successfully!'));

    return response()->json(['status' => 'success'], 200);
  }

  /**
   * Make a default language for this system.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function makeDefault($id)
  {
    // first, make other languages to non-default language
    $prevDefLang = Language::query()->where('is_default', '=', 1);

    $prevDefLang->update(['is_default' => 0]);

    // second, make the selected language to default language
    $language = Language::query()->find($id);

    $language->update(['is_default' => 1]);

    return back()->with('success', $language->name . ' ' . __('is set as default language.'));
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(UpdateRequest $request)
  {
    $language = Language::query()->find($request->id);
    $in = $request->all();
    $in['code'] = strtolower($request->code);

    if ($language->code !== $request->code) {
      /**
       * get all the keywords from the previous file,
       * which was named using previous language code
       */
      $data = file_get_contents(resource_path('lang/') . $language->code . '.json');
      $adminData = file_get_contents(resource_path('lang/') . 'admin_' . $language->code . '.json');

      // make a new json file for the new language (code)
      $fileName = strtolower($request->code) . '.json';
      $adminFileName = 'admin_' . strtolower($request->code) . '.json';

      // create the path where the new language (code) json file will be stored
      $fileLocated = resource_path('lang/') . $fileName;
      $adminFileLocated = resource_path('lang/') . $adminFileName;

      // then, put the keywords in the new json file and store the file in lang folder
      file_put_contents($fileLocated, $data);
      file_put_contents($adminFileLocated, $adminData);

      // define the path for the language folder
      $langFolderPath = resource_path('lang/' . $request->code);
      if (!file_exists($langFolderPath)) {
        mkdir($langFolderPath, 0755, true);
      }
      // define the source path for the existing language files
      $sourcePath = resource_path('lang/admin_' . $request->code);

      // Check if the source directory exists
      if (is_dir($sourcePath)) {
        $files = scandir($sourcePath);
        foreach ($files as $file) {
          // Skip the current and parent directory indicators
          if ($file !== '.' && $file !== '..') {
            // Copy each file to the new language folder
            copy($sourcePath . '/' . $file, $langFolderPath . '/' . $file);
          }
        }
      }
      // now, delete the previous language code file
      @unlink(resource_path('lang/') . $language->code . '.json');
      @unlink(resource_path('lang/') . 'admin_' . $language->code . '.json');
      // Delete language folder and its contents
      $dir = resource_path('lang/') . $language->code;
      if (is_dir($dir)) {
        $this->deleteDirectory($dir);
      }
      // Load validation attributes
      $validationFilePath = resource_path('lang/admin_' . $request->code . '/validation.php');
      //update existing keywords for validation attributes
      $newKeys = $this->dashboardAttribute();
      $this->updateValidationAttribute($newKeys, $adminData, $validationFilePath);

      // finally, update the info in db
      $language->update($in);
    } else {
      $language->update($in);
    }

    Session::flash('success', __('Language updated successfully!'));

    return response()->json(['status' => 'success'], 200);
  }

  /**
   * forntend keyword add
   */
  public function addKeyword(Request $request)
  {
    $rules = [
      'keyword' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }
    $languages = Language::get();
    foreach ($languages as $language) {
      // get all the keywords of the selected language
      $jsonData = file_get_contents(resource_path('lang/') . $language->code . '.json');

      // convert json encoded string into a php associative array
      $keywords = json_decode($jsonData, true);
      $datas = [];
      $datas[$request->keyword] = $request->keyword;

      foreach ($keywords as $key => $keyword) {
        $datas[$key] = $keyword;
      }
      //put data
      $jsonData = json_encode($datas);

      $fileLocated = resource_path('lang/') . $language->code . '.json';

      // put all the keywords in the selected language file
      file_put_contents($fileLocated, $jsonData);
    }

    //for default json
    // get all the keywords of the selected language
    $jsonData = file_get_contents(resource_path('lang/') . 'default.json');

    // convert json encoded string into a php associative array
    $keywords = json_decode($jsonData, true);
    $datas = [];
    $datas[$request->keyword] = $request->keyword;

    foreach ($keywords as $key => $keyword) {
      $datas[$key] = $keyword;
    }
    //put data
    $jsonData = json_encode($datas);

    $fileLocated = resource_path('lang/') . 'default.json';

    // put all the keywords in the selected language file
    file_put_contents($fileLocated, $jsonData);

    session()->flash('success', __('A new keyword has been added successfully for all languages!'));

    return Response::json(['status' => 'success'], 200);
  }

  /**
   * admin keyword add
   */
  public function addAdminKeyword(Request $request)
  {
    $rules = [
      'keyword' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }

    // Get all languages
    $languages = Language::get();

    // Create an associative array for new keywords
    $newKeyword = $request->keyword;
    $datas = [];

    // Update each language file
    foreach ($languages as $language) {
      $fileLocated = resource_path('lang/admin_' . $language->code . '.json');

      // Check if the language file exists, if not create it by copying from default
      if (!file_exists($fileLocated)) {
        $defaultContent = file_get_contents(resource_path('lang/admin_default.json'));
        file_put_contents($fileLocated, $defaultContent);
      }

      // Read the current language file only once
      $jsonData = file_get_contents($fileLocated);
      $keywords = json_decode($jsonData, true);

      // Add the new keyword to the existing keywords
      $keywords[$newKeyword] = $newKeyword;

      // Store the updated keywords to write later
      $datas[$fileLocated] = json_encode($keywords);
    }

    // Write all updates at once
    foreach ($datas as $file => $content) {
      file_put_contents($file, $content);
    }

    // Update the default JSON file as well
    $defaultFile = resource_path('lang/admin_default.json');
    $defaultContent = file_get_contents($defaultFile);
    $defaultKeywords = json_decode($defaultContent, true);
    $defaultKeywords[$newKeyword] = $newKeyword;

    // Write the updated default keywords
    file_put_contents($defaultFile, json_encode($defaultKeywords));

    session()->flash('success', __('A new keyword has been added successfully for all languages!'));

    return Response::json(['status' => 'success'], 200);
  }


  /**
   * Display all the keywords of specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function editKeyword($id)
  {
    $language = Language::query()->findOrFail($id);

    // get all the keywords of the selected language
    $jsonData = file_get_contents(resource_path('lang/') . $language->code . '.json');

    // convert json encoded string into a php associative array
    $keywords = json_decode($jsonData);

    return view('admin.language.edit-keyword', compact('language', 'keywords'));
  }

  /**
   * edit admin keyword page
   */
  public function editAdminKeyword($id)
  {
    $language = Language::query()->findOrFail($id);

    // get all the keywords of the selected language
    $jsonData = file_get_contents(resource_path('lang/') . 'admin_' . $language->code . '.json');

    // convert json encoded string into a php associative array
    $keywords = json_decode($jsonData);

    return view('admin.language.edit-admin-keyword', compact('language', 'keywords'));
  }

  /**
   * Update the keywords of specified resource in respective json file.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function updateKeyword(Request $request, $id)
  {
    $language = Language::query()->find($id);
    $arrData = $request['keyValues'];

    // first, check each key has value or not
    foreach ($arrData as $key => $value) {
      if ($value == null) {
        Session::flash('warning', 'Value is required for "' . $key . '" key.');

        return redirect()->back();
      }
    }

    $jsonData = json_encode($arrData);

    // Load validation attributes
    $validationFilePath = resource_path('lang/' . $language->code . '/validation.php');

    //update existing attributes
    $newKeys = $this->frontAttribute();
    $this->updateValidationAttribute($newKeys, $jsonData, $validationFilePath);

    $fileLocated = resource_path('lang/') . $language->code . '.json';

    // put all the keywords in the selected language file
    file_put_contents($fileLocated, $jsonData);

    Session::flash('success', $language->name . ' ' . __("language's keywords updated successfully!"));

    return redirect()->back();
  }

  /**
   * update admin keyword
   */
  public function updateAdminKeyword(Request $request, $id)
  {
    $language = Language::query()->find($id);
    $arrData = $request['keyValues'];

    // first, check each key has value or not
    foreach ($arrData as $key => $value) {
      if ($value == null) {
        Session::flash('warning', 'Value is required for "' . $key . '" key.');

        return redirect()->back();
      }
    }

    $fileLocated = resource_path('lang/') . 'admin_' . $language->code . '.json';

    // Load existing keywords from file
    $existingData = [];
    if (file_exists($fileLocated)) {
      $existingData = json_decode(file_get_contents($fileLocated), true) ?? [];
    }

    // Merge existing keywords with new ones (new keys overwrite old ones)
    $mergedData = array_merge($existingData, $arrData);

    //update attributes with current keyword values
    $validationFilePath = resource_path('lang/admin_' . $language->code . '/validation.php');
    //update existing keywords for validation attributes
    $newKeys = $this->dashboardAttribute();
    $this->updateValidationAttribute($newKeys, json_encode($arrData), $validationFilePath);

    // Save the updated data
    file_put_contents($fileLocated, json_encode($mergedData));

    Session::flash('success', $language->name . ' ' . __("language's keywords updated successfully!"));

    return redirect()->back();
  }
  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id)
  {
    $language = Language::query()->find($id);

    if ($language->is_default == 1) {
      return redirect()->back()->with('warning', __('Default language cannot be delete!'));
    } else {
      /**
       * delete website-menu info
       */
      $websiteMenuInfo = $language->menuInfo()->first();

      if (!empty($websiteMenuInfo)) {
        $websiteMenuInfo->delete();
      }

      /**
       * delete service-categories info
       */
      $serviceCategories = $language->serviceCategory()->get();
      foreach ($serviceCategories as $serviceCategory) {
        @unlink(public_path('assets/admin/img/category/') . $serviceCategory->image);
        $serviceCategory->delete();
      }

      /**
       * delete service-content
       */
      $serviceContents = $language->serviceContent()->get();
      foreach ($serviceContents as $serviceContent) {
        $serviceContent->delete();
      }

      /**
       * delete staff-content
       */
      $staffContents = $language->staffContent()->get();
      foreach ($staffContents as $staffContent) {
        $staffContent->delete();
      }
      /**
       * delete product-categories info
       */
      $productCategories = $language->productCategory()->get();

      if (count($productCategories) > 0) {
        foreach ($productCategories as $productCategory) {
          $productCategory->delete();
        }
      }

      $shippingCharges = $language->shippingCharge()->get();
      foreach ($shippingCharges as $shippingCharge) {
        $shippingCharge->delete();
      }

      /**
       * delete product infos
       */
      $productInfos = $language->productContent()->get();

      if (count($productInfos) > 0) {
        foreach ($productInfos as $productData) {
          $productInfo = $productData;
          $productData->delete();

          // delete the product if, this product does not contain any other product-contents in any other language
          $otherProductInfos = ProductContent::query()->where('language_id', '<>', $language->id)
            ->where('product_id', '=', $productInfo->product_id)
            ->get();

          if (count($otherProductInfos) == 0) {
            $product = Product::query()->find($productInfo->product_id);

            // delete purchase item records of this product
            $purchaseInfos = $product->purchase()->get();

            if (count($purchaseInfos) > 0) {
              foreach ($purchaseInfos as $purchaseData) {
                $purchaseInfo = $purchaseData;
                $purchaseData->delete();

                // delete the order if, this order does not contain any other items
                $otherPurchaseItems = ProductPurchaseItem::query()->where('product_id', '<>', $product->id)
                  ->where('product_order_id', '=', $purchaseInfo->product_order_id)
                  ->get();

                if (count($otherPurchaseItems) == 0) {
                  $order = ProductOrder::query()->find($purchaseInfo->product_order_id);

                  // delete order receipt
                  @unlink(public_path('assets/file/attachments/product/') . $order->receipt);

                  // delete order invoice
                  @unlink(public_path('assets/file/invoices/product/') . $order->invoice);

                  $order->delete();
                }
              }
            }

            // delete all the reviews of this product
            $reviews = $product->review()->get();

            if (count($reviews) > 0) {
              foreach ($reviews as $review) {
                $review->delete();
              }
            }

            // delete product featured image
            $featImg = $product->featured_image;
            @unlink(public_path('assets/img/products/featured-images/') . $featImg);

            // delete product slider images
            $sldImgs = json_decode($product->slider_images);

            foreach ($sldImgs as $sldImg) {
              @unlink(public_path('assets/img/products/slider-images/') . $sldImg);
            }

            // delete product zip file
            $zipFile = $product->file;
            @unlink(public_path('assets/file/products/') . $zipFile);

            $product->delete();
          }
        }
      }

      /**
       * delete vendor infos
       */
      $vendorInfos = $language->vendorInfo()->get();
      foreach ($vendorInfos as $vendorInfo) {
        $vendorInfo->delete();
      }

      /**
       * delete banner infos
       */
      $banners = $language->banner()->get();
      foreach ($banners as $banner) {
        @unlink(public_path('assets/img/banners/') . $banner->image);
        $banner->delete();
      }

      /**
       * delete workprocess infos
       */
      $workProcess = $language->workProcess()->get();
      foreach ($workProcess as $workProces) {
        $workProces->delete();
      }

      /**
       * delete about-us-section info
       */
      $aboutUsSecInfo = $language->aboutUsSection()->first();
      if (!empty($aboutUsSecInfo)) {
        $aboutUsSecInfo->delete();
      }

      /**
       * delete features infos
       */
      $features = $language->featuresInfos()->get();
      foreach ($features as $feature) {
        $feature->delete();
      }


      /**
       * delete testimonial infos
       */
      $testimonials = $language->testimonial()->get();

      if (count($testimonials) > 0) {
        foreach ($testimonials as $testimonial) {
          $clientImg = $testimonial->image;

          @unlink(public_path('assets/img/clients/') . $clientImg);
          $testimonial->delete();
        }
      }
      /**
       * delete footer-content info
       */
      $footerContentInfo = $language->footerContent()->first();

      if (!empty($footerContentInfo)) {
        $footerContentInfo->delete();
      }
      /**
       * delete footer-quick-links
       */
      $quickLinks = $language->footerQuickLink()->get();

      if (count($quickLinks) > 0) {
        foreach ($quickLinks as $quickLink) {
          $quickLink->delete();
        }
      }
      /**
       * delete custom-page infos
       */
      $customPageInfos = $language->customPageInfo()->get();

      if (count($customPageInfos) > 0) {
        foreach ($customPageInfos as $customPageData) {
          $customPageInfo = $customPageData;
          $customPageData->delete();

          // delete the custom-page if, this page does not contain any other page-content in any other language
          $otherPageContents = PageContent::query()->where('language_id', '<>', $language->id)
            ->where('page_id', '=', $customPageInfo->page_id)
            ->get();

          if (count($otherPageContents) == 0) {
            $page = Page::query()->find($customPageInfo->page_id);
            $page->delete();
          }
        }
      }
      /**
       * delete blog-categories info
       */
      $blogCategories = $language->blogCategory()->get();

      if (count($blogCategories) > 0) {
        foreach ($blogCategories as $blogCategory) {
          $blogCategory->delete();
        }
      }
      /**
       * delete blog infos
       */
      $blogInfos = $language->blogInformation()->get();

      if (count($blogInfos) > 0) {
        foreach ($blogInfos as $blogData) {
          $blogInfo = $blogData;
          $blogData->delete();

          // delete the blog if, this blog does not contain any other blog-information in any other language
          $otherBlogInfos = BlogInformation::query()->where('language_id', '<>', $language->id)
            ->where('blog_id', '=', $blogInfo->blog_id)
            ->get();

          if (count($otherBlogInfos) == 0) {
            $blog = Blog::query()->find($blogInfo->blog_id);
            @unlink(public_path('assets/img/blogs/') . $blog->image);
            $blog->delete();
          }
        }
      }
      /**
       * delete faq infos
       */
      $faqs = $language->faq()->get();

      if (count($faqs) > 0) {
        foreach ($faqs as $faq) {
          $faq->delete();
        }
      }
      /**
       * delete popup infos
       */
      $popups = $language->announcementPopup()->get();

      if (count($popups) > 0) {
        foreach ($popups as $popup) {
          @unlink(public_path('assets/img/popups/') . $popup->image);
          $popup->delete();
        }
      }

      $pageNames = $language->pageName()->get();
      foreach ($pageNames as $pageName) {
        $pageName->delete();
      }
      $seoInfos = $language->seoInfo()->get();
      foreach ($seoInfos as $seoInfo) {
        $seoInfo->delete();
      }
      /**
       * delete cookie-alert info
       */
      $cookieAlertInfo = $language->cookieAlertInfo()->first();

      if (!empty($cookieAlertInfo)) {
        $cookieAlertInfo->delete();
      }
      /**
       * delete the language json file
       */
      @unlink(resource_path('lang/') . $language->code . '.json');
      @unlink(resource_path('lang/') . 'admin_' . $language->code . '.json');
      // Delete language folder and its contents
      $dir = resource_path('lang/') . $language->code;
      if (is_dir($dir)) {
        $this->deleteDirectory($dir);
      }

      /**
       * finally, delete the language info from db
       */
      $language->delete();

      return redirect()->back()->with('success', __('Language deleted successfully!'));
    }
  }

  /**
   * Check the specified language is RTL or not.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function checkRTL($langid)
  {
    if ($langid > 0) {
      $lang = Language::where('id', $langid)->first();
    } else {
      return 0;
    }

    return $lang->direction;
  }

  //dashboard attribute
  public function dashboardAttribute()
  {
    //update existing keys
    $newKeys = [
      'direction' => 'direction',
      'keyword' => 'keyword',
      'name' => 'name',
      'username' => 'username',
      'email' => 'email address',
      'first_name' => 'first name',
      'last_name' => 'last name',
      'password' => 'password',
      'password_confirmation' => 'confirm password',
      'city' => 'city',
      'country' => 'country',
      'address' => 'address',
      'phone' => 'phone',
      'mobile' => 'mobile',
      'age' => 'age',
      'sex' => 'sex',
      'gender' => 'gender',
      'day' => 'day',
      'month' => 'month',
      'year' => 'year',
      'hour' => 'hour',
      'minute' => 'minute',
      'second' => 'second',
      'title' => 'title',
      'subtitle' => 'subtitle',
      'text' => 'text',
      'description' => 'description',
      'content' => 'content',
      'occupation' => 'occupation',
      'comment' => 'comment',
      'rating' => 'rating',
      'terms' => 'terms',
      'question' => 'question',
      'answer' => 'answer',
      'status' => 'status',
      'term' => 'term',
      'price' => 'price',
      'amount' => 'amount',
      'date' => 'date',
      'latitude' => 'latitude',
      'longitude' => 'longitude',
      'value' => 'value',
      'type' => 'type',
      'code' => 'code',
      'url' => 'url',
      'stock' => 'stock',
      'delay' => 'delay',
      'image' => 'image',
      'language_id' => 'language',
      'serial_number' => 'serial number',
      'category_id' => 'category',
      'slider_images' => 'slider images',
      'order_number' => 'order number',
      'staff_image' => 'staff image',
      'start_time' => 'start time',
      'end_time' => 'end time',
      'start_date' => 'start date',
      'end_date' => 'end date',
      'product_tax_amount' => 'product tax amount',
      'shipping_charge' => 'shipping charge',
      'short_text' => 'short text',
      'featured_image' => 'featured image',
      'current_price' => 'current price',
      'min_limit' => 'min limit',
      'max_limit' => 'max limit',
      'email_address' => 'email address',
      'contact_number' => 'contact number',
      'new_password' => 'new password',
      'new_password_confirmation' => 'new password confirmation',
      'google_adsense_publisher_id' => 'google adsense publisher id',
      'ad_type' => 'ad type',
      'resolution_type' => 'resolution type',
      'button_text' => 'button text',
      'button_url' => 'button url',
      'background_color_opacity' => 'background color opacity',
      'base_currency_symbol' => 'base currency symbol',
      'base_currency_symbol_position' => 'base currency symbol position',
      'base_currency_text' => 'base currency text',
      'base_currency_text_position' => 'base currency text position',
      'base_currency_rate' => 'base currency rate',
      'website_title' => 'website title',
      'secondary_color' => 'secondary color',
      'primary_color' => 'primary color',
      'preloader' => 'preloader',
      'logo' => 'logo',
      'favicon' => 'favicon',
      'smtp_host' => 'smtp host',
      'smtp_port' => 'smtp port',
      'encryption' => 'encryption',
      'from_name' => 'from name',
      'from_mail' => 'from mail',
      'smtp_password' => 'smtp password',
      'smtp_username' => 'smtp username',
      'mail_subject' => 'mail subject',
      'subject' => 'subject',
      'mail_body' => 'mail body',
      'cookie_alert_text' => 'cookie alert text',
      'role_id' => 'role_id',
      "paypal_status" => "paypal status",
      "paypal_sandbox_status" => "paypal sandbox status",
      "paypal_client_id" => "paypal client ID",
      "paypal_client_secret" => "paypal client secret",
      "instamojo_status" => "instamojo status",
      "instamojo_sandbox_status" => "instamojo sandbox status",
      "instamojo_key" => "instamojo API key",
      "instamojo_token" => "instamojo auth token",
      "paytm_status" => "paytm status",
      "paytm_environment" => "paytm environment",
      "paytm_merchant_key" => "paytm merchant key",
      "paytm_merchant_mid" => "paytm merchant MID",
      "paytm_merchant_website" => "paytm merchant website",
      "paytm_industry_type" => "paytm industry type",
      "stripe_status" => "stripe status",
      "stripe_key" => "stripe key",
      "stripe_secret" => "stripe secret",
      "flutterwave_status" => "flutterwave status",
      "flutterwave_public_key" => "flutterwave public key",
      "flutterwave_secret_key" => "flutterwave secret key",
      "razorpay_status" => "razorpay status",
      "razorpay_key" => "razorpay key",
      "razorpay_secret" => "razorpay secret",
      "mollie_status" => "mollie status",
      "mollie_key" => "mollie API key",
      "paystack_status" => "paystack status",
      "paystack_key" => "paystack API key",
      "mercadopago_status" => "mercadopago status",
      "mercadopago_sandbox_status" => "mercadopago sandbox status",
      "mercadopago_token" => "mercadopago token",
      "authorize_net_status" => "Authorize.Net status",
      "sandbox_check" => "sandbox check",
      "login_id" => "login ID",
      "transaction_key" => "transaction key",
      "public_key" => "public key",
      "google_map_api_key" => "google map api key",
      "google_map_radius" => "google map radius",
      "zoom_account_id" => "zoom account id",
      "zoom_client_id" => "zoom client id",
      "zoom_client_secret" => "zoom client secret",
      "disqus_short_name" => "disqus short name",
      "tawkto_status" => "tawkto status",
      "tawkto_direct_chat_link" => "tawkto direct chat link",
      "calender_id" => "calender id",
      "whatsapp_number" => "whatsapp number",
      "whatsapp_header_title" => "whatsapp header title",
      "whatsapp_popup_message" => "whatsapp popup message",
      "google_recaptcha_site_key" => "googlerecaptasitekey",
      "google_recaptcha_status" => "googlerecaptastatus",
      "google_recaptcha_secret_key" => "googlerecaptasecretkey",
      "google_client_id" => "googleclientid",
      "google_client_secret" => "googleclientsecret",
      "google_login_status" => "googleloginstatus",
      "current_password" => "current password"
    ];

    return $newKeys;
  }

  //front attribute
  public function frontAttribute()
  {
    $newKeys = [
      'name' => 'name',
      'first_name' => 'first name',
      'gateway' => 'gateway',
      'phone' => 'phone',
      'address' => 'address',
      'email' => 'email address',
      'subject' => 'subject',
      'message' => 'message',
      'username' => 'username',
      'password' => 'password',
      'password_confirmation' => 'confirm password',
      'new_password' => 'new password',
      'new_password_confirmation' => 'new confirm password'
    ];

    return $newKeys;
  }

  public function updateValidationAttribute($newKeys, $content, $validationFilePath)
  {
    try {
      // Load the existing validation array
      $validation = include($validationFilePath);

      // Ensure 'attributes' key exists
      if (!isset($validation['attributes']) || !is_array($validation['attributes'])) {
        $validation['attributes'] = [];
      }
    } catch (\Exception $e) {
      session()->flash('warning', __('Please provide a valid language code!'));
      return;
    }


    //update existing keys
    foreach ($newKeys as $key => $value) {
      if (!array_key_exists($key, $validation['attributes'])) {
        $validation['attributes'][$key] = $value;
      }
    }

    // update values which matching keys with new values
    $decodedContent = json_decode($content, true);
    if (is_array($decodedContent)) {
      foreach ($decodedContent as $key => $value) {
        if (array_key_exists($key, $validation['attributes'])) {
          $validation['attributes'][$key] = $value;
        }
      }
    }

    //save the changes in validation attributes array
    $validationContent = "<?php\n\nreturn " . var_export($validation, true) . ";\n";
    file_put_contents($validationFilePath, $validationContent);
  }

  //delete a directory recursively
  private function deleteDirectory($dir)
  {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      $filePath = "$dir/$file";
      if (is_dir($filePath)) {
        $this->deleteDirectory($filePath);
      } else {
        @unlink($filePath);
      }
    }
    rmdir($dir);
  }
}
