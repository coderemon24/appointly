"use strict";
$(function () {

  $('body').on('click', '.featured', function () {
    let id = $(this).data('id');
    $("#featuredModal").modal('show');
    $('#service_id').val(id);

    /**
     * Select payment method
     */
    $('body').on('change', 'select[name="gateway"]', function () {
      let value = $(this).val();
      let dataType = parseInt(value);

      // Hide all gateway related elements
      $('#stripe-element, #authorizenet-element, .offline-gateway-info').addClass('d-none');

      if (isNaN(dataType)) {
        // Show or hide stripe card inputs
        if (value === 'stripe') {
          $('#stripe-element').removeClass('d-none');
        }
        // Show or hide iyzico inputs
        else if (value === 'iyzico') {
          $('.iyzico-element').removeClass('d-none');
        }

        // Show or hide authorize.net card inputs
        else if (value === 'authorize.net') {
          $('#authorizenet-element').removeClass('d-none');
          $("#authorizenet-element input").removeAttr('disabled');
        }
      } else {
        // Show particular offline gateway information
        $('#offline-gateway-' + value).removeClass('d-none');
      }
    });

    /**
     * init stripe payment method
     */
    if (stripe_key) {
      var stripe = Stripe(stripe_key);
      var elements = stripe.elements();
      // Create a Stripe Element for the card field
      var cardElement = elements.create('card', {
        style: {
          base: {
            iconColor: '#454545',
            color: '#454545',
            fontWeight: '500',
            lineHeight: '50px',
            fontSmoothing: 'antialiased',
            backgroundColor: '#f2f2f2',
            ':-webkit-autofill': {
              color: '#454545',
            },
            '::placeholder': {
              color: '#454545',
            },
          }
        },
      });
      if ($('#stripe-element').length) {
        cardElement.mount('#stripe-element');
      }
      // hide after init
      if ($('#stripe-element').length) {
        $('#stripe-element').addClass('d-none');
      }
    }

    /**
     * submit payment form
     */
    $("#featuredBtn").on('click', function (e) {
      $(e.target).attr('disabled', true);
      $(".request-loader").addClass("show");

      // Validate the form fields
      if ($('#gateway').val() == 'stripe') {
        stripe.createToken(cardElement).then(function (result) {
          if (result.error) {
            // Display errors to the customer
            var errorElement = document.getElementById('stripe-errors');
            errorElement.textContent = result.error.message;
            return; // Prevent further execution
          } else {
            stripeTokenHandler(result.token);
          }
        });
      } else if ($('#gateway').val() == 'authorize.net') {
        sendPaymentDataToAnet();
      }

      let paymentForm = document.getElementById('payment-form');
      let fd = new FormData(paymentForm);
      let url = $("#payment-form").attr('action');
      let method = $("#payment-form").attr('method');

      $.ajax({
        url: url,
        method: method,
        data: fd,
        contentType: false,
        processData: false,
        success: function (data) {
          $(e.target).attr('disabled', false);
          $('.request-loader').removeClass('show');
          $('.em').each(function () {
            $(this).html('');
          });

          if (data.redirectURL) {
            window.location.href = data.redirectURL;
          } else {
            $('#razorPayForm').html(data);
            $(".request-loader-time").removeClass("show");
          }
        },
        error: function (error) {
          $('.em').each(function () {
            $(this).html('');
          });

          for (let x in error.responseJSON.errors) {
            document.getElementById('err_' + x).innerHTML = error.responseJSON.errors[x][0];
          }

          $('.request-loader').removeClass('show');
          $(e.target).attr('disabled', false);
        }
      });
    });




    // Send the token to your server
    function stripeTokenHandler(token) {
      // Add the token to the form data before submitting to the server
      var form = document.getElementById('payment-form');
      var hiddenInput = document.createElement('input');
      hiddenInput.setAttribute('type', 'hidden');
      hiddenInput.setAttribute('name', 'stripeToken');
      hiddenInput.setAttribute('value', token.id);
      form.appendChild(hiddenInput);

      // Submit the form to your server
      form.submit();
    }
    //Send the authorize.net token to your server
    function sendPaymentDataToAnet() {
      // Set up authorisation to access the gateway.
      var authData = {};
      authData.clientKey = authorize_public_key;
      authData.apiLoginID = authorize_login_key;

      var cardData = {};
      cardData.cardNumber = document.getElementById("anetCardNumber").value;
      cardData.month = document.getElementById("anetExpMonth").value;
      cardData.year = document.getElementById("anetExpYear").value;
      cardData.cardCode = document.getElementById("anetCardCode").value;

      // Now send the card data to the gateway for tokenisation.
      // The responseHandler function will handle the response.
      var secureData = {};
      secureData.authData = authData;
      secureData.cardData = cardData;
      Accept.dispatchData(secureData, responseHandler);
    }
    function responseHandler(response) {
      if (response.messages.resultCode === "Error") {
        var i = 0;
        let errorLists = ``;
        while (i < response.messages.message.length) {
          errorLists += `<li class="text-danger">${response.messages.message[i].text}</li>`;
          i = i + 1;
        }
        $("#authorizeNetErrors").show();
        $("#authorizeNetErrors").html(errorLists);
      } else {
        paymentFormUpdate(response.opaqueData);
      }
    }
    function paymentFormUpdate(opaqueData) {
      var form = document.getElementById('payment-form');
      document.getElementById("opaqueDataDescriptor").value = opaqueData.dataDescriptor;
      document.getElementById("opaqueDataValue").value = opaqueData.dataValue;
      form.submit();
    }
  });

});

