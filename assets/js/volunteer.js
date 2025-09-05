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
      // Stop any live timer interval from the pre-signout screen
      if (window.makesfVolunteerLiveTimer) {
        try { clearInterval(window.makesfVolunteerLiveTimer); } catch (e) {}
        window.makesfVolunteerLiveTimer = null;
      }

      // Prefer the unified sign-in reset which also restores the heading
      if (window.MakeSignIn && typeof window.MakeSignIn.returnToInterface === 'function') {
        window.MakeSignIn.returnToInterface();
        return;
      }

      // Fallback behavior (older builds): manually reset UI
      $("#result").empty();
      $("body").removeClass("volunteer-signout-mode");

      // Reset heading if present
      var $h = $("#makesf-signin-heading");
      var $strong = $h.find('strong').first();
      if ($strong.length) { $strong.text('Member Sign In'); }
      else if ($h.length) { $h.text('Member Sign In'); }

      // Restore visible list/search
      if ($("#search-results").length) {
        $("#search-results").removeClass("d-none");
        $("#memberSearchOptimized").focus();
      } else {
        $("#member-list").removeClass("d-none");
        $("#memberSearch").focus();
        if (window.memberList && typeof window.memberList.search === "function") {
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
            // Ensure the success UI replaces the grid, not appended under it
            showVolunteerSignOutInterface(response.data.html);

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

            // If server did not include a footer back button, add a minimal fallback
            setTimeout(function () {
              var $result = $("#result");
              if ($result.find(".volunteer-signout-success").length > 0 && $result.find(".return-to-list-btn").length === 0) {
                $result.append('<div class="badge-footer"><div class="d-flex justify-content-center" style="gap:12px;"><button class="btn btn-outline-secondary btn-lg return-to-list-btn">Back</button></div></div>');
              }
            }, 500);
          } else {
            alert(
              "Error signing out: " + (response.data.message || "Unknown error")
            );
          }
        },
        error: function (response) {
          console.log("Error signing out:", response);
          if (typeof navigator !== 'undefined' && navigator && navigator.onLine === false) {
            var $result = $("#result");
            $result.html(
              '<div class="alert alert-warning text-center" style="max-width:720px;margin:2rem auto;">' +
                '<h3 style="margin-bottom:.5rem;">You appear to be offline</h3>' +
                '<div>Reconnect to Wi‑Fi to complete sign-out. This screen will update once you are back online.</div>' +
              '</div>'
            );
          } else {
            alert("Error signing out. Please try again.");
          }
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

    // Back button on pre-signout screen
    $(document).on("click", ".volunteer-back-btn", function () {
      returnToMemberList();
    });
  });
})(this, jQuery);
