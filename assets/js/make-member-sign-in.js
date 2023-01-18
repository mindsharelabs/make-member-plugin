jQuery(function($) {
	


	$( "#makeMemberSearch" ).submit(function( event ) {
		// console.log(event);
		
		var makerProfileReturn = $('#makerProfileReturn');
		
		event.preventDefault();
		makerProfileReturn.html('<div class="loading text-center p-5"><i class="fas fa-spinner fa-4x fa-spin"></i></div>');

		var makeEmail = $("#makerEmail").val();
	
		$.ajax({
			url : makeMemberSignIn.ajax_url,
			type : 'post',
			data : {
				action : 'makeMemberSignIn',
				makeEmail : makeEmail,
			},
			success: function(response) {
				makerProfileReturn.html(response.data);
				console.log(response);
			},
			error: function (response) {
				
				console.log(response);
			},
		});

	  	event.preventDefault();
	});


	$(document).on('click', '.profile-card', function(e) {
		$('.profile-card').not(this).addClass('faded');
		$(this).addClass('selected');
	})


});