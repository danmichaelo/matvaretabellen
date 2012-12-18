$(document).ready(function() {

	$('#url')
        .ajaxStart(function () {
            $(this).addClass('spinner');
            $(':button,:submit').attr('disabled', true);
         })
         .ajaxStop(function () {
            $(this).removeClass('spinner');
            $(':button,:submit').attr('disabled', false);
        });

	$('#result').hide();
	$('form').on('submit', function(e) {
		var data = { 
			'url': $('#url').val(), 
			'strip-null-values': $('#strip-zero-values').is(':checked') 
		};
		e.preventDefault();
		$('#result').slideUp();
		$.get('./', data, function(response) {
			$('pre').html(response)
			$('#result').slideDown();
		})
	});

	$('form a').on('click', function(event) {
		event.preventDefault();
		$('#url').val($(this).text());
	});

});
