(function( root, $, undefined ) {
    "use strict";

    $(function () {


        $(document).on('click', 'button.push-event', function (event) {

			event.preventDefault();
            var action = 'makesantafe_' + $(this).data('action');
            var eventID = $(this).data('eventid');
            var destination = $(this).data('destination');
            var container = $('#socialPushContainer');

            var noticeContainer = container.find('.social-notice');

			$.ajax({
				url : makeSocialSettings.ajaxurl,
				type : 'post',
				data : {
					action : action,
					destination : destination,
					eventID : eventID,
				},
                beforeSend: function() {
                    container.addClass('loading');
                    noticeContainer.html('<div class="notice notice-info">Sending event to ' + destination + '...</div>');
                },
				success: function(response) {
                    if(response.success) {
                        noticeContainer.html('<div class="notice notice-success">' + response.data.message + '</div>');
                    } else {
                        noticeContainer.html('<div class="notice notice-error">' + response.data.message + '</div>');
                    }
					console.log(response);
				},
				error: function (response) {
					console.log('An error occurred.');
					console.log(response);
				},
			});

		})




    });


} ( this, jQuery ));
