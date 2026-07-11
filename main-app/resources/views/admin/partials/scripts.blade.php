<script>
  'use strict';

  const baseUrl = "{{ asset('/') }}";
  let Month_wise_appointment = "{{ __('Month wise appointment') }}";
  let Monthly_Income = "{{ __('Monthly Income') }}";
  let delete_text_sure = "{{ __('Are you sure?') }}";
  let delete_text = "{{ __('You won\'t be able to revert this!') }}";
  let package_delete_text =
    "{{ __('If you delete this package, all memberships under this package will be deleted') }}";
  let cancel = "{{ __('Cancel') }}";
  let delete_it = "{{ __('Yes, delete it') }}";
  let sucessText = "{{ __('Success') }}";
  let warningText = "{{ __('Warning') }}";
  let nextText = "{{ __('Next') }}";
  let previousText = "{{ __('Previous') }}";
  let showText = "{{ __('Show') }}";
  let entriesText = "{{ __('entries') }}";
  let Search = "{{ __('Search') }}";
  let Showing = "{{ __('Showing') }}";
  let to = "{{ __('to') }}";
  let ofText = "{{ __('of') }}";
  let you_can_delete_text = "{{ __('You can\'t delete all images') . '!' }}";
  let slider_delete_text = "{{ __('Slider image deleted successfully!') }}";
  let buy_package_use_panel = "{{ __('Please buy a package to use this panel!') }}";
  let something_went_wrong_longText = "{{ __('Something went wrong. Please contact with your owner!') }}";
  let limit_over_msg = "{{ __('Limit is reached of exceeded!') }}";
  let No_Subcategory_Exist = "{{ __('No Subcategory Exist') }}";
  let select_subcategory = "{{ __('Select a subcategory') }}";
  let buy_plane_to_add_staff = "{{ __('Please Buy a plan to add satff!') }}";
  let you_can_not_add_more_staff_for_this_vendor = "{{ __('You can\'t add more staff for this vendor') }}";
  let This_vendor_had_reached_the_limit = "{{ __('This vendor had reached the limit') }}";
  let No_packages_available_for_this_vendor = "{{ __('No packages available for this vendor!') }}";
</script>

{{-- core js files --}}
<script type="text/javascript" src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('assets/js/popper.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('assets/js/bootstrap.min.js') }}"></script>

{{-- jQuery ui --}}
<script type="text/javascript" src="{{ asset('assets/js/jquery-ui.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('assets/js/jquery.ui.touch-punch.min.js') }}"></script>
{{-- jQuery time-picker --}}
<script type="text/javascript" src="{{ asset('assets/js/jquery.timepicker.min.js') }}"></script>
{{-- jQuery scrollbar --}}
<script type="text/javascript" src="{{ asset('assets/js/jquery.scrollbar.min.js') }}"></script>
{{-- bootstrap notify --}}
<script type="text/javascript" src="{{ asset('assets/js/bootstrap-notify.min.js') }}"></script>
{{-- sweet alert --}}
<script type="text/javascript" src="{{ asset('assets/js/sweet-alert.min.js') }}"></script>
{{-- bootstrap tags input --}}
<script type="text/javascript" src="{{ asset('assets/js/bootstrap-tagsinput.min.js') }}"></script>
{{-- bootstrap date-picker --}}
<script type="text/javascript" src="{{ asset('assets/js/bootstrap-datepicker.min.js') }}"></script>
{{-- tinymce editor --}}
<script src="{{ asset('assets/js/tinymce/js/tinymce/tinymce.min.js') }}"></script>
{{-- js color --}}
<script type="text/javascript" src="{{ asset('assets/js/jscolor.min.js') }}"></script>
{{-- fontawesome icon picker js --}}
<script type="text/javascript" src="{{ asset('assets/js/fontawesome-iconpicker.min.js') }}"></script>
{{-- datatables js --}}
<script type="text/javascript" src="{{ asset('assets/js/datatables-1.10.23.min.js') }}"></script>
{{-- datatables bootstrap js --}}
<script type="text/javascript" src="{{ asset('assets/js/datatables.bootstrap4.min.js') }}"></script>
{{-- dropzone js --}}
<script type="text/javascript" src="{{ asset('assets/js/dropzone.min.js') }}"></script>
{{-- atlantis js --}}
<script type="text/javascript" src="{{ asset('assets/js/atlantis.js') }}"></script>
{{-- fonts and icons script --}}
<script type="text/javascript" src="{{ asset('assets/js/webfont.min.js') }}"></script>

@if (session()->has('success'))
  <script>
    'use strict';
    var content = {};

    content.message = '{{ session('success') }}';
    content.title = sucessText;
    content.icon = 'fa fa-bell';

    $.notify(content, {
      type: 'success',
      placement: {
        from: 'top',
        align: 'right'
      },
      showProgressbar: true,
      time: 1000,
      delay: 4000
    });
  </script>
@endif

@if (session()->has('warning'))
  <script>
    'use strict';
    var content = {};

    content.message = '{{ session('warning') }}';
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
      delay: 4000
    });
  </script>
@endif

<script>
  'use strict';
  const account_status = 1;
  const secret_login = 1;
  let time_format = "{{ $settings->time_format }}";
</script>

{{-- select2 js --}}
<script type="text/javascript" src="{{ asset('assets/js/select2.min.js') }}"></script>
{{-- admin-main js --}}
<script type="text/javascript" src="{{ asset('assets/js/admin-main.js') }}"></script>
<script>
  "use strict";

  function changeLang(elm) {
    let url = "{{ route('admin.language.change', '') }}" + "/" + elm.value;

    let code = elm.value;
    let curr_url = window.location.href.split('?')[0];
    $.ajax({
      url: url,
      method: 'get',
      data: {
        code: code
      },
      success: function() {
        let new_url = curr_url + '?language=' + encodeURIComponent(code);
        window.location = new_url;
      }
    });
  }
</script>
