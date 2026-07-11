<div class="col-lg-4 col-xl-3">
  <div class="widget-offcanvas offcanvas-lg offcanvas-start" tabindex="-1" id="widgetOffcanvas"
    aria-labelledby="widgetOffcanvas">
    <div class="offcanvas-header px-20">
      <h4 class="offcanvas-title">{{ __('Filter') }}</h4>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#widgetOffcanvas"
        aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-lg-0">
      <aside class="widget-area" data-aos="fade-up">
        <!-- widget-categories- -->
        <div class="widget widget-categories-2 mb-30 p-20 border radius-md">

          <h5 class="title mb-20">{{ __('Categories') }}</h5>
          <!-- -adad- -->
          <ul class="widget-link list-unstyled toggle-list" data-toggle-list="pricingToggle" data-toggle-show="5">
            <li>
              <a href="javascript:void(0)"
                class="category-toggle category-search {{ request()->category ? '' : 'active' }}">
                <i class="far fa-angle-right"></i>
                {{ __('All') }}
              </a>
            </li>
            <!-- cat-item -->
            @foreach ($categories as $category)
              <li class="cat-item-2">
                <a href="javascript:void(0)"
                  class="category-search category-toggle {{ request()->category == $category->slug ? 'active' : '' }}"
                  data-slug="{{ $category->slug }}">
                  <i class="far fa-angle-right"></i>
                  {{ $category->name }}
                </a>
                @if ($category->subcategories->count() > 0)
                  <ul class="widget-link list-unstyled widget-subcategories">
                    @foreach ($category->subcategories as $subcategory)
                      <li>
                        <a href="javascript:void(0)"
                          class="subcategory-search {{ request()->subcategory == $subcategory->slug ? 'active' : '' }}"
                          data-slug="{{ $subcategory->slug }}">
                          {{ $subcategory->name }}
                        </a>
                      </li>
                    @endforeach
                  </ul>
                @endif
              </li>
            @endforeach
          </ul>
          <span class="show-more-btn-2 mt-15" id="showMoreBtn">
            {{ __('Show More') }} +
          </span>

        </div>
        <!--widget-categories-2 end  -->

        <div id="service_details">
          <div class="widget widget-select mb-30 p-20 border radius-md">
            <h5 class="title">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#select"
                aria-expanded="true" aria-controls="select">
                {{ __('Service Details') }}
              </button>
            </h5>
            <div id="select" class="collapse show">
              <div class="accordion-body mt-20 scroll-y">
                <div class="row gx-sm-2">
                  <div class="col-md-12 col-xxl-12">
                    <div class="form-group mb-20">
                      <label class="mb-1 color-dark">{{ __('Service Title') }}</label>
                      <input class="form-control" autocomplete="off" type="text"
                        placeholder="{{ __('Enter Service Title') }}" value="{{ request('service_title') }}"
                        id="search_service_title">
                    </div>
                  </div>
                  <div class="col-md-12 col-xxl-12">
                    <label class="mb-1 color-dark">{{ __('Location') }}</label>
                    <div class="form-group location-group">
                      <input class="form-control" value="{{ request()->input('location') }}" type="text"
                        autocomplete="off" placeholder="{{ __('Enter location') }}" name="location" id="location">
                      @if ($websiteInfo->google_map_status == 1)
                        <button type="button" class="btn btn-sm current-location" onclick="getCurrentLocation()">
                          <i class="fas fa-crosshairs"></i>
                        </button>
                      @endif
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div id="service_type_div">
          <div class="widget widget-ratings mb-30 p-20 border radius-md">
            <h5 class="title">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#sort"
                aria-expanded="true" aria-controls="sort">
                {{ __('Service Type') }}
              </button>
            </h5>
            <div id="sort" class="collapse show">
              <div class="accordion-body mt-20 scroll-y">
                <ul class="list-group custom-radio">
                  <li>
                    <input class="input-radio service_type" type="radio" name="service_type" id="service_type_all"
                      value="service_type_all" checked>
                    <label class="form-radio-label" for="service_type_all"><span>{{ __('All') }}
                  </li>
                  <li>
                    <input class="input-radio service_type" type="radio" name="service_type" id="offline"
                      value="offline">
                    <label class="form-radio-label" for="offline"><span>{{ __('Offline') }}
                  </li>
                  <li>
                    <input class="input-radio service_type" type="radio" name="service_type" id="online"
                      value="online">
                    <label class="form-radio-label" for="online"><span>{{ __('Online') }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="widget widget-price mb-30 p-20 border radius-md">
          <h5 class="title">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#price"
              aria-expanded="true" aria-controls="price">
              {{ __('Pricing') }}
            </button>
          </h5>
          <div id="price" class="collapse show">
            <div class="accordion-body pt-20 scroll-y">
              <div class="row gx-sm-3 d-none">
                <div class="col-md-6">
                  <div class="form-group mb-20">
                    <label class="mb-1 color-dark">{{ __('Minimum') }}</label>
                    <input class="form-control" type="number" name="min" id="min"
                      value="{{ $min }}">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-20">
                    <label class="mb-1 color-dark">{{ __('Maximum') }}</label>
                    <input class="form-control" type="number" name="max" id="max"
                      value="{{ $max }}">
                  </div>
                </div>
              </div>
              <input class="form-control" type="hidden" value="{{ $min }}" id="o_min">
              <input class="form-control" type="hidden" value="{{ $max }}" id="o_max">
              <input type="hidden" id="currency_symbol" value="{{ $basicInfo->base_currency_symbol }}">
              <div class="price-item">
                <div class="price-slider" data-range-slider='filterPriceSlider'></div>
                <div class="price-value">
                  <span class="color-dark">{{ __('Price') }}:
                    <span class="filter-price-range" data-range-value='filterPriceSliderValue'></span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div id="rating_div">
          <div class="widget widget-ratings mb-30 p-20 border radius-md">
            <h5 class="title">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#sort"
                aria-expanded="true" aria-controls="sort">
                {{ __('Ratings') }}
              </button>
            </h5>
            <div id="sort" class="collapse show">
              <div class="accordion-body mt-20 scroll-y">
                <ul class="list-group custom-radio">
                  <li>
                    <input class="input-radio rating" type="radio" name="rating" id="radio1" value=""
                      {{ empty(request()->input('rating')) ? 'checked' : '' }}>
                    <label class="form-radio-label" for="radio1"><span>{{ __('Show All') }}
                  </li>
                  <li>
                    <input class="input-radio rating" type="radio" name="rating" id="radio6" value="5"
                      {{ request()->input('rating') == 5 ? 'checked' : '' }}>
                    <label class="form-radio-label" for="radio6"><span>{{ __('5 stars') }}
                  </li>
                  <li>
                    <input class="input-radio rating" type="radio" name="rating" id="radio5" value="4"
                      {{ request()->input('rating') == 4 ? 'checked' : '' }}>
                    <label class="form-radio-label" for="radio5"><span>{{ __('4 stars and higher') }}
                  </li>
                  <li>
                    <input class="input-radio rating" type="radio" name="rating" id="radio4" value="3"
                      {{ request()->input('rating') == 3 ? 'checked' : '' }}>
                    <label class="form-radio-label" for="radio4"><span>{{ __('3 stars and higher') }}
                  </li>
                  <li>
                    <input class="input-radio rating" type="radio" name="rating" id="radio3" value="2"
                      {{ request()->input('rating') == 2 ? 'checked' : '' }}>
                    <label class="form-radio-label" for="radio3"><span>{{ __('2 stars and higher') }}
                  </li>
                  <li>
                    <input class="input-radio rating" type="radio" name="rating" id="radio2" value="1"
                      {{ request()->input('rating') == 1 ? 'checked' : '' }}>
                    <label class="form-radio-label" for="radio2"><span>{{ __('1 star and higher') }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="cta pb-40">
          <a href="{{ route('frontend.services') }}" class="btn btn-lg btn-primary btn-gradient icon-end w-100">
            <i class="fal fa-sync-alt"></i> {{ __('Reset All') }}
          </a>
        </div>
        @if (!empty(showAd(1)))
          <div class="text-center mt-4">
            {!! showAd(1) !!}
          </div>
        @endif
      </aside>
    </div>
  </div>
</div>
