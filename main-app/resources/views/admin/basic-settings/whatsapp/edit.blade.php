@extends('admin.layout')

@section('content')
    <div class="page-header">
        <h4 class="page-title">{{ __('Edit Template') }}</h4>
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
                <a href="#">{{ __('Settings') }}</a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.basic_settings.plugins') }}">{{ __('Plugins') }}</a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.basic_settings.whatsapp_manager_template') }}">{{ __('Manage Templates') }}</a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="#">{{ __('Edit Template') }}</a>
            </li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title d-inline-block">{{ __('Edit Template') }}</div>
                    <a class="btn btn-info btn-sm float-right d-inline-block"
                        href="{{ route('admin.basic_settings.whatsapp_manager_template') }}">
                        <span class="btn-label">
                            <i class="fas fa-backward"></i>
                        </span>
                        {{ __('Back') }}
                    </a>
                </div>

                <div class="card-body pt-5">
                    <div class="row">
                        <div class="col-lg-7">
                            <form id="mailTemplateForm"
                                action="{{ route('admin.basic_settings.whatsapp_manager_template_update', $template->id) }}"
                                method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label>{{ __('Name') }}<span class="text-danger">**</span></label>
                                            <input type="text" class="form-control" name="name"
                                                value="{{ old('name', $template->name) }}">
                                            <p>
                                                <span
                                                    class="text-warning">{{ __('Please enter the exact template name as used in your Facebook WhatsApp Manager.') }}</span>
                                                <span>
                                                    <a target="_blank" href="https://prnt.sc/M4m9Cschfvnk"
                                                        class="text-primary">
                                                        {{ __('See example') }}
                                                    </a>
                                                </span>
                                            </p>
                                            @error('name')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label>{{ __('Language Code') }} <span class="text-danger">**</span></label>
                                            <input type="text" class="form-control" name="language_code"
                                                value="{{ old('language_code', $template->language_code) }}">
                                            <p>
                                                <span
                                                    class="text-warning">{{ __('Please enter the exact template language code as used in your Facebook WhatsApp Manager.') }}</span>
                                                <br>
                                                <span>
                                                    <strong>{{ __('Example') . ' : ' }}</strong>
                                                    <span
                                                        class="text-warning">{{ __('en for English, ar for Arabic.') }}</span>
                                                    <span>{{ __('reference') }} <a target="_blank"
                                                            href="https://prnt.sc/TWpsBq-uoxYG">
                                                            {{ __('See example') }}</a></span>
                                                </span>
                                                <br>
                                                <strong
                                                    class="text-danger">{{ __('For special regional codes') . ' : ' }}</strong>
                                                {{ __('If your selected language appears like') }}
                                                <a target="_blank" href="https://prnt.sc/U6xOU8lRYPV6">
                                                    {{ __('See example') }}</a>,
                                                {{ __('then you need to use') }} <strong>en_UAE</strong>.
                                            </p>
                                            @error('language_code')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                    </div>


                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label>{{ __('Purpose') }}<span class="text-danger">**</span></label>
                                            <input type="text" class="form-control" name="temp_type"
                                                value="{{ old('type', str_replace('_', ' ', $template->type)) }}" readonly>
                                            <small
                                                class="text-warning">{{ __('This field cannot be changed, otherwise SMS will not be sent') }}</small>
                                            @error('type')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                    </div>
                                    <input type="hidden" value="{{ $template->type }}" name="type">


                                    <div class="col-lg-12">
                                        <div class="form-group whatsapp_params">
                                            <label>{{ __('Params') }}<span class="text-danger">**</span></label>
                                            <select name="params[]" class="select2 form-control" multiple="multiple">
                                                @php
                                                    $allOptions = [
                                                        'customer_name' => 'Customer Name',
                                                        'vendor_name' => 'Vendor Name',
                                                        'service_title' => 'Service Title',
                                                        'order_number' => 'Booking Number',
                                                        'booking_date' => 'Appointment Date',
                                                        'start_date' => 'Start Time',
                                                        'end_date' => 'End Time',
                                                        'customer_paid' => 'Paid Amount',
                                                        'payment_method' => 'Payment Method',
                                                        'order_status' => 'Booking Status',
                                                        'zoom_info' => 'Zoom Information',
                                                        'invoice' => 'Invoice',
                                                        'staff' => 'Staff Name',
                                                    ];

                                                    $selectedParams =
                                                        json_decode(old('params', $template->params), true) ?? [];
                                                @endphp

                                                @foreach ($selectedParams as $param)
                                                    @if (isset($allOptions[$param]))
                                                        <option value="{{ $param }}" selected>
                                                            {{ __($allOptions[$param]) }}</option>
                                                    @endif
                                                @endforeach

                                                @foreach ($allOptions as $key => $label)
                                                    @if (!in_array($key, $selectedParams))
                                                        <option value="{{ $key }}">{{ __($label) }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <p class="form-text text-warning">
                                                {{ __('Note') . ' : ' }}{{ __('The order in which you select options will determine the order in which the data is displayed.') }}
                                            </p>

                                            @error('params')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>

                                    </div>
                                </div>
                            </form>

                        </div>
                        @includeIf('admin.basic-settings.whatsapp.bbcode')
                    </div>
                </div>

                <div class="card-footer">
                    <div class="row">
                        <div class="col-12 text-center">
                            <button type="submit" form="mailTemplateForm" class="btn btn-success">
                                {{ __('Save Changes') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script>
        let allOptions = @json($allOptions);
    </script>
    <script src="{{ asset('assets/js/whatsapp-template.js') }}"></script>
@endsection
