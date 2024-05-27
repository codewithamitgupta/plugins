jQuery(document).ready(function($) {
    $('#apf-filter-form').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: apf_ajax.ajax_url,
            method: 'POST',
            data: $(this).serialize() + '&action=apf_filter',
            success: function(response) {
                $('#apf-results').html(response);
            }
        });
    });
});
