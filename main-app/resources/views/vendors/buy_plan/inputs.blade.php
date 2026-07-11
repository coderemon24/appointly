<!-- iyzico payment gateway required inputs -->
<div class="iyzico-element {{ old('payment_method') == 'Iyzico' ? '' : 'd-none' }} row">
    <div class="col-md-6">
        <input type="text" name="zip_code" class="form-control mb-2" placeholder="{{ __('Zip Code') }} "
            value="{{ old('zip_code') }}">
        @error('zip_code')
            <p class="text-danger text-left">{{ $message }}</p>
        @enderror
    </div>
    <div class="col-md-6">
        <input type="text" name="identity_number" class="form-control mb-2" placeholder="{{ __('Identity Number') }}"
            value="{{ old('identity_number') }}">
        @error('identity_number')
            <p class="text-danger text-left">{{ $message }}</p>
        @enderror
    </div>
    <div class="col-md-6">
        <input type="text" name="address" class="form-control mb-2" placeholder="{{ __('Address') }}"
            value="{{ old('address') }}">
        @error('address')
            <p class="text-danger text-left">{{ $message }}</p>
        @enderror
    </div>
    <div class="col-md-6">
        <input type="text" name="city" class="form-control mb-2" placeholder="{{ __('City') }}"
            value="{{ old('city') }}">
        @error('city')
            <p class="text-danger text-left">{{ $message }}</p>
        @enderror
    </div>
    <div class="col-md-6">
        <input type="text" name="country" class="form-control mb-2" placeholder="{{ __('Country') }}"
            value="{{ old('country') }}">
        @error('country')
            <p class="text-danger text-left">{{ $message }}</p>
        @enderror
    </div>
    <div class="col-md-6">
        <input type="text" name="phone" class="form-control mb-2" placeholder="{{ __('Phone') }}"
            value="{{ old('phone') }}">
        @error('phone')
            <p class="text-danger text-left">{{ $message }}</p>
        @enderror
    </div>
</div>


<!-- iyzico payment gateway required inputs -->
<div id="stripe-element">
    <!-- A Stripe Element will be inserted here. -->
</div>
<!-- Used to display form errors -->
<div id="stripe-errors" class="pb-2 text-danger text-left" role="alert"></div>
