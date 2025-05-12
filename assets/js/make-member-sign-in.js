
(function( root, $, undefined ) {
    "use strict";

    $(function () {

        
        var metaContainer = $('#result');

        var windowWidth = $(window).width();
        var windowHeight = $(window).height();



        var memberContainer = $('#memberList');


        $( document ).ready(function() {
            loadMembers();
            
        });


        function loadMembers(callback) {
            
            $.ajax({
                url : makeMember.ajax_url,
                type : 'post',
                data : {
                    action : 'makeAllGetMembers'
                },
                success: function(response) {
                  
                    memberContainer.html(response.data.html);
                    const memberList = new List('member-list', { 
                        valueNames: [
                            'email',
                            'name',
                        
                        ],
                        searchClass : 'member-search'
                    });
                  

                },
                error: function (response) {
                    console.log('An error occurred.');
                    console.log(response);
                },
            });
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
                beforeSend : function() {
                    $('#member-list').addClass('d-none');
                    $('#memberSearch').val('');
                    metaContainer.html('<div class="loading"><div><i class="fas fa-spinner fa-spin"></i></div></div>');
                    console.log('Loading user data...');
                },
                success: function(response) {
                  
                    if(response.data.status == 'userfound') {
                        metaContainer.html(response.data.html);

                    } else {
                        metaContainer.html(response.data.html);

                        setTimeout(function() { 
                            metaContainer.html('');
                            $('#member-list').removeClass('d-none');
                            loadMembers();
                        }, 10000);


                    }

                },
                error: function (response) {
                    console.log('An error occurred.');
                    console.log(response);
                },
            });
        }



        $(document).on('click', '.profile-card', function() {
            var userID = $(this).data('user');
            
            submitUser(userID);
        });



        $(document).on('click', '.badge-item:not(.not-allowed), .activity-item', function() {
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
            var userID = $(this).data('user')
            
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
                        console.log(response);
                        metaContainer.html(response.data.html);
                        
                        setTimeout(function() { 
                            metaContainer.html('');
                            loadMembers();
                            $('#member-list').removeClass('d-none');
                        }, 8000);

                        
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
