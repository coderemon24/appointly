$(function ($) {
  "use strict";
  // service pages widget-categories-2
  let toggleList = document.querySelector(".toggle-list");
  let showCount = parseInt(toggleList.getAttribute("data-toggle-show"), 10);
  let listItems = document.querySelectorAll(".cat-item-2");
  let isExpanded = false;
  let showMoreBtn = document.getElementById("showMoreBtn");

  function toggleItems() {
    if (isExpanded) {
      listItems.forEach((item, index) => {
        item.style.display = index < showCount ? "block" : "none";
      });
      showMoreBtn.textContent = show_more;
    } else {
      listItems.forEach(item => {
        item.style.display = "block";
      });
      showMoreBtn.textContent = show_less;
    }
    isExpanded = !isExpanded;
  }

  listItems.forEach((item, index) => {
    if (index >= showCount) {
      item.style.display = "none";
    }
  });
  showMoreBtn.addEventListener("click", toggleItems);


  // Function to update URL and submit form
  function updateUrl(data) {
    let newUrl = new URL(window.location);

    if (data === "category") {
      newUrl.searchParams.set('category', $('#category').val());
      newUrl.searchParams.delete('rating');
      newUrl.searchParams.delete('service_type');
      newUrl.searchParams.delete('location');
      newUrl.searchParams.delete('service_title');
      newUrl.searchParams.delete('sort');
      newUrl.searchParams.delete('min');
      newUrl.searchParams.delete('max');
      newUrl.searchParams.delete('subcategory');
    } else if (data === "rating") {
      newUrl.searchParams.set('rating', $('#rating').val());
    } else if (data === "service_type") {
      newUrl.searchParams.set('service_type', $('#service_type').val());
    } else if (data === "location_val") {
      newUrl.searchParams.set('location', $('#location_val').val());
    } else if (data === "service_title") {
      newUrl.searchParams.set('service_title', $('#service_title').val());
    } else if (data === "sort") {
      newUrl.searchParams.set('sort', $('#sort_val').val());
    } else if (data === "price_search") {
      newUrl.searchParams.set('min', $('#min_val').val());
      newUrl.searchParams.set('max', $('#max_val').val());
    } else if (data === "subcategory") {
      newUrl.searchParams.set('subcategory', $('#subcategory').val());
    }

    window.history.replaceState({}, '', newUrl);
    $('#searchForm').submit();
  }

  // Handle click events for ratings, service type, category toggle and sort
  $('body').on('click', '.rating', function () {
    $('#rating').val($(this).val());
    updateUrl("rating");
  });

  $('body').on('click', '.service_type', function () {
    $('#service_type').val($(this).val());
    updateUrl("service_type");
  });

  if (googleApiStatus === 0) {
    $('body').on('keydown', '#location', function (event) {
      if (event.keyCode === 13) {
        $('#location_val').val($(this).val());
        updateUrl("location_val");
      }
    });
  }

  $('body').on('keydown', '#search_service_title', function (event) {
    if (event.keyCode === 13) {
      event.preventDefault();
      $('#service_title').val($(this).val());
      updateUrl("service_title");
    }
  });

  $('body').on('change', '.sort', function () {
    $('#sort_val').val($(this).val());
    updateUrl("sort");
  });

  $('body').on('click', '.category-toggle', function () {
    let slug = $(this).data('slug');
    // Reset other filters
    $('#location_val').val('');
    $('#sort_val').val('');
    $('#location').val('');
    $('#subcategory').val('');
    $('#rating, #service_type, #service_title, #min_val, #max_val, #page').val('');
    $('#sort-filter option[value="nearest"]').remove();
    $('#sort-filter option[value="faraway"]').remove();
    // Reload specific parts of the page
    $("#service_details").load(location.href + " #service_details > *");
    $("#rating_div").load(location.href + " #rating_div > *");
    $("#service_type_div").load(location.href + " #service_type_div > *");

    // Set selected category and update URL
    $('.category-toggle.active').removeClass('active');
    $(this).addClass('active');
    $('#category').val(slug);
    setTimeout(() => {
      updateUrl("category");
    }, 100);
  });

  $('body').on('click', '.subcategory-search', function () {
    let slug = $(this).data('slug');
    let parentCategory = $(this).closest('.cat-item-2').find('.category-toggle');
    $('.subcategory-search').removeClass('active');
    $(this).addClass('active');
    parentCategory.addClass('active');
    $('#subcategory').val(slug);
    updateUrl("subcategory");
  });
  // Price range slider initialization
  function initializePriceSliders() {
    var range_slider_max = document.getElementById('min');
    if (range_slider_max) {
      var sliders = document.querySelectorAll("[data-range-slider='priceSlider']");
      var filterSliders = document.querySelector("[data-range-slider='filterPriceSlider']");
      var min = parseFloat($('#min').val());
      var max = parseFloat($('#max').val());
      var o_min = parseFloat($('#o_min').val());
      var o_max = parseFloat($('#o_max').val());
      var currency_symbol = $('#currency_symbol').val();

      sliders.forEach(function (el) {
        noUiSlider.create(el, {
          start: [min, max],
          connect: true,
          step: 10,
          range: {
            'min': o_min,
            'max': o_max
          }
        });

        el.noUiSlider.on("update", function (values) {
          $("[data-range-value='priceSliderValue']").text(currency_symbol + values.join(" - " + currency_symbol));
        });
      });

      if (filterSliders) {
        noUiSlider.create(filterSliders, {
          start: [min, max],
          connect: true,
          step: 10,
          range: {
            'min': o_min,
            'max': o_max
          }
        });

        filterSliders.noUiSlider.on("update", function (values) {
          $("[data-range-value='filterPriceSliderValue']").text(currency_symbol + values.join(" - " + currency_symbol));
          $('#min_val').val(values[0]);
          $('#max_val').val(values[1]);
        });

        filterSliders.noUiSlider.on("change", function () {
          updateUrl("price_search");
        });
      }
    }
  }
  initializePriceSliders();
  // Form submission handling
  $('#searchForm').on('submit', function (e) {
    e.preventDefault();
    var fd = $(this).serialize();
    $(".request-loader-time").addClass("show");

    $.ajax({
      url: searchUrl,
      method: "get",
      data: fd,
      success: function (res) {
        $('#search_container').html(res);
        $('#total-service').text($('#countServie').val());
        if (serviceView == 0) {
          if (clusters) {
            map.removeLayer(clusters);
            clusters.clearLayers();
          }
          if (map) {
            map.off();
            map.remove();
          }

          setTimeout(function () {
            mapInitialize("main-map", featuredContents, regularContents);
          }, 100);
        }
      },
      complete: function () {
        $(".request-loader-time").removeClass("show");
      },
      error: function (xhr) {
        console.error(xhr.responseText);
        $(".request-loader-time").removeClass("show");
      }
    });
  });

  // Pagination handling
  $('body').on('click', '.pagination a', function (e) {
    e.preventDefault();
    $(".request-loader-time").addClass("show");
    let page = $(this).attr('href').split('page=')[1];
    let searchParams = $('#searchForm').serialize();
    servicePage(page, searchParams);
  });

  function servicePage(page, searchParams) {
    $.ajax({
      url: searchUrl + "?page=" + page + "&" + searchParams,
      success: function (res) {
        $(".request-loader-time").removeClass("show");
        $('#search_container').html(res);
        if (serviceView == 0) {
          if (window.clusters) {
            window.map.removeLayer(clusters);
            window.clusters.clearLayers();
          }
          window.map.off();
          window.map.remove();

          setTimeout(function () {
            mapInitialize("main-map", featuredContents, regularContents);
          }, 100);
        }
      },
      complete: function () {
        $(".request-loader-time").removeClass("show");
      },
      error: function (xhr) {
        console.error(xhr.responseText);
      }
    });
  }


});
