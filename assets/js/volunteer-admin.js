(function (root, $, undefined) {
  "use strict";

  $(function () {
    var adminNonce = volunteerAdmin.nonce || "";

    // End volunteer session from admin
    $(document).on("click", ".end-session", function () {
      var sessionId = $(this).data("session");
      var button = $(this);

      if (!confirm("Are you sure you want to end this volunteer session?")) {
        return;
      }

      $.ajax({
        url: volunteerAdmin.ajax_url,
        type: "post",
        data: {
          action: "make_volunteer_admin_action",
          admin_action: "end_session",
          session_id: sessionId,
          nonce: adminNonce,
        },
        beforeSend: function () {
          button.prop("disabled", true).text("Ending...");
        },
        success: function (response) {
          if (response.success) {
            button.closest("tr").fadeOut(function () {
              $(this).remove();
            });
            showNotice("Session ended successfully", "success");
          } else {
            showNotice("Error: " + response.data, "error");
            button.prop("disabled", false).text("End Session");
          }
        },
        error: function () {
          showNotice("Error ending session. Please try again.", "error");
          button.prop("disabled", false).text("End Session");
        },
      });
    });

    // View session details
    $(document).on("click", ".view-session", function () {
      var sessionId = $(this).data("session");
      openSessionModal(sessionId);
    });

    // View task statistics
    $(document).on("click", ".view-task-stats", function () {
      var taskId = $(this).data("task");
      // TODO: Implement task statistics modal
      alert("Task statistics for ID: " + taskId);
    });

    // Filter sessions
    $(document).on("click", "#filter-sessions", function () {
      var status = $("#session-filter-status").val();
      var date = $("#session-filter-date").val();

      // Build filter URL
      var url = new URL(window.location);
      if (status) {
        url.searchParams.set("status", status);
      } else {
        url.searchParams.delete("status");
      }
      if (date) {
        url.searchParams.set("date", date);
      } else {
        url.searchParams.delete("date");
      }

      window.location.href = url.toString();
    });

    // Update report period
    $(document).on("change", "#report-period", function () {
      var period = $(this).val();
      var url = new URL(window.location);
      url.searchParams.set("period", period);
      window.location.href = url.toString();
    });

    // Export report
    $(document).on("click", "#export-report", function () {
      var period = $("#report-period").val() || "month";
      var button = $(this);

      $.ajax({
        url: volunteerAdmin.ajax_url,
        type: "post",
        data: {
          action: "make_volunteer_admin_action",
          admin_action: "export_report",
          period: period,
          nonce: adminNonce,
        },
        beforeSend: function () {
          button.prop("disabled", true).text("Exporting...");
        },
        success: function (response) {
          if (response.success) {
            // Create and download CSV file
            var csv = response.data.csv;
            var blob = new Blob([csv], { type: "text/csv" });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "volunteer-report-" + period + ".csv";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showNotice("Report exported successfully", "success");
          } else {
            showNotice("Error exporting report: " + response.data, "error");
          }
          button.prop("disabled", false).text("Export CSV");
        },
        error: function () {
          showNotice("Error exporting report. Please try again.", "error");
          button.prop("disabled", false).text("Export CSV");
        },
      });
    });

    // Schedule management
    $(document).on("click", ".edit-schedule", function () {
      var scheduleId = $(this).data("schedule");
      // TODO: Implement schedule editing modal
      alert("Edit schedule ID: " + scheduleId);
    });

    $(document).on("click", ".deactivate-schedule", function () {
      var scheduleId = $(this).data("schedule");
      var button = $(this);

      if (!confirm("Are you sure you want to deactivate this schedule?")) {
        return;
      }

      // TODO: Implement schedule deactivation
      alert("Deactivate schedule ID: " + scheduleId);
    });

    $(document).on("click", ".activate-schedule", function () {
      var scheduleId = $(this).data("schedule");
      var button = $(this);

      // TODO: Implement schedule activation
      alert("Activate schedule ID: " + scheduleId);
    });

    // Initialize date picker for filters
    if ($("#session-filter-date").length) {
      $("#session-filter-date").datepicker({
        dateFormat: "yy-mm-dd",
        maxDate: 0, // Today
      });
    }

    // Auto-refresh active sessions every 30 seconds
    if ($(".volunteer-stats-grid").length) {
      setInterval(function () {
        refreshActiveSessionsCount();
      }, 30000);
    }

    function refreshActiveSessionsCount() {
      $.ajax({
        url: volunteerAdmin.ajax_url,
        type: "post",
        data: {
          action: "make_volunteer_admin_action",
          admin_action: "get_active_count",
          nonce: adminNonce,
        },
        success: function (response) {
          if (response.success) {
            $(".stat-card:first .stat-number").text(response.data.count);
          }
        },
        error: function () {
          // Silently fail for auto-refresh
        },
      });
    }

    function showNotice(message, type) {
      var noticeClass = type === "success" ? "notice-success" : "notice-error";
      var notice = $(
        '<div class="notice ' +
          noticeClass +
          ' is-dismissible"><p>' +
          message +
          "</p></div>"
      );

      $(".wrap").prepend(notice);

      // Auto-dismiss after 5 seconds
      setTimeout(function () {
        notice.fadeOut(function () {
          $(this).remove();
        });
      }, 5000);
    }

    // Make notices dismissible
    $(document).on("click", ".notice-dismiss", function () {
      $(this)
        .closest(".notice")
        .fadeOut(function () {
          $(this).remove();
        });
    });

    // Dashboard stats cards hover effects
    $(".stat-card").hover(
      function () {
        $(this).addClass("hover");
      },
      function () {
        $(this).removeClass("hover");
      }
    );

    // Table row hover effects
    $(".wp-list-table tbody tr").hover(
      function () {
        $(this).addClass("hover");
      },
      function () {
        $(this).removeClass("hover");
      }
    );

    // Initialize tooltips for status indicators
    $(
      ".status-active, .status-completed, .priority-high, .priority-medium, .priority-low"
    ).each(function () {
      var $this = $(this);
      var title = $this.text();
      $this.attr("title", title);
    });

    // Search functionality for tables
    if ($(".volunteer-admin table").length) {
      var searchInput = $(
        '<div class="table-search" style="margin-bottom: 10px;">' +
          '<input type="text" placeholder="Search..." class="regular-text" id="table-search">' +
          "</div>"
      );

      $(".volunteer-admin table").before(searchInput);

      $("#table-search").on("keyup", function () {
        var value = $(this).val().toLowerCase();
        $(".volunteer-admin table tbody tr").filter(function () {
          $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
      });
    }

    // Bulk actions for sessions (future enhancement)
    var bulkActions = $(
      '<div class="bulk-actions" style="margin-bottom: 10px;">' +
        '<select id="bulk-action">' +
        '<option value="">Bulk Actions</option>' +
        '<option value="end">End Selected Sessions</option>' +
        '<option value="export">Export Selected</option>' +
        "</select>" +
        '<button class="button" id="apply-bulk">Apply</button>' +
        "</div>"
    );

    // Add checkboxes to session tables (future enhancement)
    $(".volunteer-admin table thead tr").prepend(
      '<th><input type="checkbox" id="select-all"></th>'
    );
    $(".volunteer-admin table tbody tr").each(function () {
      var sessionId = $(this)
        .find(".end-session, .view-session")
        .first()
        .data("session");
      if (sessionId) {
        $(this).prepend(
          '<td><input type="checkbox" class="session-checkbox" value="' +
            sessionId +
            '"></td>'
        );
      } else {
        $(this).prepend("<td></td>");
      }
    });

    // Select all functionality
    $(document).on("change", "#select-all", function () {
      $(".session-checkbox").prop("checked", $(this).prop("checked"));
    });

    // Update select all when individual checkboxes change
    $(document).on("change", ".session-checkbox", function () {
      var total = $(".session-checkbox").length;
      var checked = $(".session-checkbox:checked").length;
      $("#select-all").prop("checked", total === checked);
    });

    // Session Modal Functions
    function openSessionModal(sessionId) {
      // Show loading state
      showSessionModal();
      $("#session-modal-content").html(
        '<div class="loading-spinner">Loading session details...</div>'
      );

      // Fetch session data
      $.ajax({
        url: volunteerAdmin.ajax_url,
        type: "post",
        data: {
          action: "make_volunteer_admin_action",
          admin_action: "get_session_details",
          session_id: sessionId,
          nonce: adminNonce,
        },
        success: function (response) {
          if (response.success) {
            renderSessionModal(response.data);
          } else {
            $("#session-modal-content").html(
              '<div class="error-message">Error loading session: ' +
                response.data +
                "</div>"
            );
          }
        },
        error: function () {
          $("#session-modal-content").html(
            '<div class="error-message">Error loading session details. Please try again.</div>'
          );
        },
      });
    }

    function showSessionModal() {
      if ($("#session-modal").length === 0) {
        createSessionModal();
      }
      $("#session-modal").show();
      $("body").addClass("modal-open");
    }

    function hideSessionModal() {
      $("#session-modal").hide();
      $("body").removeClass("modal-open");
    }

    function createSessionModal() {
      var modalHtml = `
        <div id="session-modal" class="volunteer-modal">
          <div class="modal-backdrop"></div>
          <div class="modal-container">
            <div class="modal-header">
              <h2>Volunteer Session Details</h2>
              <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body">
              <div id="session-modal-content"></div>
            </div>
          </div>
        </div>
      `;
      $("body").append(modalHtml);
    }

    function renderSessionModal(sessionData) {
      var html = `
        <div class="session-details">
          <div class="session-header">
            <div class="volunteer-info">
              <h3>${sessionData.volunteer_name}</h3>
              <p class="volunteer-email">${sessionData.volunteer_email}</p>
            </div>
            <div class="session-status">
              <span class="status-badge status-${sessionData.status}">${
        sessionData.status_label
      }</span>
            </div>
          </div>

          <div class="session-times">
            <div class="time-field">
              <label>Sign In Time:</label>
              <input type="datetime-local" id="signin-time" value="${
                sessionData.signin_time_input
              }" ${sessionData.status === "completed" ? "" : "readonly"}>
            </div>
            <div class="time-field">
              <label>Sign Out Time:</label>
              <input type="datetime-local" id="signout-time" value="${
                sessionData.signout_time_input
              }" ${sessionData.status === "active" ? "readonly" : ""}>
            </div>
            <div class="duration-display">
              <strong>Duration:</strong> <span id="duration-display">${
                sessionData.duration_display
              }</span>
            </div>
          </div>

          <div class="session-tasks">
            <h4>Tasks Completed:</h4>
            <div class="tasks-list">
              ${sessionData.tasks_html}
            </div>
          </div>

          <div class="session-notes">
            <h4>Session Notes:</h4>
            <textarea id="session-notes" rows="4">${
              sessionData.notes
            }</textarea>
          </div>

          <div class="session-actions">
            ${
              sessionData.status === "active"
                ? '<button class="button button-primary end-session-modal" data-session="' +
                  sessionData.id +
                  '">End Session Now</button>'
                : '<button class="button button-primary save-session-changes" data-session="' +
                  sessionData.id +
                  '">Save Changes</button>'
            }
            <button class="button button-secondary close-modal">Cancel</button>
          </div>
        </div>
      `;

      $("#session-modal-content").html(html);

      // Update duration when times change
      $("#signin-time, #signout-time").on("change", updateDurationDisplay);
    }

    function updateDurationDisplay() {
      var signinTime = $("#signin-time").val();
      var signoutTime = $("#signout-time").val();

      if (signinTime && signoutTime) {
        var signin = new Date(signinTime);
        var signout = new Date(signoutTime);
        var diffMs = signout - signin;

        if (diffMs > 0) {
          var hours = Math.floor(diffMs / (1000 * 60 * 60));
          var minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
          $("#duration-display").text(hours + "h " + minutes + "m");
        } else {
          $("#duration-display").text("Invalid time range");
        }
      }
    }

    // Modal event handlers
    $(document).on(
      "click",
      ".modal-close, .close-modal, .modal-backdrop",
      function () {
        hideSessionModal();
      }
    );

    $(document).on("click", ".end-session-modal", function () {
      var sessionId = $(this).data("session");
      var notes = $("#session-notes").val();

      if (!confirm("Are you sure you want to end this volunteer session?")) {
        return;
      }

      $.ajax({
        url: volunteerAdmin.ajax_url,
        type: "post",
        data: {
          action: "make_volunteer_admin_action",
          admin_action: "end_session",
          session_id: sessionId,
          notes: notes,
          nonce: adminNonce,
        },
        success: function (response) {
          if (response.success) {
            hideSessionModal();
            showNotice("Session ended successfully", "success");
            location.reload(); // Refresh to show updated data
          } else {
            showNotice("Error ending session: " + response.data, "error");
          }
        },
        error: function () {
          showNotice("Error ending session. Please try again.", "error");
        },
      });
    });

    $(document).on("click", ".save-session-changes", function () {
      var sessionId = $(this).data("session");
      var signinTime = $("#signin-time").val();
      var signoutTime = $("#signout-time").val();
      var notes = $("#session-notes").val();

      if (!signinTime || !signoutTime) {
        showNotice("Please provide both sign-in and sign-out times", "error");
        return;
      }

      var signin = new Date(signinTime);
      var signout = new Date(signoutTime);

      if (signout <= signin) {
        showNotice("Sign-out time must be after sign-in time", "error");
        return;
      }

      $.ajax({
        url: volunteerAdmin.ajax_url,
        type: "post",
        data: {
          action: "make_volunteer_admin_action",
          admin_action: "update_session",
          session_id: sessionId,
          signin_time: signinTime,
          signout_time: signoutTime,
          notes: notes,
          nonce: adminNonce,
        },
        beforeSend: function () {
          $(".save-session-changes").prop("disabled", true).text("Saving...");
        },
        success: function (response) {
          if (response.success) {
            hideSessionModal();
            showNotice("Session updated successfully", "success");
            location.reload(); // Refresh to show updated data
          } else {
            showNotice("Error updating session: " + response.data, "error");
            $(".save-session-changes")
              .prop("disabled", false)
              .text("Save Changes");
          }
        },
        error: function () {
          showNotice("Error updating session. Please try again.", "error");
          $(".save-session-changes")
            .prop("disabled", false)
            .text("Save Changes");
        },
      });
    });

    // Prevent modal from closing when clicking inside the modal container
    $(document).on("click", ".modal-container", function (e) {
      e.stopPropagation();
    });
  });
})(this, jQuery);
