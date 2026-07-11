"use strict";

/*================ Service Type =======================*/
$(document).ready(function () {
  // handle the initial state
  toggleServiceOptions();
});

// toggle advanced section when the radio button is changed
$('input[name="person_type"]').on('change', function () {
  toggleServiceOptions();
});

function toggleServiceOptions() {
  if ($('input[name="person_type"]:checked').val() == '1') {
    $('.groupPersons').addClass('d-none');
  } else {
    $('.groupPersons').removeClass('d-none');
  }
}


/*==== Allow Login toggle button on Staff Page  =====*/
$(document).ready(function () {
  // toggle allow login form when the radio button is changed
  $('input[name="login_allow_toggle"]').on('change', function () {
    toggleAllowLogin();
  });
});

// toggle allow login form section for visibility
function toggleAllowLogin() {
  if ($('input[name="login_allow_toggle"]:checked').val() === '1') {
    $('.allowLoginShowOff').removeClass('d-none');
  } else {
    $('.allowLoginShowOff').addClass('d-none');
  }
  $('input[name="login_allow_toggle"]').trigger('change');
}

function bootnotify(message, title, type) {
  var content = {};

  content.message = message;
  content.title = title;
  content.icon = 'fa fa-bell';

  $.notify(content, {
    type: type,
    placement: {
      from: 'top',
      align: 'right'
    },
    showProgressbar: true,
    time: 1000,
    allow_dismiss: true,
    delay: 4000
  });
}

// service form
$('#ServiceSubmit').on('click', function (e) {
  let can_service_add = $(this).attr('data-can_service_add');
  if (can_service_add == 0) {
    bootnotify(No_packages_available_for_this_vendor, 'Alert', 'warning');
    return false;
  } else if (can_service_add == 2) {
    bootnotify(This_vendor_had_reached_the_limit, 'Alert', 'warning');
    return false;
  } else if (can_service_add == 'downgrad') {
    bootnotify(something_went_wrong_longText, 'Alert', 'warning');
    return false;
  }

  $(e.target).attr('disabled', true);
  $(".request-loader").addClass("show");

  let serviceForm = document.getElementById('serviceForm');
  let fd = new FormData(serviceForm);
  let url = $("#serviceForm").attr('action');
  let method = $("#serviceForm").attr('method');

  //if summernote has then get summernote content
  $('.form-control').each(function (i) {
    let index = i;

    let $toInput = $('.form-control').eq(index);

    if ($(this).hasClass('summernote')) {
      let tmcId = $toInput.attr('id');
      let content = tinyMCE.get(tmcId).getContent();
      fd.delete($(this).attr('name'));
      fd.append($(this).attr('name'), content);
    }
  });


  $.ajax({
    url: url,
    method: method,
    data: fd,
    contentType: false,
    processData: false,
    success: function (data) {

      $(e.target).attr('disabled', false);
      $('.request-loader').removeClass('show');

      $('.em').each(function () {
        $(this).html('');
      });

      if (data == 'success') {
        location.reload();
        $('#serviceForm')[0].reset();
      }
      if (data == 'empty_package') {
        "use strict";
        var content = {};
        content.message = buy_package_use_panel;
        content.title = warningText;
        content.icon = 'fa fa-bell';
        $.notify(content, {
          type: 'warning',
          placement: {
            from: 'top',
            align: 'right'
          },
          showProgressbar: true,
          time: 1000,
          delay: 4000,
        });
      }

      if (data == "staff_downgrad_js") {
        $('.modal').modal('hide');
        "use strict";
        var content = {};
        content.message = something_went_wrong_longText;
        content.title = warningText;
        content.icon = 'fa fa-bell';
        $.notify(content, {
          type: 'warning',
          placement: {
            from: 'top',
            align: 'right'
          },
          showProgressbar: true,
          time: 1000,
          delay: 4000,
        });
      }

      if (data == "downgrade") {
        $('.modal').modal('hide');
        "use strict";
        var content = {};
        content.message = limit_over_msg;
        content.title = warningText;
        content.icon = 'fa fa-bell';
        $.notify(content, {
          type: 'warning',
          placement: {
            from: 'top',
            align: 'right'
          },
          showProgressbar: true,
          time: 1000,
          delay: 4000,
        });
        $("#limitModal").modal('show');
      }

    },
    error: function (error) {
      let errors = ``;

      for (let x in error.responseJSON.errors) {
        errors += `<li>
                <p class="text-danger mb-0">${error.responseJSON.errors[x][0]}</p>
              </li>`;
      }

      $('#service_erros ul').html(errors);
      $('#service_erros').show();

      $('.request-loader').removeClass('show');

      $('html, body').animate({
        scrollTop: $('#service_erros').offset().top - 100
      }, 1000);
    }
  });
  $(e.target).attr('disabled', false);
});


//staff form
$('#staffSubmit').on('click', function (e) {
  let can_staff_add = $(this).attr('data-can_staff_add');
  if (can_staff_add == 0) {
    bootnotify(buy_plane_to_add_staff, 'Alert', 'warning');
    return false;
  } else if (can_staff_add == 2) {
    bootnotify(you_can_not_add_more_staff_for_this_vendor, 'Alert', 'warning');
    return false;
  }
  $(e.target).attr('disabled', true);
  $(".request-loader").addClass("show");


  let staffForm = document.getElementById('staffForm');
  let fd = new FormData(staffForm);
  let url = $("#staffForm").attr('action');
  let method = $("#staffForm").attr('method');


  $.ajax({
    url: url,
    method: method,
    data: fd,
    contentType: false,
    processData: false,
    success: function (data) {

      $(e.target).attr('disabled', false);
      $('.request-loader').removeClass('show');

      $('.em').each(function () {
        $(this).html('');
      });

      if (data == 'success') {
        location.reload();
        $('#staffForm')[0].reset();
      }
      if (data == 'empty_package') {
        "use strict";
        var content = {};
        content.message = buy_package_use_panel;
        content.title = warningText;
        content.icon = 'fa fa-bell';
        $.notify(content, {
          type: 'warning',
          placement: {
            from: 'top',
            align: 'right'
          },
          showProgressbar: true,
          time: 1000,
          delay: 4000,
        });
      }
      if (data == "downgrade") {
        $('.modal').modal('hide');
        "use strict";
        var content = {};
        content.message = limit_over_msg;
        content.title = warningText;
        content.icon = 'fa fa-bell';
        $.notify(content, {
          type: 'warning',
          placement: {
            from: 'top',
            align: 'right'
          },
          showProgressbar: true,
          time: 1000,
          delay: 4000,
        });
        $("#limitModal").modal('show');
      }
    },
    error: function (error) {
      let errors = ``;

      for (let x in error.responseJSON.errors) {
        errors += `<li>
                <p class="text-danger mb-0">${error.responseJSON.errors[x][0]}</p>
              </li>`;
      }

      $('#service_erros ul').html(errors);
      $('#service_erros').show();

      $('.request-loader').removeClass('show');

      $('html, body').animate({
        scrollTop: $('#service_erros').offset().top - 100
      }, 1000);
    }
  });
  $(e.target).attr('disabled', false);
});

// get subcategory of selected category for service
$('.service-category').on('change', function () {
  $('.request-loader').addClass('show');
  let categoryId = $(this).val();
  let langCode = $(this).data('lang_code');
  let url;
  if (authUser === 'admin') {
    url = baseUrl + "admin/service-management/get-subcategory/" + categoryId;
  } else if (authUser === 'vendor') {
    url = baseUrl + "/vendor/service-management/get-subcategory/" + categoryId;
  } else if (authUser === 'staff') {
    url = baseUrl + "/staff/service-management/get-subcategory/" + categoryId;
  } else {
    url = null;
  }



  $.get(url, function (response) {
    $('.request-loader').removeClass('show');

    if ('successData' in response) {

      $(`select[name="${langCode}_subcategory_id"]`).removeAttr('disabled');

      let subcategories = response.successData;

      let markup = `<option selected disabled>${select_subcategory}</option>`;

      if (subcategories.length > 0) {
        subcategories.forEach(function (subcategory) {
          markup += `<option value="${subcategory.id}">${subcategory.name}</option>`;
        });
      } else {
        markup += `<option disabled>${No_Subcategory_Exist}</option>`;
      }

      $(`select[name="${langCode}_subcategory_id"]`).html(markup);
    } else {
      alert(response.errorData);
    }
  });
});
