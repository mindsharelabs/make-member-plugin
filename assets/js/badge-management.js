/**
 * Badge Management JavaScript for Make Member Plugin
 * Handles badge award/remove functionality in attendee tables
 */

jQuery(document).ready(function ($) {
  // Handle badge toggle button clicks (guarded for make_badge_ajax)
  if (typeof window.make_badge_ajax !== "undefined") {
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
  }

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
  // ---------------------------------------------------------------------------
  // User Profile Badge Expiration Admin (profile.php / user-edit.php)
  // Requires MAKESF_BADGE_ADMIN localized object.
  // ---------------------------------------------------------------------------
  if (typeof window.MAKESF_BADGE_ADMIN !== "undefined") {
    function setRowStatus($row, msg, isError) {
      var $status = $row.find(".makesf-badge-renew-status");
      $status.text(msg || "");
      $status.removeClass("text-danger text-success");
      if (msg) {
        $status.addClass(isError ? "text-danger" : "text-success");
      }
    }

    function postAjax(action, nonce, data) {
      var payload = $.extend(
        {
          action: action,
          nonce: nonce,
        },
        data || {}
      );
      return $.post(window.MAKESF_BADGE_ADMIN.ajax_url, payload);
    }

    // Renew badge
    $(document).on("click", ".makesf-renew-badge", function (e) {
      e.preventDefault();

      var $btn = $(this);
      var badgeId = $btn.data("badge");
      var userId = $btn.data("user");
      var $row = $btn.closest("tr");

      $btn.prop("disabled", true).text(window.MAKESF_BADGE_ADMIN.i18n.renewing);
      setRowStatus($row, "", false);

      postAjax(window.MAKESF_BADGE_ADMIN.action, window.MAKESF_BADGE_ADMIN.nonce, {
        user_id: userId,
        badge_id: badgeId,
      })
        .done(function (resp) {
          if (resp && resp.success && resp.data) {
            if (resp.data.last_time) {
              $row.find(".makesf-badge-last-time").text(resp.data.last_time);
            }
            if (resp.data.expires) {
              $row.find(".makesf-badge-expires").text(resp.data.expires);
            }
            setRowStatus($row, resp.data.message || "Renewed", false);
          } else {
            setRowStatus(
              $row,
              resp && resp.data && resp.data.message
                ? resp.data.message
                : window.MAKESF_BADGE_ADMIN.i18n.error,
              true
            );
          }
        })
        .fail(function () {
          setRowStatus($row, window.MAKESF_BADGE_ADMIN.i18n.error, true);
        })
        .always(function () {
          $btn.prop("disabled", false).text(window.MAKESF_BADGE_ADMIN.i18n.renew);
        });
    });

    // Add badge
    $(document).on("click", "#makesf-add-badge-btn", function (e) {
      e.preventDefault();

      var $btn = $(this);
      var userId = $btn.data("user");
      var $select = $("#makesf-add-badge");
      var badgeId = parseInt($select.val(), 10);
      var $status = $("#makesf-add-badge-status");

      $status.removeClass("text-danger text-success").text("");

      if (!badgeId) {
        $status.addClass("text-danger").text("Select a badge first.");
        return;
      }

      $btn.prop("disabled", true).text("Adding…");

      postAjax(window.MAKESF_BADGE_ADMIN.add_action, window.MAKESF_BADGE_ADMIN.add_nonce, {
        user_id: userId,
        badge_id: badgeId,
      })
        .done(function (resp) {
          if (resp && resp.success && resp.data) {
            $status.addClass("text-success").text(resp.data.message || "Badge added.");

            // Remove the selected option from the dropdown (so it can't be added twice)
            $select.find('option[value="' + badgeId + '"]').remove();
            $select.val("");

            // If the table exists, append a minimal row.
            var $table = $(".makesf-badge-admin-table");
            if ($table.length) {
              var badgeName = resp.data.badge_name || ("Badge #" + badgeId);

              // Build a new row consistent with the PHP markup
              var $row = $(
                "<tr>" +
                  "<td><strong></strong><br><code></code></td>" +
                  "<td class='makesf-badge-last-time'>" + (resp.data.last_time || "—") + "</td>" +
                  "<td class='makesf-badge-expires'>" + (resp.data.expires || "—") + "</td>" +
                  "<td><button type='button' class='button button-primary makesf-renew-badge'>" +
                    window.MAKESF_BADGE_ADMIN.i18n.renew +
                  "</button></td>" +
                  "<td><button type='button' class='button button-link-delete makesf-remove-badge'>Remove</button></td>" +
                  "<td><span class='makesf-badge-renew-status notice notice-success'>" + (resp.data.status || "Active") + "</span></td>" +
                "</tr>"
              );

              $row.find("strong").text(badgeName);
              $row.find("code").text(String(badgeId));

              // Data attributes for renew/remove
              $row
                .find(".makesf-renew-badge")
                .attr("data-user", userId)
                .attr("data-badge", badgeId);

              $row
                .find(".makesf-remove-badge")
                .attr("data-user", userId)
                .attr("data-badge-id", badgeId);

              $table.find("tbody").append($row);

              // Remove any "No badges" message if present (in case we add after empty state)
              $(".makesf-badge-admin-wrap em:contains('No badges')").closest("p").remove();
            }
          } else {
            $status
              .addClass("text-danger")
              .text(
                resp && resp.data && resp.data.message
                  ? resp.data.message
                  : window.MAKESF_BADGE_ADMIN.i18n.error
              );
          }
        })
        .fail(function () {
          $status.addClass("text-danger").text(window.MAKESF_BADGE_ADMIN.i18n.error);
        })
        .always(function () {
          $btn.prop("disabled", false).text("Add");
        });
    });

    // Remove badge
    $(document).on("click", ".makesf-remove-badge", function (e) {
      e.preventDefault();

      var $btn = $(this);
      var userId = $btn.data("user");
      var badgeId = parseInt($btn.data("badge-id"), 10);
      var $row = $btn.closest("tr");

      if (!badgeId) {
        return;
      }

      $btn.prop("disabled", true).text("Removing…");
      setRowStatus($row, "", false);

      postAjax(window.MAKESF_BADGE_ADMIN.remove_action, window.MAKESF_BADGE_ADMIN.remove_nonce, {
        user_id: userId,
        badge_id: badgeId,
      })
        .done(function (resp) {
          if (resp && resp.success) {
            // Add back to dropdown so it can be re-added later
            var badgeName = "";
            var $nameEl = $row.find("td:first strong");
            if ($nameEl.length) {
              badgeName = $nameEl.text().trim();
            }

            var $select = $("#makesf-add-badge");
            if ($select.length) {
              var $opt = $("<option></option>")
                .val(badgeId)
                .text(badgeName || ("Badge #" + badgeId));
              $select.append($opt);
            }

            $row.remove();
          } else {
            setRowStatus(
              $row,
              resp && resp.data && resp.data.message
                ? resp.data.message
                : window.MAKESF_BADGE_ADMIN.i18n.error,
              true
            );
          }
        })
        .fail(function () {
          setRowStatus($row, window.MAKESF_BADGE_ADMIN.i18n.error, true);
        })
        .always(function () {
          $btn.prop("disabled", false).text("Remove");
        });
    });
  }

});
