jQuery(document).ready(function($) {
    $('#apf-filter-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();

        $.ajax({
            url: apf_ajax.ajax_url,
            method: 'POST',
            data: formData + '&action=apf_filter',
            success: function(response) {
                $('#apf-results').html(response);

                // Update product layout to 4 columns
                $('.products').addClass('columns-4');
            }
        });

        // Update the shop page URL with filter parameters without reloading
        var newUrl = location.protocol + '//' + location.host + location.pathname + '?' + formData;
        history.pushState({path: newUrl}, '', newUrl);

        // Perform the actual product query filtering
        $.ajax({
            url: newUrl,
            success: function(response) {
                var html = $(response).find('ul.products').html();
                $('ul.products').html(html);
            }
        });
    });

    // Reset filters
    $('#reset-filters').on('click', function() {
        $('#apf-filter-form')[0].reset();
        $('#apf-filter-form').trigger('submit');
    });
});
