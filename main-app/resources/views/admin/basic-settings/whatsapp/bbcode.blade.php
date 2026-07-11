<div class="col-lg-5">
    <!-- Note for Variable Table -->
    <div class="variable-note mb-2 p-3 border rounded">
        <h4 class="text-warning mb-3"><strong>{{ __('Note') . ' : ' }}</strong></h4>
        <ul class="mb-0">
            <li class="mb-2 text-dark">
                {{ __('The values in the') }}
                <span class="fw-bold">{{ __('"Variable Name"') }}</span>
                {{ __('column will appear in your message according to these variables.') }}
            </li>
            <li class="mb-2 text-dark">
                {{ __('You can change the order of appearance by reordering the parameters in the "Params" field.') }}
            </li>
            <li class="mb-2 text-dark">
                {{ __('Use the variables exactly as listed when creating your template message.') }}
                <a target="_blank" href="https://prnt.sc/ZYjIHb5LKNxv" class="text-primary fw-bold">
                    {{ __('View example') }}
                </a>
            </li>
            <li class="text-dark">
                {{ __('For invoices, select "Document" as the header type and upload a sample PDF.') }}
                <a target="_blank" href="https://prnt.sc/cGiJ2lGaqnVs" class="text-primary fw-bold">
                    {{ __('See example') }}
                </a>
            </li>
        </ul>
    </div>



    <!-- Variable Table -->
    <table id="params-table" class="table table-striped border">
        <thead>
            <tr>
                <th scope="col">{{ __('Variable Name') }}</th>
                <th scope="col">{{ __('Meaning') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $counter = 1; @endphp
            @foreach (json_decode($template->params, true) as $index => $variable)
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
                @endphp
                <tr class="list-{{ $variable }}">
                    <td scope="row" class="text-danger font-weight-bold">
                        @if ($variable === 'invoice')
                            <span class="text-primary fw-bold">
                                {{ __('Document Header') }}
                            </span>
                            <br>
                            <small class="text-muted">
                                {{ __('(used for attaching a sample invoice PDF, no index needed)') }}
                            </small>
                        @else
                            @php echo '{{ ' . $counter . ' }}'; @endphp
                            @php $counter++; @endphp
                        @endif
                    </td>
                    <td scope="row">{{ __($allOptions[$variable]) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
