"use strict";
var markers = [], map, marker_clusterer, clusters;

$(document).ready(function () {
  const featuredContent = typeof featuredContents !== 'undefined' ? featuredContents : [];
  const regularContent = typeof regularContents !== 'undefined' ? regularContents : [];
  
  var mapId = $(".btn[data-bs-target='#mapModal']").is(":visible") ? "modal-main-map" : "main-map";

  mapInitialize(mapId, featuredContent, regularContent);

  document.getElementById('mapModal').addEventListener('shown.bs.modal', function () {
    mapInitialize(mapId, featured_content, listing_content);
  });
  $('#mapModal').on('shown.bs.modal', function () {
    if (!map) {
      initializeMap();
    } else {
      map.invalidateSize();
    }
  });
});

function mapInitialize(mapId, featuredContent, regularContent) {

  var l = !0,
    p = mapStyle,
    o = !0;
  map = L.map(mapId, {
    center: [105.931426295101, 160.020130352685],
    minZoom: 0,
    maxZoom: 22,
    scrollWheelZoom: o,
    tap: !L.Browser.mobile,
    fullscreenControl: true,
    fullscreenControlOptions: {
      position: 'topleft'
    }
  });

  var t = L.tileLayer('//{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
    maxZoom: 22

  }).addTo(map);

  if (regularContent && regularContent.length > 0) {
    regularContent.forEach(element => {

      var s = `
          <div class="marker-container">
              <div class="marker-card">
                  <div class="front face">
                      <i class="${element.categoryIcon}"></i>
                  </div>
                  <div class="marker-arrow"></div>
              </div>
          </div>`;
      let a = L.marker([element.latitude, element.longitude], {
        icon: L.divIcon({
          html: s,
          className: 'open_steet_map_marker google_marker',
          iconSize: [40, 46],
          popupAnchor: [1, -35],
          iconAnchor: [20, 46],
        })
      });
      let serviceUrl = baseURL + '/services/details/' + element.slug + "/" + element.id;
      a.bindPopup('<div class="product-default"> <figure class="product-img"> <a href="' + serviceUrl + '" class="lazy-container radius-sm ratio ratio-2-3"> <img class="lazyload" src="assets/images/placeholder.png" data-src="' + baseURL + '/assets/img/services/' + element.service_image + '" alt="Service"> </a></figure><div class="product-details px-2 py-4"><h6 class="service-title"><a href="' + serviceUrl + '">' + element.name + '</a></h6><span class="product-location icon-start"><i class="fal fa-map-marker-alt"></i>' + element.address + '</span></div></div>');
      clusters.addLayer(a);
      markers.push(a);
      map.addLayer(clusters);
    });
  } else {
    console.log("No regular servie found");
  }

  if (featuredContent && featuredContent.length > 0) {
    featuredContent.forEach(featuredElement => {
      var s = `
          <div class="marker-container">
              <div class="marker-card">
                  <div class="front face">
                      <i class="${featuredElement.categoryIcon}"></i>
                  </div>
                  <div class="marker-arrow"></div>
              </div>
          </div>`;
      let a = L.marker([featuredElement.latitude, featuredElement.longitude], {
        icon: L.divIcon({
          html: s,
          className: 'open_steet_map_marker google_marker',
          iconSize: [40, 46],
          popupAnchor: [1, -35],
          iconAnchor: [20, 46],
        })
      });
      let serviceUrl = baseURL + '/services/details/' + featuredElement.slug + "/" + featuredElement.id;
      a.bindPopup('<div class="product-default featured"> <figure class="product-img"> <a href="' + serviceUrl + '" class="lazy-container radius-sm ratio ratio-2-3"> <img class="lazyload" src="assets/images/placeholder.png" data-src="' + baseURL + '/assets/img/services/' + featuredElement.service_image + '" alt="Service"> </a></figure><div class="product-details px-2 py-4"><h6 class="service-title"><a href="' + serviceUrl + '">' + featuredElement.name + '</a></h6><span class="product-location icon-start"><i class="fal fa-map-marker-alt"></i>' + featuredElement.address + '</span></div></div>');
      clusters.addLayer(a);
      markers.push(a);
      map.addLayer(clusters);
    });
  } else {
    console.log("No featured servie found");
  }

  if (markers.length) {
    var e = [];
    for (var i in markers) {
      if (typeof markers[i]['_latlng'] == 'undefined') continue;
      var c = [markers[i].getLatLng()];
      e.push(c)
    };
    var r = L.latLngBounds(e);
    map.fitBounds(r)
  };
  if (!markers.length) { }
}

var timerMap, ad_galleries, firstSet = !1,
  mapRefresh = !0,
  loadOnTab = !0,
  zoomOnMapSearch = 22,
  clusterConfig = null,
  markerOptions = null,
  mapDisableAutoPan = !1,
  rent_inc_id = '55',
  scrollWheelEnabled = !1,
  myLocationEnabled = !0,
  rectangleSearchEnabled = !0,
  mapSearchbox = !0,
  mapRefresh = !0,
  map_main, styles, mapStyle = [{
    'featureType': 'landscape',
    'elementType': 'geometry.fill',
    'stylers': [{
      'color': '#fcf4dc'
    }]
  }, {
    'featureType': 'landscape',
    'elementType': 'geometry.stroke',
    'stylers': [{
      'color': '#c0c0c0'
    }, {
      'visibility': 'on'
    }]
  }];

clusters = L.markerClusterGroup({
  spiderfyOnMaxZoom: true,
  showCoverageOnHover: false,
  zoomToBoundsOnClick: true
});

var jpopup_customOptions = {
  'maxWidth': 'initial',
  'width': 'initial',
  'className': 'popupCustom'
};

