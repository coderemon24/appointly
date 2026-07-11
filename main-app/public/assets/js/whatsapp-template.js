"use strict";

$(document).ready(function () {
  // make selected items sortable
  var $select = $('.select2').select2({
    placeholder: "Select options"
  });

  $select.select2({
    sorter: data => data // preserve DOM order
  }).on('select2:select', function (e) {
    var element = e.params.data.element;
    var $element = $(element);

    $element.detach();
    $(this).append($element);
    $(this).trigger('change');
  });


  function reindexTable() {
    let tbody = $('#params-table tbody');
    let rows = tbody.find('tr');

    let counter = 1;

    rows.each(function () {
      if ($(this).hasClass('list-invoice')) {
        // if invoice then skip index
        $(this).find('td:first').html(
          `<span class="text-primary fw-bold">Document Header</span><br>
                 <small class="text-muted">(used for attaching a sample invoice PDF, no index needed)</small>`
        );
      } else {
        // if not invoice then add index
        $(this).find('td:first').text(`{{ ${counter} }}`);
        counter++;
      }
    });

    // append invoice row at the end
    let invoiceRow = tbody.find('.list-invoice');
    if (invoiceRow.length) {
      tbody.append(invoiceRow);
    }
  }


  $('.select2').on('select2:select', function (e) {
    let selectedItem = e.params.data;

    if ($(".list-" + selectedItem.id).length === 0) {
      let rowHtml = `
                <tr class="list-${selectedItem.id}">
                    <td scope="row" class="text-danger font-weight-bold"></td>
                    <td scope="row">${allOptions[selectedItem.id]}</td>
                </tr>
            `;
      $('#params-table tbody').append(rowHtml);
      reindexTable();
    }
  });

  $('.select2').on('select2:unselect', function (e) {
    let removedItem = e.params.data;
    $(".list-" + removedItem.id).remove();
    reindexTable();
  });

  reindexTable();
});
