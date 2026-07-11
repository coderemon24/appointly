<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\Vendor\StaffController;
use App\Http\Controllers\Api\Vendor\PluginController;
use App\Http\Controllers\Api\Vendor\BuyPlanController;
use App\Http\Controllers\Api\Vendor\WithdrawController;
use App\Http\Controllers\Api\Vendor\LimitCheckController;
use App\Http\Controllers\Api\Vendor\RecivedEmailController;
use App\Http\Controllers\Api\Vendor\StaffScheduleController;
use App\Http\Controllers\Api\Vendor\SupportTicketController;
use App\Http\Controllers\Api\Vendor\StaffGlobalDayController;
use App\Http\Controllers\Api\Vendor\VendorCheckoutController;
use App\Http\Controllers\Api\Vendor\FeaturedServiceController;
use App\Http\Controllers\Api\Vendor\Staff\ServiceAssignController;
use App\Http\Controllers\Api\Vendor\VendorController as VendorManagerController;
use App\Http\Controllers\Api\Vendor\ServiceController as VendorServiceController;
use App\Http\Controllers\Api\Vendor\AppointmentController as VendorAppointmentController;

Route::get('/', [HomeController::class, 'index'])->name('api.index');
Route::get('/get-basic', [HomeController::class, 'getBasic'])->name('api.getBasic');
Route::get('/all-plugins', [HomeController::class, 'getAllPlugins'])->name('api.all-plugins');

//services routes
Route::prefix('services')->group(function () {
  Route::get('/', [ServiceController::class, 'index'])->name('api.frontend.services');
  Route::get('/search', [ServiceController::class, 'searchService'])->name('api.frontend.services.category.search');
  Route::get('/details/{slug}/{id}', [ServiceController::class, 'details'])->name('api.frontend.service.details');
  Route::post('/send-inquiry-message', [ServiceController::class, 'inQuiryMessage'])->name('api.frontend.service.inQuiryMessage');
  Route::get('/get-staff-content/{id}', [ServiceController::class, 'staffcontent'])->name('api.frontend.service.content');
  Route::get('/search-staff-content/{id}', [ServiceController::class, 'staffSearch'])->name('api.frontend.staff-search');
  Route::get('/check-date-time/{id}', [ServiceController::class, 'staffHoliday'])->name('api.frontend.staff.holiday');
  Route::get('/show-staff-hour', [ServiceController::class, 'staffHour'])->name('api.frontend.staff.hour');
  Route::get('/billing-form/submit', [ServiceController::class, 'billing'])->name('api.frontend.services.billing');

  Route::post('/store-review/{id}', [ServiceController::class, 'storeReview'])->name('api.frontend.service.rating.store')->middleware('auth:sanctum');
  Route::get('addto/wishlist/{id}', [UserController::class, 'add_to_wishlist'])->name('api.user.wishlist')->middleware('auth:sanctum');
  Route::get('remove/wishlist/{id}', [UserController::class, 'remove_wishlist'])->name('api.remove.wishlist')->middleware('auth:sanctum');
});

//vendors management routes
Route::get('/vendors', [VendorController::class, 'index'])->name('api.frontend.vendors');
Route::post('/vendor/contact', [VendorController::class, 'contact'])->name('api.frontend.vendor.contact');
Route::get('/user/facebook', [UserController::class, 'facebookLogin'])->name('api.user.login.facebook');

Route::get('/get-lang/{code}', [LanguageController::class, 'getLang']);

//booking routes
Route::post('verfiy-payment', [BookingController::class, 'verfiyPayment'])->name('api.frontend.service.payment.verfiy');
Route::post('payment-process', [BookingController::class, 'paymentProcess'])->name('api.frontend.service.payment');

//guest customer routes
Route::prefix('customer')->group(function () {
  Route::get('/signup', [UserController::class, 'signup'])->name('api.user.signup');
  Route::post('/signup/submit', [UserController::class, 'signupSubmit'])->name('api.user.signup_submit');

  Route::get('/login', [UserController::class, 'login'])->name('api.user.login');
  Route::post('/login/submit', [UserController::class, 'loginSubmit'])->name('api.user.login_submit');

  Route::post('/forget-password', [UserController::class, 'forgetPassword'])->name('api.user.forget_password');
  Route::post('/reset-password', [UserController::class, 'resetPasswordSubmit'])->name('api.user.reset_password');
});

//store fcm token
Route::post('/save-fcm-token', [FcmTokenController::class, 'store']);
//authenticated customer routes
Route::prefix('/customer')->middleware('auth:sanctum')->group(function () {
  Route::get('/dashboard', [UserController::class, 'redirectToDashboard'])->name('api.user.dashboard');
  Route::get('/wishlist', [UserController::class, 'wishlist'])->name('api.user.dashboard.wishlist');

  Route::get('/edit-profile', [UserController::class, 'editProfile'])->name('api.user.edit_profile');
  Route::post('/update-profile', [UserController::class, 'updateProfile'])->name('api.user.update_profile');
  Route::get('/change-password', [UserController::class, 'changePassword'])->name('api.user.change_password');
  Route::post('/update-password', [UserController::class, 'updatePassword'])->name('api.user.update_password');

  Route::get('/appointment', [AppointmentController::class, 'appointment'])->name('api.user.appointment.index');
  Route::get('/appointment/details/{id}', [AppointmentController::class, 'details'])->name('api.user.appointment.details');

  Route::get('/order', [OrderController::class, 'order'])->name('api.user.order.index')->middleware('shop.status');
  Route::get('/order/details/{id}', [OrderController::class, 'details'])->name('api.user.order.details')->middleware('shop.status');
  Route::post('/order/product-download/{product_id}', [OrderController::class, 'download'])->name('api.user.product_order.product.download')->middleware('shop.status');

  Route::post('/logout', [UserController::class, 'logoutSubmit'])->name('api.user.logout');
});


/**
 * =================================Vendor Routes =============================
 */
Route::get('/vendor/signup', [VendorManagerController::class, 'signup'])->name('api.vendor.signup');
Route::post('/vendor/signup/submit', [VendorManagerController::class, 'create'])->name('api.vendor.create');

Route::get('/vendor/login', [VendorManagerController::class, 'login'])->name('api.vendor.login');
Route::post('/vendor/login/submit', [VendorManagerController::class, 'authentication'])->name('api.vendor.login_submit');

Route::prefix('/vendor')->middleware('auth:sanctum_vendor')->group(function () {
  Route::get('/get-lang/{code}', [LanguageController::class, 'getVendorLang']);
  Route::get('/get-basic', [VendorManagerController::class, 'getBasic'])->name('api.vendor.getBasic');

  Route::get('/dashboard', [VendorManagerController::class, 'dashboard'])->name('api.api.vendor.dashboard');
  Route::post('/updated-password', [VendorManagerController::class, 'updated_password'])->name('api.vendor.update_password');
  Route::get('/edit-profile', [VendorManagerController::class, 'edit_profile'])->name('api.vendor.edit_profile');
  Route::post('/updated-profile', [VendorManagerController::class, 'update_profile'])->name('api.vendor.update_profile');
  Route::post('/logout', [VendorManagerController::class, 'logout'])->name('api.vendor.logout');
  Route::get('/subscription-log', [VendorManagerController::class, 'subscription_log'])->name('api.vendor.subscription_log');
  Route::get('/transcation', [VendorManagerController::class, 'transcation'])->name('api.vendor.transcation');

  Route::get('/service-inquiry', [VendorServiceController::class, 'message'])->name('api.vendor.booking.inquiry');
  Route::post('/service-inquiry/delete/{id}', [VendorServiceController::class, 'messageDestroy'])->name('api.vendor.booking.inquiry_submit');

  //buy subscription
  Route::get('/package-list', [BuyPlanController::class, 'index'])->name('api.vendor.plan.extend.index');
  Route::get('/package/checkout/{package_id}', [BuyPlanController::class, 'checkout'])->name('api.vendor.plan.extend.checkout');
  Route::post('/package/verfiy-payment', [VendorCheckoutController::class, 'verfiyPayment'])->name('api.vendor.plan.checkout');
  Route::post('/package/checkout/payment-process', [VendorCheckoutController::class, 'paymentProcess']);

  //withdraw management
  Route::prefix('withdraw')->group(function () {
    Route::get('/', [WithdrawController::class, 'index'])->name('api.vendor.withdraw');
    Route::get('/create', [WithdrawController::class, 'create'])->name('api.vendor.withdraw.create');
    Route::get('/get-method/input/{id}', [WithdrawController::class, 'get_inputs']);
    Route::get('/calculation/{method}/{amount}', [WithdrawController::class, 'balance_calculation']);
    Route::post('/send-request', [WithdrawController::class, 'send_request'])
      ->name('api.vendor.withdraw.send-request');
    Route::post('/bulk-delete', [WithdrawController::class, 'bulkDelete'])
      ->name('api.vendor.withdraw.bulk_delete_withdraw');
    Route::post('/delete', [WithdrawController::class, 'delete'])
      ->name('api.vendor.withdraw.delete_withdraw');
  });


  Route::prefix('recevied')->group(function () {
    Route::get('email', [RecivedEmailController::class, 'mailToAdmin'])->name('api.vendor.email.index');
    Route::post('update/email', [RecivedEmailController::class, 'updateMailToAdmin'])->name('api.vendor.email.update');
  });

  //service management
  Route::get('/services', [VendorServiceController::class, 'index'])->name('api.vendor.service_managment');
  Route::prefix('/service')->group(function () {
    Route::get('/create', [VendorServiceController::class, 'create'])->name('api.vendor.service_managment.create');
    Route::post('/store', [VendorServiceController::class, 'store'])->name('api.vendor.service_managment.store');
    Route::get('/edit/{id}', [VendorServiceController::class, 'edit'])->name('api.vendor.service_managment.edit');
    Route::post('/update/{id}', [VendorServiceController::class, 'update'])->name('api.vendor.service_managment.update');
    Route::post('/update-status', [VendorServiceController::class, 'servicestatus'])->name('api.vendor.service_managment.servicestatus');
    Route::post('/destroy/{id}', [VendorServiceController::class, 'destroy'])->name('api.vendor.service_managment.destroy');
    Route::post('/bulk-destroy', [VendorServiceController::class, 'bulkDestroy'])->name('api.vendor.service_managment.bulkDestroy');
    Route::get('get-subcategory/{category_id}', [VendorServiceController::class, 'getSucategory'])->name('api.vendor.service_managment.get_subcategory');
  });

  //staff management
  Route::prefix('staff')->group(function () {
    Route::get('/', [StaffController::class, 'index'])->name('api.vendor.staff.index');
    Route::get('/create', [StaffController::class, 'create'])->name('api.vendor.staff.create');
    Route::post('/store', [StaffController::class, 'store'])->name('api.vendor.staff.store');
    Route::get('/edit/{id}', [StaffController::class, 'edit'])->name('api.vendor.staff.edit');
    Route::post('/update/{id}', [StaffController::class, 'update'])->name('api.vendor.staff.update');
    Route::post('/destroy/{id}', [StaffController::class, 'destroy'])->name('api.vendor.staff.destroy');
    Route::post('/bulk-destroy', [StaffController::class, 'bulkDestroy'])->name('api.vendor.staff.bulkDestroy');
    Route::post('/staff-status', [StaffController::class, 'staffstatus'])->name('api.vendor.staff.staffstatus');
    Route::get('/permission/{id}', [StaffController::class, 'permission'])->name('api.vendor.staff.permission');
    Route::post('/permission-update/{id}', [StaffController::class, 'permissionUpdate'])->name('api.vendor.staff.permissionUpdate');
    Route::get('/change-password/{id}', [StaffController::class, 'changePassword'])->name('api.vendor.staff.change_password');
    Route::post('/update-password/{id}', [StaffController::class, 'updatePassword'])->name('api.vendor.staff.update_password');

    Route::prefix('service-assign')->group(function () {
      Route::get('/{id}', [ServiceAssignController::class, 'index'])->name('api.vendor.staff.service_assign');
      Route::post('/store', [ServiceAssignController::class, 'store'])->name('api.vendor.staff.service_assign.store');
      Route::post('/destroy', [ServiceAssignController::class, 'destroy'])->name('api.vendor.staff.service_assign.destroy');
    });

    //staff Schedule management
    Route::get('/days/{id}', [StaffScheduleController::class, 'day'])->name('api.vendor.staff.schedule.index');
    Route::post('/schedule-type/{id}', [StaffScheduleController::class, 'scheduleType'])->name('api.vendor.staff.schedule.type_chnage');
    Route::get('/time-slots', [StaffScheduleController::class, 'TimeSlots'])->name('api.vendor.staff.schedule.TimeSlots');
    Route::post('/store/time-slot', [StaffScheduleController::class, 'storeTimeSlot'])->name('api.vendor.staff.schedule.storeTimeSlot');
    Route::post('/update-time-slot', [StaffScheduleController::class, 'updateSlot'])->name('api.vendor.staff.schedule.updateSlot');
    Route::post('/change-weekend/{id}', [StaffScheduleController::class, 'weekendChange'])->name('api.vendor.staff.schedule.weekendChange');
    Route::post('/time-slot/destroy/{id}', [StaffScheduleController::class, 'destroy'])->name('api.vendor.staff.schedule.timeSlot.destroy');
    Route::post('/time-slot/bulk-destroy', [StaffScheduleController::class, 'bulkDestroy'])->name('api.vendor.staff.schedule.timeSlot.bulkDestroy');

    //staff holidays management
    Route::get('/holidays/{id}', [StaffScheduleController::class, 'holidays'])->name('api.vendor.staff.holiday.index');
    Route::post('/holiday/store', [StaffScheduleController::class, 'holidayStore'])->name('api.vendor.staff.holiday.store');
    Route::post('/holiday/destory/{id}', [StaffScheduleController::class, 'holidayDestory'])->name('api.vendor.staff.holiday.holidayDestory');
    Route::post('/holiday/bulk-destory', [StaffScheduleController::class, 'holidayBulkDestory'])
      ->name('api.vendor.staff.holiday.holidayBulkDestory');
    Route::post('holiday-schedule-type/{id}', [StaffScheduleController::class, 'holidayScheduleType'])
      ->name('api.vendor.staff.holiday.schedule_type_change');
  });

  //support ticket management
  Route::prefix('support')->group(function () {
    Route::get('tickets', [SupportTicketController::class, 'index'])->name('api.vendor.support_tickets');
    Route::post('ticket/store', [SupportTicketController::class, 'store'])->name('api.vendor.support_ticket.store');
    Route::get('ticket/message/{id}', [SupportTicketController::class, 'message'])->name('api.vendor.support_tickets.message');
    Route::post('zip-upload', [SupportTicketController::class, 'zip_file_upload'])->name('api.vendor.support_ticket.zip_file.upload');
    Route::post('ticket/reply/{id}', [SupportTicketController::class, 'ticketreply'])->name('api.vendor.support_ticket.reply');
    Route::post('ticket/delete/{id}', [SupportTicketController::class, 'delete'])->name('api.vendor.support_tickets.delete');
  });

  //appointment management
  Route::prefix('appointment')->group(function () {
    Route::get('/settings', [VendorAppointmentController::class, 'setting'])->name('api.vendor.appointments.setting');
    Route::post('/update/settings', [VendorAppointmentController::class, 'updatesetting'])->name('api.vendor.appointments.setting_update');

    Route::get('/all', [VendorAppointmentController::class, 'index'])->name('api.vendor.all_appointment');
    Route::get('/pending', [VendorAppointmentController::class, 'pendingAppointment'])->name('api.vendor.pending_appointment');
    Route::get('/accepted', [VendorAppointmentController::class, 'acceptedAppointment'])->name('api.vendor.accepted_appointment');
    Route::get('/rejected', [VendorAppointmentController::class, 'rejectedAppointment'])->name('api.vendor.rejected_appointment');

    Route::post('/update/appointment-status/{id}', [VendorAppointmentController::class, 'updateAppointmentStatus'])->name('api.vendor.appointment.update_status');
    Route::post('/staff/assign', [VendorAppointmentController::class, 'staffAssign'])->name('api.vendor.appointment.staff_assign');

    Route::get('/details/{id}', [VendorAppointmentController::class, 'show'])->name('api.vendor.appointment.details');
    Route::post('/delete/{id}', [VendorAppointmentController::class, 'destroy'])->name('api.vendor.appointment.delete');
    Route::post('/bulk-destroy', [VendorAppointmentController::class, 'bulkDestroy'])->name('api.vendor.appointment.bulk-destroy');
  });

  Route::prefix('plugins')->group(function () {
    Route::get('', [PluginController::class, 'index'])->name('api.vendor.plugins.index');
    Route::post('/zoom/update', [PluginController::class, 'zoomUpdate'])->name('api.vendor.plugins.zoom.store');
    Route::post('/calendar/update', [PluginController::class, 'updateCalendar'])->name('api.vendor.update_google_calendar');
  });

  //staff gloabal days
  Route::prefix('schedule')->group(function () {
    Route::get('/days', [StaffGlobalDayController::class, 'index'])->name('api.vendor.staff.global.day');
    Route::post('weekend-change/{id}', [StaffGlobalDayController::class, 'weekendChange'])
      ->name('api.vendor.weekend.change');

    // time slot route
    Route::prefix('time-slots')->group(function () {
      Route::get('/', [StaffGlobalDayController::class, 'serviceHour'])->name('api.vendor.global.time-slot.manage');
      Route::post('/store', [StaffGlobalDayController::class, 'store'])->name('api.vendor.global.time-slot.store');
      Route::post('/update', [StaffGlobalDayController::class, 'update'])->name('api.vendor.global.time-slot.update');
      Route::post('/destroy/{id}', [StaffGlobalDayController::class, 'destroy'])->name('api.vendor.global.time-slot.destroy');
      Route::post('/bulk-delete', [StaffGlobalDayController::class, 'bulkDestroy'])->name('api.vendor.global.time-slot.bulk_delete');
    });

    // holiday route
    Route::prefix('/holiday')->group(function () {
      Route::get('/', [StaffGlobalDayController::class, 'holidayIndex'])->name('api.vendor.global.holiday');
      Route::post('/store', [StaffGlobalDayController::class, 'holidayStore'])->name('api.vendor.global.holiday.store');
      Route::post('/delete/{id}', [StaffGlobalDayController::class, 'holidayDestroy'])->name('api.vendor.global.holiday.delete');
      Route::post('/bulke-destory', [StaffGlobalDayController::class, 'holidayBulkDestroy'])->name('api.vendor.global.holiday.bluk-destroy');
    });
  });

  //featured service
  Route::get('service-promotion', [FeaturedServiceController::class, 'index'])->name('api.vendor.featured.service');
  Route::get('/featured-payment-verify', [FeaturedServiceController::class, 'verfiyPayment'])->name('api.vendor.featured.verify');
  Route::post('/featured-payment-process', [FeaturedServiceController::class, 'paymentProcess'])->name('api.vendor.featured.payment');

  Route::get('/limit-check', [LimitCheckController::class, 'limitCheck'])->name('api.vendor.limit.check');
});

//vendor details page route
Route::get('vendor/{username}', [VendorController::class, 'details'])->name('api.frontend.vendor.details');
Route::get('crrency', [HomeController::class, 'currency'])->name('api.frontend.currency');

/**
 * =================================Staff Routes =============================
 */



Route::prefix('/staff')->middleware('auth:sanctum_staff')->group(function () {
  Route::post('/login_submit', 'Api\StaffAdmin\StaffController@loginSubmit')->withoutMiddleware('auth:sanctum_staff');
  Route::get('/change-language/{lang}', 'Api\StaffAdmin\StaffController@languageChange')->name('api.api.staff.language.change');
  Route::post('/change-theme', 'Api\StaffAdmin\StaffController@changeTheme')->name('api.api.staff.change_theme');
  Route::get('/edit-profile', 'Api\StaffAdmin\StaffController@edit_profile')->name('api.api.staff.admin_edit.profile');
  Route::post('/update-profile', 'Api\StaffAdmin\StaffController@update_profile')->name('api.api.staff.update_profile');
  Route::get('/change-password', 'Api\StaffAdmin\StaffController@change_password')->name('api.api.staff.change_password');
  Route::post('/update-password', 'Api\StaffAdmin\StaffController@updated_password')->name('api.api.staff.update_password');
  Route::get('dashboard', 'Api\StaffAdmin\StaffController@index')->name('api.api.staff.dashboard');
  Route::get('logout', 'Api\StaffAdmin\StaffController@logout')->name('api.api.staff.logout');
  Route::get('/service-inquiry', 'Api\StaffAdmin\ServiceInqueryController@message')->name('api.api.staff.booking.inquiry');
  Route::post('/service-inquiry/delete/{id}', 'Api\StaffAdmin\ServiceInqueryController@messageDestroy')->name('api.api.staff.booking.inquiry.delete');
  Route::post('/service-inquiry/bulk-delete', 'Api\StaffAdmin\ServiceInqueryController@bulkDelete')->name('api.api.staff.booking.inquiry.bulk_delete');
  Route::get('/plugins', 'Api\StaffAdmin\PluginController@index')->name('api.staff.plugins.index');
  Route::post('/calendar/update', 'Api\StaffAdmin\PluginController@updateCalendar')->name('api.staff.update_google_calendar');

  Route::prefix('/services')->middleware('auth:sanctum_staff')->group(function () {
    Route::get('/', 'Api\StaffAdmin\ServiceController@index')->name('api.api.staff.services.index');
    Route::get('/create', 'Api\StaffAdmin\ServiceController@create')->name('api.api.staff.services.create');
    Route::post('/store', 'Api\StaffAdmin\ServiceController@store')->name('api.api.staff.services.store');
    Route::get('/edit/{id}', 'Api\StaffAdmin\ServiceController@edit')->name('api.api.staff.services.edit');
    Route::post('/update/{id}', 'Api\StaffAdmin\ServiceController@update')->name('api.api.staff.services.update');
    Route::post('/update-status', 'Api\StaffAdmin\ServiceController@servicestatus')->name('api.api.staff.services.status');
    Route::post('/destroy/{id}', 'Api\StaffAdmin\ServiceController@destroy')->name('api.api.staff.services.destroy');
    Route::post('/bulk-destroy', 'Api\StaffAdmin\ServiceController@bulkDestroy')->name('api.api.staff.services.bulkDestroy');
    Route::get('/get-categories', 'Api\StaffAdmin\ServiceController@getCategory')->name('api.api.staff.services.get_category');
    Route::get('/get-subcategory/{category_id}', 'Api\StaffAdmin\ServiceController@getSubcategory')->name('api.api.staff.services.get_subcategory');
    Route::post('/gallery-image-remove', 'Api\StaffAdmin\ServiceController@imagedbrmv')->name('api.api.staff.gallery_image_remove');
  });

  Route::prefix('/appointments')->middleware('auth:sanctum_staff')->group(function () {
    Route::get('/', 'Api\StaffAdmin\AppointmentController@index')->name('api.api.staff.appointment.index');
    Route::get('/pending', 'Api\StaffAdmin\AppointmentController@pendingAppointment')->name('api.api.staff.appointment.pending');
    Route::get('/accepted', 'Api\StaffAdmin\AppointmentController@acceptedAppointment')->name('api.api.staff.appointment.accepted');
    Route::get('/rejected', 'Api\StaffAdmin\AppointmentController@rejectedAppointment')->name('api.api.staff.appointment.rejected');
    Route::get('/details/{id}', 'Api\StaffAdmin\AppointmentController@show')->name('api.api.staff.appointment.details');
  });

  Route::prefix('/schedule')->middleware('auth:sanctum_staff')->group(function () {
    Route::get('/days', 'Api\StaffAdmin\StaffDayHourController@day')->name('api.api.staff.schedule.day');
    Route::post('/schedule-type/{id}', 'Api\StaffAdmin\StaffDayHourController@changeStaffSetting')->name('api.api.staff.schedule.type_chnage');
    Route::get('/time-slots', 'Api\StaffAdmin\StaffDayHourController@timeSlots')->name('api.api.staff.schedule.TimeSlots');
    Route::post('/time-slot-store', 'Api\StaffAdmin\StaffDayHourController@store')->name('api.api.staff.schedule.storeTimeSlot');
    Route::post('/update-time-slot', 'Api\StaffAdmin\StaffDayHourController@update')->name('api.api.staff.schedule.updateSlot');
    Route::post('/change-weekend/{id}', 'Api\StaffAdmin\StaffDayHourController@weekendChange')->name('api.api.staff.schedule.weekendChange');
    Route::post('/time-slot/destroy/{id}', 'Api\StaffAdmin\StaffDayHourController@destroy')->name('api.api.staff.schedule.timeSlot.destroy');
    Route::post('/time-slot/bulk-destroy', 'Api\StaffAdmin\StaffDayHourController@bulkDestroy')->name('api.api.staff.schedule.timeSlot.bulkDestroy');
  });

});
