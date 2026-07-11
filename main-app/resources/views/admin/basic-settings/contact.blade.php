@extends('admin.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Contact Page') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('admin.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Pages') }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Contact Page') }}</a>
      </li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <form action="{{ route('admin.basic_settings.contact_page.update') }}" method="post">
          @csrf
          <div class="card-header">
            <div class="row">
              <div class="col-lg-10">
                <div class="card-title">{{ __('Update Contact Page') }}</div>
              </div>
            </div>
          </div>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-8 offset-lg-2">

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>{{ __('Email Address') . '*' }}</label>
                      <input type="email" class="form-control" name="email_address"
                        value="{{ $data->email_address != null ? $data->email_address : '' }}"
                        placeholder="{{ __('Enter Email Address') }}">
                      @if ($errors->has('email_address'))
                        <p class="mt-2 mb-0 text-danger">{{ $errors->first('email_address') }}</p>
                      @endif
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>{{ __('Contact Number') . '*' }}</label>
                      <input type="text" class="form-control" name="contact_number"
                        value="{{ $data->contact_number != null ? $data->contact_number : '' }}"
                        placeholder="{{ __('Enter Contact Number') }}">
                      @if ($errors->has('contact_number'))
                        <p class="mt-2 mb-0 text-danger">{{ $errors->first('contact_number') }}</p>
                      @endif
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group">
                      <label>{{ __('Latitude') . '*' }}</label>
                      <input type="text" class="form-control" name="latitude"
                        value="{{ $data->latitude != null ? $data->latitude : '' }}" placeholder="{{ __('Enter Latitude') }}"
                        id="latitude_{{ $currentLang->code }}">
                      @if ($errors->has('latitude'))
                        <p class="mt-2 mb-0 text-danger">{{ $errors->first('latitude') }}</p>
                      @endif
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>{{ __('Longitude') . '*' }}</label>
                      <input type="text" class="form-control" name="longitude"
                        value="{{ $data->longitude != null ? $data->longitude : '' }}"
                        placeholder="{{ __('Enter Longitude') }}" id="longitude_{{ $currentLang->code }}">
                      @if ($errors->has('longitude'))
                        <p class="mt-2 mb-0 text-danger">{{ $errors->first('longitude') }}</p>
                      @endif
                    </div>
                  </div>
                  <div class="col-md-12">
                    <div class="form-group">
                      <label>{{ __('Address') . '*' }}</label>
                      <input type="text" class="form-control" name="address"
                        value="{{ $data->address != null ? $data->address : '' }}"
                        placeholder="{{ __('Enter Longitude') }}" id="search-address_{{ $currentLang->code }}">
                      @if ($websiteInfo->google_map_status == 1)
                        <a href="" class="btn btn-secondary mt-2 btn-sm" data-toggle="modal"
                          data-target="#GoogleMapModal">
                          <i class="fas fa-eye"></i> {{ __('Show Map') }}
                        </a>
                      @endif
                      @if ($errors->has('address'))
                        <p class="mt-2 mb-0 text-danger">{{ $errors->first('address') }}</p>
                      @endif
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card-footer">
            <div class="row">
              <div class="col-12 text-center">
                <button type="submit" class="btn btn-success">
                  {{ __('Update') }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  @if ($websiteInfo->google_map_status == 1)
    @includeIf('map.map-modal');
  @endif
@endsection
@section('script')
  @if ($websiteInfo->google_map_status == 1)
    <script>
      "use strict";
      var defaultLang = "{{ $currentLang->code }}";
      var address = "{{ @$data->address }}"
    </script>
    <script
      src="https://maps.googleapis.com/maps/api/js?key={{ $websiteInfo->google_map_api_key }}&libraries=places&callback=initMap"
      async defer></script>
    <script src="{{ asset('assets/js/edit-map-init.js') }}"></script>
  @endif
@endsection
