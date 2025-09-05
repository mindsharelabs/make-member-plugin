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

    // Search functionality disabled on settings/tables

    // Scope session checkboxes to session tables only (not volunteers list)
    var $sessionTables = $(".volunteer-admin table").filter(function(){
      return $(this).find('.end-session, .view-session').length > 0;
    });
    $sessionTables.find('thead tr').prepend('<th><input type="checkbox" id="select-all"></th>');
    $sessionTables.find('tbody tr').each(function(){
      var sessionId = $(this).find('.end-session, .view-session').first().data('session');
      if (sessionId) {
        $(this).prepend('<td><input type="checkbox" class="session-checkbox" value="'+sessionId+'"></td>');
      }
    });

    // Select all functionality
    $(document).on("change", "#select-all", function () {
      $(".session-checkbox").prop("checked", $(this).prop("checked"));
    });

    // Benefits review modal
    function ensureBenefitsModal() {
      if ($('#makesf-modal').length) return;
      var modal = ''+
      '<div id="makesf-modal" class="makesf-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="makesf-modal-title">'+
        '<div class="makesf-modal-backdrop"></div>'+
        '<div class="makesf-modal-dialog">'+
          '<div class="makesf-modal-header">'+
            '<h2 id="makesf-modal-title">Volunteer Month Review</h2>'+
            '<button type="button" class="button-link makesf-modal-close" aria-label="Close">×</button>'+
          '</div>'+
          '<div class="makesf-modal-body"></div>'+
          '<div class="makesf-modal-footer">'+
            '<button type="button" class="button button-primary" id="benefits-save-status">Save</button>'+
            '<button type="button" class="button makesf-modal-close">Close</button>'+
          '</div>'+
        '</div>'+
      '</div>';
      $('body').append(modal);
      $('#makesf-modal').on('click', '.makesf-modal-close, .makesf-modal-backdrop', function(){ $('#makesf-modal').hide(); });
    }

    function openReviewModal(userId, month) {
      ensureBenefitsModal();
      var $m = $('#makesf-modal');
      var row = $('tr[data-user-id="' + userId + '"]');
      var name = row.find('.column-name a').text() || 'Volunteer';
      var monthLabel = (function(){
        var parts = (month || '').split('-');
        if (parts.length === 2) {
          var y = parseInt(parts[0],10), m = parseInt(parts[1],10)-1;
          var d = new Date(y, m, 1);
          return d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
        }
        return month;
      })();
      $m.find('#makesf-modal-title').text('Benefits Review — ' + name + ' (' + monthLabel + ')');
      $m.find('.makesf-modal-body').html('<p class="loading-spinner">Loading…</p>');
      $m.show();
      $.post(volunteerAdmin.ajax_url, { action: 'make_volunteer_admin_action', admin_action: 'get_month_sessions', nonce: adminNonce, user_id: userId, month: month }, function(res){
        if (!res || !res.success) { $m.find('.makesf-modal-body').html('<p>Error loading data.</p>'); return; }
        var d = res.data; var html = '';
        // Summary grid
        html += '<div class="review-grid">';
        html += '  <div class="summary-card"><div class="summary-title">Total Hours</div><div class="summary-value">'+d.total_hours+'</div></div>';
        html += '  <div class="summary-card"><div class="summary-title">Target</div><div class="summary-value">'+d.target_hours+'h</div></div>';
        html += '  <div class="summary-card"><div class="summary-title">Status</div><div class="summary-value"><span class="badge benefits-status status-'+d.status+'">'+(d.status.charAt(0).toUpperCase()+d.status.slice(1))+'</span></div></div>';
        html += '  <div class="summary-card"><div class="summary-title">Target Check</div><div class="summary-value">'+(d.meets_target?'<span class="badge meets-target">Meets target</span>':'<span class="badge below-target">Below target</span>')+'</div></div>';
        html += '</div>';
        // Status picker
        html += '<div class="status-picker"><label class="status-label">Set Benefits Status:</label>'+
                '<div class="status-options">'+
                  '<label><input type="radio" name="benefits-status" value="approved" '+(d.status==='approved'?'checked':'')+'> Approved</label>'+
                  '<label><input type="radio" name="benefits-status" value="denied" '+(d.status==='denied'?'checked':'')+'> Denied</label>'+
                  '<label><input type="radio" name="benefits-status" value="pending" '+(d.status==='pending'?'checked':'')+'> Pending</label>'+
                '</div></div>';
        // Sessions table
        html += '<div class="sessions-table-wrap">';
        html += '<table class="widefat fixed"><thead><tr><th>Sign In</th><th>Sign Out</th><th style="width:110px;">Hours</th></tr></thead><tbody>';
        d.sessions.forEach(function(s){
          var sin = formatReadableDateTime(s.signin);
          var sout = s.signout ? formatReadableDateTime(s.signout) : '';
          var hours = (s.minutes && !isNaN(s.minutes)) ? (Math.round((s.minutes/60) * 100) / 100).toFixed(2) : '0.00';
          html += '<tr><td>'+sin+'</td><td>'+sout+'</td><td>'+hours+'</td></tr>';
        });
        html += '</tbody></table></div>';
        $m.find('.makesf-modal-body').html(html);
        $m.data('user', userId).data('month', month);
      });
    }

    $(document).on('click', '.benefits-review', function(e){
      e.preventDefault();
      openReviewModal($(this).data('user'), $(this).data('month'));
    });

    // Save status from modal
    $(document).on('click', '#benefits-save-status', function(){
      var $m = $('#makesf-modal');
      var uid = $m.data('user');
      var ym = $m.data('month');
      var status = $m.find('input[name="benefits-status"]:checked').val();
      if (!status) { showNotice('Pick a status first.', 'error'); return; }
      var $btn = $(this);
      $.post(volunteerAdmin.ajax_url, { action: 'make_volunteer_admin_action', admin_action: 'set_benefits_status', nonce: adminNonce, user_id: uid, month: ym, status: status }, function(res){
        if (res && res.success) {
          // Update table badge
          var $row = $('tr[data-user-id="' + uid + '"]');
          var $badge = $row.find('.benefits-status');
          var label = status.charAt(0).toUpperCase() + status.slice(1);
          if ($badge.length) { $badge.removeClass('status-pending status-denied status-approved').addClass('status-'+status).text(label); }
          // Update summary badge in modal
          var $modalBadge = $m.find('.summary-card .benefits-status');
          if ($modalBadge.length) { $modalBadge.removeClass('status-pending status-denied status-approved').addClass('status-'+status).text(label); }
          var msg = (res.data && res.data.message) ? res.data.message : 'Saved benefits status.';
          // Update membership columns if present
          if (res.data && res.data.membership) {
            var ms = res.data.membership;
            $row.find('.column-membership').text(ms.status_label || '—');
            $row.find('.column-expires').text(ms.end_label || '—');
          }
          showNotice(msg, 'success');
          $m.hide();
        } else {
          showNotice('Error saving status: ' + (res && res.data ? res.data : ''), 'error');
        }
      }).always(function(){ $btn.prop('disabled', false); });
    });

    // Bulk Approve/Deny for volunteers benefits
    function updateRowBenefitsBadge(userIds, status) {
      var label = status.charAt(0).toUpperCase() + status.slice(1);
      userIds.forEach(function(uid){
        var $row = $('tr[data-user-id="' + uid + '"]');
        var $badge = $row.find('.benefits-status');
        if ($badge.length) {
          $badge.removeClass('status-pending status-denied status-approved').addClass('status-' + status).text(label);
        }
      });
    }

    function updateBulkButtonsState() {
      var hasSelection = $('.benefits-user-checkbox:checked').length > 0;
      $('#benefits-approve-selected, #benefits-deny-selected, #benefits-approve-if-meets').prop('disabled', !hasSelection);
    }

    $(document).on('change', '#benefits-select-all', function(){
      $('.benefits-user-checkbox').prop('checked', $(this).prop('checked'));
      updateBulkButtonsState();
    });

    $(document).on('change', '.benefits-user-checkbox', function(){
      var total = $('.benefits-user-checkbox').length;
      var checked = $('.benefits-user-checkbox:checked').length;
      $('#benefits-select-all').prop('checked', total > 0 && total === checked);
      updateBulkButtonsState();
    });

    function handleBulkBenefits(status) {
      var ids = $('.benefits-user-checkbox:checked').map(function(){ return $(this).val(); }).get();
      if (ids.length === 0) { showNotice('Select at least one volunteer.', 'error'); return; }
      var ym = $('#volunteer_month').val() || '';
      var $btnApprove = $('#benefits-approve-selected');
      var $btnDeny = $('#benefits-deny-selected');
      var $buttons = $btnApprove.add($btnDeny);
      $.ajax({
        url: volunteerAdmin.ajax_url,
        type: 'post',
        data: {
          action: 'make_volunteer_admin_action',
          admin_action: 'bulk_set_benefits_status',
          nonce: adminNonce,
          user_ids: ids,
          month: ym,
          status: status
        },
        beforeSend: function(){ $buttons.prop('disabled', true); },
        success: function(res){
          if (res && res.success) {
            var updated = res.data.updated || ids;
            updateRowBenefitsBadge(updated, status);
            var msg = (res.data && res.data.message) ? res.data.message : ('Updated ' + updated.length + ' volunteer(s).');
            showNotice(msg, 'success');
          } else {
            showNotice('Error updating benefits status.', 'error');
          }
        },
        error: function(){ showNotice('Error updating benefits status.', 'error'); },
        complete: function(){ $buttons.prop('disabled', false); }
      });
    }

    $(document).on('click', '#benefits-approve-selected', function(){ handleBulkBenefits('approved'); });
    $(document).on('click', '#benefits-deny-selected', function(){ handleBulkBenefits('denied'); });
    $(document).on('click', '#benefits-approve-if-meets', function(){
      var ids = $('.benefits-user-checkbox:checked').map(function(){ return $(this).val(); }).get();
      if (ids.length === 0) { showNotice('Select at least one volunteer.', 'error'); return; }
      // Filter to those rows that meet target
      var filtered = ids.filter(function(uid){
        var $row = $('tr[data-user-id="' + uid + '"]');
        return $row.find('.badge.meets-target').length > 0;
      });
      if (filtered.length === 0) { showNotice('No selected volunteers meet the target.', 'error'); return; }
      var ym = $('#volunteer_month').val() || '';
      var $btns = $('#benefits-approve-selected, #benefits-deny-selected, #benefits-approve-if-meets').prop('disabled', true);
      $.ajax({
        url: volunteerAdmin.ajax_url,
        type: 'post',
        data: {
          action: 'make_volunteer_admin_action',
          admin_action: 'bulk_set_benefits_status',
          nonce: adminNonce,
          user_ids: filtered,
          month: ym,
          status: 'approved'
        },
        success: function(res){
          if (res && res.success) {
            var updated = res.data.updated || filtered;
            updateRowBenefitsBadge(updated, 'approved');
            var msg = (res.data && res.data.message) ? res.data.message : ('Approved ' + updated.length + ' who meet target.');
            showNotice(msg, 'success');
          } else {
            showNotice('Error updating benefits status.', 'error');
          }
        },
        error: function(){ showNotice('Error updating benefits status.', 'error'); },
        complete: function(){ $btns.prop('disabled', false); }
      });
    });

    // Initialize bulk buttons disabled state on load
    updateBulkButtonsState();

    // Helpers
    function formatReadableDateTime(str){
      if (!str) return '';
      // Normalize space to 'T' for better parsing across browsers
      var normalized = String(str).replace(' ', 'T');
      var d = new Date(normalized);
      if (isNaN(d.getTime())) {
        // Fallback: try without 'T'
        d = new Date(str);
      }
      if (isNaN(d.getTime())) return str; // give up, show raw
      // Round to nearest minute
      var secs = d.getSeconds();
      if (secs >= 30) {
        d.setMinutes(d.getMinutes() + 1);
      }
      d.setSeconds(0);
      d.setMilliseconds(0);
      try {
        return d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
      } catch(e) {
        // Basic manual format as ultimate fallback
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var h = d.getHours(); var ampm = h >= 12 ? 'PM' : 'AM'; h = h % 12; h = h ? h : 12;
        var m = d.getMinutes(); if (m < 10) m = '0' + m;
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() + ', ' + h + ':' + m + ' ' + ampm;
      }
    }

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
