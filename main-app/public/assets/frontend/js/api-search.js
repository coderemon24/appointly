"use strict";
var geocoder;
let isSubmitting = false;
let staffInput;


window.initMap = function (service_id = null) {
  geocoder = new google.maps.Geocoder();
  let input = document.getElementById('location');

  // Listen for 'Enter' key on the input field
  if (input) {
    let searchBox = new google.maps.places.SearchBox(input);
    input.addEventListener('keyup', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        handleSearch();
      }
    });
    // Listen for place changes in the search box
    searchBox.addListener('places_changed', function () {
      const $sortSelect = $('#sort-filter');

      if ($sortSelect.length && $sortSelect.find('option[value="close-by"]').length === 0) {
        $sortSelect.prepend(`
    <option value="close-by" selected>
      ${$sortSelect.data('close-text') || 'Distance: Closest first'}
    </option>
    <option value="distance-away">
      ${$sortSelect.data('far-text') || 'Distance: Farthest first'}
    </option>
  `);
      }

      const places = searchBox.getPlaces();
      if (places.length === 0) {
        return;
      }

      // Get the last selected place
      const place = places[places.length - 1];

      if (!place.geometry) {
        alert("Returned place contains no geometry");
        return;
      }

      const formattedAddress = decodeURIComponent(place.formatted_address);
      document.getElementById('location_val').value = formattedAddress;
      handleSearch();
    });
  }

  function getCurrentLocation() {

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(function (position) {
        var latitude = position.coords.latitude;
        var longitude = position.coords.longitude;

        var latlng = { lat: latitude, lng: longitude };

        // Update the geocode based on the latitude and longitude
        geocodeLatLng(geocoder, latlng);
      }, function (error) {
        alert("Unable to retrieve your location. Error: " + error.message);
      });
    } else {
      alert("Geolocation is not supported by this browser.");
    }
  }

  staffInput = document.getElementById('searchVale');
  if (staffInput) {
    let staffSearchBox = new google.maps.places.SearchBox(staffInput);
    staffInput.addEventListener('keyup', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        const searchVal = staffInput.value.trim() ? staffInput.value.trim() : null;
        searchStaff(searchVal, service_id);
      }
    });

    staffSearchBox.addListener('places_changed', function () {
      const places = staffSearchBox.getPlaces();
      if (places.length === 0) {
        return;
      }
      // Get the last selected place
      const place = places[places.length - 1];

      if (!place.geometry) {
        alert("Returned place contains no geometry");
        return;
      }

      const formattedAddress = decodeURIComponent(place.formatted_address);
      staffInput.value = formattedAddress;
      searchStaff(formattedAddress, service_id);
    });
  }

}

function searchStaff(searchVal, service_id) {
  $('.request-loader-time').addClass('show');
  $.ajax({
    method: 'get',
    url: baseURL + '/services/staff/search/' + service_id,
    data: { searchVal: searchVal },
    success: function (res) {
      $('.request-loader-time').removeClass('show');
      $('.staff-slider').html(res);
      // Staff slider
      var staffSlider = new Swiper(".staff-slider", {
        spaceBetween: 24,
        speed: 1000,
        loop: false,
        autoplay: {
          delay: 3000,
        },
        slidesPerView: 1,
        pagination: false,

        pagination: {
          el: "#staff-slider-pagination",
          clickable: true,
        },

        breakpoints: {
          320: {
            slidesPerView: 1
          },
          576: {
            slidesPerView: 2
          },
          992: {
            slidesPerView: 3
          },
        }
      });

    }
  });
}
// Function to update URL and submit form
function updateUrl(data) {
  let newUrl = new URL(window.location);
  if (data === "location_val") {
    newUrl.searchParams.set('location', $('#location_val').val());
    newUrl.searchParams.set('sort', 'nearest');
  } else {
    newUrl.searchParams.delete('location');
    newUrl.searchParams.delete('sort');
  }
  window.history.replaceState({}, '', newUrl);

  // Submit the form and prevent multiple submissions
  if (!isSubmitting) {
    isSubmitting = true;
    $('#searchForm').submit();
  }
}

// Function to handle the search process
function handleSearch() {
  const locationValue = $('#location').val().trim();
  const $sortSelect = $('#sort-filter');

  if ($sortSelect.length && $sortSelect.find('option[value="close-by"]').length === 0) {
    $sortSelect.prepend(`
    <option value="close-by" selected>
      ${$sortSelect.data('close-text') || 'Distance: Closest first'}
    </option>
    <option value="distance-away">
      ${$sortSelect.data('far-text') || 'Distance: Farthest first'}
    </option>
  `);
  }
  // Check if the form is already submitting
  if (isSubmitting) {
    return;
  }

  if (!locationValue && !isSubmitting) {
    $('#location_val').val('');
    updateUrl(); // Reset URL if location is blank
    isSubmitting = true;
  } else if (locationValue && !isSubmitting) {
    document.getElementById('location_val').value = locationValue;
    updateUrl("location_val");
  }
}


// Geocode latitude and longitude to get the address
function geocodeLatLng(latLng) {
  geocoder.geocode({ location: latLng }, function (results, status) {
    if (status === 'OK') {
      if (results[0]) {
        if (staffInput) {
          $('#searchVale').val(results[0].formatted_address);
          searchStaff(results[0].formatted_address);
        } else {
          $('#location').val(results[0].formatted_address);
          $('#location_val').val(results[0].formatted_address);
          updateUrl("location_val");
        }
      } else {
        console.log('No results found');
      }
    } else {
      console.log('Geocoder failed due to: ' + status);
    }
  });
}

// Get the user's current location
function getCurrentLocation() {
  if (navigator.geolocation) {
    const $sortSelect = $('#sort-filter');

    if ($sortSelect.length && $sortSelect.find('option[value="close-by"]').length === 0) {
      $sortSelect.prepend(`
    <option value="close-by" selected>
      ${$sortSelect.data('close-text') || 'Distance: Closest first'}
    </option>
    <option value="distance-away">
      ${$sortSelect.data('far-text') || 'Distance: Farthest first'}
    </option>
  `);
    }
    navigator.geolocation.getCurrentPosition(function (position) {

      const latLng = { lat: position.coords.latitude, lng: position.coords.longitude };
      geocodeLatLng(latLng);
    }, function (error) {
      alert("Unable to retrieve your location. Error: " + error.message);
    });
  } else {
    alert("Geolocation is not supported by this browser.");
  }
}

// Reset the isSubmitting flag when the form submission is completed
$('#searchForm').on('submit', function () {
  setTimeout(() => {
    isSubmitting = false
  }, 300);
});
if (typeof google !== "undefined" && google.maps) {
  if (typeof initMap === "function") {
    initMap();
  } else {
    // Retry after a slight delay
    setTimeout(() => initMap && initMap(), 100);
  }
}
