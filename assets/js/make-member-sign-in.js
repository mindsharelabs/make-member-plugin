
(function( root, $, undefined ) {
    "use strict";

    $(function () {

        
        var metaContainer = $('#result');




        // Setting up Qr Scanner properties
        var html5QrCodeScanner = new Html5QrcodeScanner("reader", {
          fps: 1,
          qrbox: 300,
          aspectRatio: 1.7777778
        });

        // in
        html5QrCodeScanner.render(onScanSuccess, onScanError);


        // When scan is unsuccessful fucntion will produce error message
        function onScanError(errorMessage) {
          // Handle Scan Error
        }

        // When scan is successful fucntion will produce data
        function onScanSuccess(qrCodeMessage) {


            console.log(qrCodeMessage);

            metaContainer.html('<div class="success">Scan Success!</div>');


            $.ajax({
                url : makeMember.ajax_url,
                type : 'post',
                data : {
                    action : 'makeGetMember',
                    userID : qrCodeMessage
                },
                success: function(response) {
                    metaContainer.html(response.data);
                    html5QrCodeScanner.clear();
                    console.log(response);
                },
                error: function (response) {
                    console.log('An error occurred.');
                    console.log(response);
                },
            });

          


        }







        $(document).on('click', '.badge-item', function() {
            $(this).toggleClass('selected');
        });


        $(document).on('click', '.sign-in-done', function() {
            
            var badges = [];
            var userID = $('.profile-card').data('user');
            
            $('.selected').each(function(i, elm) {
                badges.push($(this).data('badge'));
            }).promise().done( function(){


                $.ajax({
                    url : makeMember.ajax_url,
                    type : 'post',
                    data : {
                        action : 'makeMemberSignIn',
                        badges : badges,
                        userID : userID
                    },
                    beforeSend: function() {
                        metaContainer.html('<div class="loading"><div><i class="fas fa-spinner fa-spin"></i></div<</div>');
                    },
                    success: function(response) {
                        // metaContainer.html('<div class="loading"><div><i class="fas fa-spinner fa-spin"></i></div<</div>');
                        metaContainer.html('');
                        html5QrCodeScanner.render(onScanSuccess, onScanError);
                    },
                    error: function (response) {
                        console.log('An error occurred.');
                        console.log(response);
                    },
                });



            } );;

           

        });














    });


} ( this, jQuery ));
