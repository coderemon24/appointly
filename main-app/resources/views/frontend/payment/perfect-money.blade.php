   <form action="{{ $data['url'] }}" method="{{ $data['method'] }}" id="paymentForm">
       @csrf
       @foreach ($data['val'] as $key => $value)
           <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
       @endforeach
   </form>
   <script>
       "use strict";
       document.getElementById("paymentForm").submit();
   </script>
