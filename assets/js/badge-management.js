/**
 * Badge Management JavaScript for Make Member Plugin
 * Handles badge award/remove functionality in attendee tables
 */

jQuery(document).ready(function ($) {
  // Handle badge toggle button clicks
  $(document).on("click", "button.make-attendee-badge-toggle", function (e) {
    e.preventDefault();

    var $button = $(this);
    var user_id = $button.data("user_id");
    var cert_id = $button.data("cert_id");
    var event_id = $button.data("event_id");
    var akey = $button.data("akey");
    var sub_event = $button.data("sub_event");

    // Disable button during request
    $button.prop("disabled", true);
    var originalText = $button.find(".badge-status").text();
    $button.find(".badge-status").text("Processing...");

    // Prepare AJAX data
    var ajaxData = {
      action: "make_badge_toggle",
      user_id: user_id,
      cert_id: cert_id,
      event_id: event_id,
      akey: akey,
      sub_event: sub_event,
      nonce: make_badge_ajax.nonce,
    };

    // Send AJAX request
    $.ajax({
      url: make_badge_ajax.ajax_url,
      type: "POST",
      data: ajaxData,
      dataType: "json",
      success: function (response) {
        if (response.success) {
          // Update button text and class
          $button.find(".badge-status").text(response.data.html);

          // Toggle button class
          if (response.data.new_status) {
            $button.addClass("badged");
          } else {
            $button.removeClass("badged");
          }

          // Show success message (optional - can be removed if too intrusive)
          if (response.data.message) {
            // Create a temporary success message instead of alert
            var $message = $(
              '<div class="badge-success-message" style="color: green; font-weight: bold; margin-top: 5px;">' +
                response.data.message +
                "</div>"
            );
            $button.parent().append($message);
            setTimeout(function () {
              $message.fadeOut(function () {
                $message.remove();
              });
            }, 3000);
          }

          // Update the badges display in the same row to show the new badge immediately
          var $row = $button.closest("tr");
          var $table = $button.closest("table");

          // Find the "Badges" column index
          var badgesColumnIndex = -1;
          $table.find("thead th").each(function (index) {
            if ($(this).text().trim() === "Badges") {
              badgesColumnIndex = index;
              return false; // break
            }
          });

          // Update the badges display with the new data
          if (response.data.updated_badges && badgesColumnIndex >= 0) {
            var $badgesCell = $row.find("td").eq(badgesColumnIndex);
            if ($badgesCell.length > 0) {
              $badgesCell.html(response.data.updated_badges);

              // Add a subtle highlight effect to show the change
              $badgesCell.addClass("just-updated");
              setTimeout(function () {
                $badgesCell.removeClass("just-updated");
              }, 2000);
            }
          }

          // Add visual feedback to the button
          $button.addClass("just-updated");
          setTimeout(function () {
            $button.removeClass("just-updated");
          }, 2000);
        } else {
          // Handle error
          console.error("Badge toggle error:", response.data);
          alert("Error: " + (response.data || "Unknown error occurred"));
          $button.find(".badge-status").text(originalText);
        }
      },
      error: function (xhr, status, error) {
        // Handle AJAX error
        console.error("AJAX Error:", error);
        console.error("Response:", xhr.responseText);
        alert("Network error occurred. Please try again.");
        $button.find(".badge-status").text(originalText);
      },
      complete: function () {
        // Re-enable button
        $button.prop("disabled", false);
      },
    });
  });

  // Add hover effects for badge buttons
  $(document)
    .on("mouseenter", "button.make-attendee-badge-toggle", function () {
      $(this).addClass("hover");
    })
    .on("mouseleave", "button.make-attendee-badge-toggle", function () {
      $(this).removeClass("hover");
    });

  // Add visual feedback when buttons are clicked
  $(document).on("click", "button.make-attendee-badge-toggle", function () {
    $(this).addClass("processing");
  });
});
