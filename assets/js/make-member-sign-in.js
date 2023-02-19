
(function( root, $, undefined ) {
    "use strict";

    $(function () {

        
        var metaContainer = $('#result');




        // Setting up Qr Scanner properties
        var html5QrCodeScanner = new Html5QrcodeScanner("reader", {
          fps: 1,
          qrbox: 400,
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

            metaContainer.html('<div class="alert alert-success">Scan Success!</div>');


            
            submitUser(qrCodeMessage);
          


        }



        function submitUser(userID = false, userEmail = false) {
            $.ajax({
                url : makeMember.ajax_url,
                type : 'post',
                data : {
                    action : 'makeGetMember',
                    userID : userID,
                    userEmail : userEmail
                },
                success: function(response) {
                    html5QrCodeScanner.clear();

                    if(response.data.status == 'userfound') {
                        metaContainer.html(response.data.html);

                    } else if(response.data.status == 'nomembership') {
                        metaContainer.html(response.data.html);
                        setTimeout(function() { 
                            metaContainer.html('');
                            html5QrCodeScanner.render(onScanSuccess, onScanError);
                        }, 5500);


                    } else if(response.data.status == 'nouser') {
                        metaContainer.html(response.data.html);
                        
                        setTimeout(function() { 
                            metaContainer.html('');
                            html5QrCodeScanner.render(onScanSuccess, onScanError);
                        }, 2500);

                    }

                },
                error: function (response) {
                    console.log('An error occurred.');
                    console.log(response);
                },
            });
        }



        $(document).on('click', '.badge-item:not(.not-allowed)', function() {
            $(this).toggleClass('selected');

            var selections = $('.selected').length;
    
            if(selections > 0) {
                $('button.sign-in-done').prop("disabled", false);
            } else {
                $('button.sign-in-done').prop("disabled", true);
            }
            // 
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
                        metaContainer.html('<div class="loading"><div><i class="fas fa-spinner fa-spin"></i></div></div>');
                    },
                    success: function(response) {
                        metaContainer.html('<div class="alert alert-success text-center"><h1>Success!</h1><h2>Thank you!</h2></div>');
                        
                        setTimeout(function() { 
                            metaContainer.html('');
                            html5QrCodeScanner.render(onScanSuccess, onScanError);
                            $('button.sign-in-email').removeClass('removed');
                        }, 2500);

                        
                    },
                    error: function (response) {
                        console.log('An error occurred.');
                        console.log(response);
                    },
                });



            } );;

           

        });



        $(document).on('click', 'button.sign-in-email', function(e) {

            $.ajax({
                url : makeMember.ajax_url,
                type : 'post',
                data : {
                    action : 'makeGetEmailForm',
                },
                beforeSend: function() {
                    metaContainer.html('<div class="loading"><div><i class="fas fa-spinner fa-spin"></i></div></div>');
                    $('button.sign-in-email').addClass('removed');
                    html5QrCodeScanner.clear();
                },
                success: function(response) {
                    metaContainer.html(response.data.html);
                    console.log(response);



                    //return to normal sign in after 30sec
                    setTimeout(function() { 
                        metaContainer.html('');
                        html5QrCodeScanner.render(onScanSuccess, onScanError);
                        $('button.sign-in-email').removeClass('removed');
                    }, 30000);

                    
                },
                error: function (response) {
                    console.log('An error occurred.');
                    console.log(response);
                },
            });
        })



        $(document).on('submit', 'form#emailSubmit', function(e) {
            e.preventDefault();

            var userEmail = $('#emailSubmit').find('input[name="userEmail"]').val();
            submitUser(false, userEmail);

        })






    });


} ( this, jQuery ));
