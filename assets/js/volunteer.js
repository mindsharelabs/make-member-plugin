(function (root, $, undefined) {
  "use strict";

  $(function () {
    /**
     * Volunteer JavaScript - Backend Integration Version
     *
     * This file now focuses only on volunteer-specific UI interactions.
     * Profile card click interception has been removed as volunteer session
     * checking is now handled at the backend level in the AJAX endpoints.
     */

    /**
     * Legacy function for backward compatibility
     */
    function checkUserVolunteerSession(userID) {
      // This function is now deprecated as volunteer session checking
      // is handled at the backend level in the AJAX endpoints
      console.log(
        "Make Volunteer: Legacy function called - volunteer session checking now handled by backend"
      );
      return false;
    }

    /**
     * Clear search interface for both original and optimized systems
     */
    function clearSearchInterface() {
      // Clear optimized search interface
      $("#search-results").addClass("d-none");
      $("#memberSearchOptimized").val("");
      $("#clearSearch").hide();

      // Clear original search interface
      $("#member-list").addClass("d-none");
      $("#memberSearch").val("");
      $("#clearSearchBtn").hide();

      // Show loading in result container
      $("#result").html(
        '<div class="loading"><div><i class="fas fa-spinner fa-spin"></i></div></div>'
      );
    }

    /**
     * Return to member list helper function - works with both systems
     */
    function returnToMemberList() {
      // Clear the result area completely
      $("#result").empty();

      // Clear volunteer sign-out mode
      $("body").removeClass("volunteer-signout-mode");

      // Show optimized search if it exists
      if ($("#search-results").length) {
        $("#search-results").removeClass("d-none");
        $("#memberSearchOptimized").focus();
      } else {
        // Fallback to original system
        $("#member-list").removeClass("d-none");
        $("#memberSearch").focus();

        // Clear search if List.js is available
        if (
          window.memberList &&
          typeof window.memberList.search === "function"
        ) {
          window.memberList.search("");
        }
      }
    }

    /**
     * Show volunteer sign-out interface - works with both systems
     */
    function showVolunteerSignOutInterface(html) {
      var $result = $("#result");
      if ($result.length) {
        $result.html(html);

        // Hide both search interfaces
        $("#member-list").addClass("d-none");
        $("#search-results").addClass("d-none");
      }
    }

    /**
     * Handle volunteer sign-out
     */
    $(document).on("click", ".volunteer-sign-out-btn", function () {
      var userID = $(this).data("user");
      var sessionID = $(this).data("session");

      // Collect selected tasks
      var tasks = [];
      $(".task-item.selected").each(function () {
        tasks.push($(this).data("task"));
      });

      // Get notes
      var notes = $("#volunteerNotes").val() || "";

      $.ajax({
        url: makeMember.ajax_url,
        type: "post",
        data: {
          action: "makeVolunteerSignOut",
          userID: userID,
          tasks: tasks,
          notes: notes,
          nonce: makeMember.volunteer_nonce || "",
        },
        beforeSend: function () {
          $("#result").html(
            '<div class="loading"><div><i class="fas fa-spinner fa-spin"></i></div></div>'
          );
        },
        success: function (response) {
          if (response.success) {
            $("#result").html(response.data.html);

            // Update volunteer status in member list immediately
            if (
              response.data.user_id &&
              window.MakeSignIn &&
              window.MakeSignIn.updateVolunteerStatus
            ) {
              window.MakeSignIn.updateVolunteerStatus(
                response.data.user_id,
                false
              );
            }

            // Mark that we're in volunteer sign-out mode to prevent other timeouts
            $("body").addClass("volunteer-signout-mode");

            // Add a "Return to Member List" button for immediate control
            setTimeout(function () {
              var $result = $("#result");
              if ($result.find(".volunteer-signout-success").length > 0) {
                $result.append(
                  '<div class="text-center mt-4"><button class="btn btn-primary btn-lg return-to-list-btn">Return to Member List</button></div>'
                );

                // Only start the 15-second auto-redirect AFTER the confirmation is shown and button is added
                setTimeout(function () {
                  // Only redirect if we're still in volunteer sign-out mode
                  if ($("body").hasClass("volunteer-signout-mode")) {
                    returnToMemberList();
                  }
                }, 15000);
              }
            }, 1000);
          } else {
            alert(
              "Error signing out: " + (response.data.message || "Unknown error")
            );
          }
        },
        error: function (response) {
          console.log("Error signing out:", response);
          alert("Error signing out. Please try again.");
        },
      });
    });

    /**
     * Handle task selection for sign-out
     */
    $(document).on("click", ".task-item:not(.not-allowed)", function () {
      $(this).toggleClass("selected");
    });

    /**
     * Handle return to member list button click
     */
    $(document).on("click", ".return-to-list-btn", function () {
      returnToMemberList();
    });
  });
})(this, jQuery);
