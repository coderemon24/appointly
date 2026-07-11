<?php

namespace App\Providers;

use App\Models\Language;
use App\Models\Staff\Staff;
use App\Models\HomePage\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use App\Models\BasicSettings\SocialMedia;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   *
   * @return void
   */
  public function register()
  {
    //
  }

  /**
   * Bootstrap any application services.
   *
   * @return void
   */
  public function boot()
  {
    Paginator::useBootstrap();
    // URL::forceScheme('https');

    if (!app()->runningInConsole()) {
      # code...
      $data = DB::table('basic_settings')->select(
        'favicon',
        'website_title',
        'logo',
        'base_currency_text',
        'base_currency_text_position',
        'maintenance_img',
        'maintenance_msg',
        'google_map_status',
        'google_map_api_key',
        'service_view'
      )->first();

      //send this information to only staff view files
      View::composer('staffs.*', function ($view) {
        $langs = Language::all();
        // get basic info
        $basicData = DB::table('basic_settings')
          ->select('theme_version', 'time_format')
          ->first();

        if (Auth::guard('staff')->check() == true) {
          $staffid = Auth::guard('staff')->user()->id;
          if ($staffid) {
            $permission = Staff::findOrFail($staffid);
          }
        } else {
          $permission = null;
        }

        if (session()->has('staff_lang')) {
          $langCode = str_replace('admin_', '', session()->get('staff_lang'));
          $currentLang = Language::query()->where('code', $langCode)->first();
        } else {
          $currentLang = Language::query()->where('is_default', 1)->first();
        }

        $footerText = $currentLang->footerContent()->first();
        $view->with([
          'basicInfo' => $basicData,
          'defaultLang' => $currentLang,
          'permission' => $permission,
          'footerTextInfo' => $footerText,
          'langs' => $langs,
        ]);
      });

      // send this information to only back-end view files
      View::composer('admin.*', function ($view) {
        $langs = Language::all();
        $currentLang = null;
        if (Auth::guard('admin')->check() == true) {
          $authAdmin = Auth::guard('admin')->user();
          $role = null;

          if (!is_null($authAdmin->role_id)) {
            $role = $authAdmin->role()->first();
          }

          $langCode = Auth::guard('admin')->user()->lang_code;
          $code = str_replace('admin_', '', $langCode);
          $currentLang = Language::query()->where('code', $code)->first();

          if (is_null($currentLang)) {
            $currentLang = Language::query()->where('is_default', 1)->first();
            $authAdmin->lang_code = 'admin_' . $currentLang->code;
            $authAdmin->save();
          }

          if ($currentLang->direction == 1) {
            $rtl = 1;
          } else {
            $rtl = 0;
          }
        }

        $websiteSettings = DB::table('basic_settings')->select('admin_theme_version', 'base_currency_symbol', 'base_currency_symbol_position', 'base_currency_symbol_position', 'base_currency_text_position', 'base_currency_text', 'base_currency_rate', 'theme_version', 'shop_status', 'time_format')->first();



        if (Auth::guard('admin')->check() == true) {
          $view->with('roleInfo', $role);
        }
        if (!is_null($currentLang)) {
          $footerText = $currentLang->footerContent()->first();
          $view->with('footerTextInfo', $footerText);
          $view->with('rtl', $rtl);
        }

        $view->with('currentLang', $currentLang);
        $view->with('langs', $langs);
        $view->with('settings', $websiteSettings);
      });

      // send this information to only back-end view files
      View::composer('vendors.*', function ($view) {

        $langs = Language::all();
        $setting = DB::table('basic_settings')->where('uniqid', 12345)->select('admin_approval_notice')->first();

        $language = null;
        $langCode = str_replace('admin_', '', session()->get('vendor_lang'));
        if ($langCode) {
          $code = str_replace('admin_', '', $langCode);
          $language = Language::query()->where('code', $code)->first();
        }

        if (is_null($language)) {
          $language = Language::query()->where('is_default', 1)->first();
        }


        $footerText = $language->footerContent()->first();
        $websiteSettings = DB::table('basic_settings')->select(
          'admin_theme_version',
          'base_currency_symbol',
          'base_currency_symbol_position',
          'base_currency_text',
          'base_currency_text_position',
          'base_currency_rate',
          'theme_version',
          'whatsapp_manager_status',
          'time_format'
        )->first();

        $view->with('defaultLang', $language);
        $view->with('langs', $langs);
        $view->with('settings', $websiteSettings);
        $view->with('setting', $setting);
        $view->with('footerTextInfo', $footerText);
      });

      // send this information to only front-end view files
      View::composer('frontend.*', function ($view) {
        // get basic info
        $basicData = DB::table('basic_settings')
          ->select(
            'theme_version',
            'footer_logo',
            'footer_background_image',
            'email_address',
            'whatsapp_manager_status',
            'contact_number',
            'address',
            'primary_color',
            'secondary_color',
            'whatsapp_status',
            'whatsapp_number',
            'whatsapp_header_title',
            'whatsapp_popup_status',
            'whatsapp_popup_message',
            'tawkto_status',
            'tawkto_direct_chat_link',
            'base_currency_symbol',
            'base_currency_symbol_position',
            'base_currency_text',
            'base_currency_text_position',
            'hero_section_video_url',
            'preloader_status',
            'preloader',
            'shop_status'
          )
          ->first();

        // get all the languages of this system
        $allLanguages = Language::all();

        // get the current locale of this website
        if (Session::has('currentLocaleCode')) {
          $locale = Session::get('currentLocaleCode');
        }

        if (empty($locale)) {
          $language = Language::query()->where('is_default', '=', 1)->first();
        } else {
          $language = Language::query()->where('code', '=', $locale)->first();
          if (empty($language)) {
            $language = Language::query()->where('is_default', '=', 1)->first();
          }
        }

        // get all the social medias
        $socialMedias = SocialMedia::query()->orderBy('serial_number', 'asc')->get();

        // get the menus of this website
        $siteMenuInfo = $language->menuInfo;
        if (is_null($siteMenuInfo)) {
          $menus = json_encode([]);
        } else {
          $menus = $siteMenuInfo->menus;
        }

        // get the announcement popups
        $popups = $language->announcementPopup()->where('status', 1)->orderBy('serial_number', 'asc')->get();

        // get the cookie alert info
        $cookieAlert = $language->cookieAlertInfo()->first();

        $footerSectionStatus = Section::query()->pluck('footer_section_status')->first();

        if ($footerSectionStatus == 1) {
          // get the footer info
          $footerData = $language->footerContent()->first();

          // get the quick links of footer
          $quickLinks = $language->footerQuickLink()->orderBy('serial_number', 'asc')->get();
        }
        $secInfo = Section::query()->first();

        // get shopping cart information from session
        if (Session::has('productCart')) {
          $cartItems = Session::get('productCart');
        } else {
          $cartItems = [];
        }
        $view->with([
          'basicInfo' => $basicData,
          'allLanguageInfos' => $allLanguages,
          'currentLanguageInfo' => $language,
          'socialMediaInfos' => $socialMedias,
          'menuInfos' => $menus,
          'secInfo' => $secInfo,
          'popupInfos' => $popups,
          'cookieAlertInfo' => $cookieAlert,
          'footerInfo' => ($footerSectionStatus == 1) ? $footerData : NULL,
          'quickLinkInfos' => ($footerSectionStatus == 1) ? $quickLinks : [],
          'cartItemInfo' => $cartItems,
          'footerSectionStatus' => $footerSectionStatus
        ]);
      });


      // send this information to both front-end & back-end view files
      View::share(['websiteInfo' => $data]);
    }
  }
}
