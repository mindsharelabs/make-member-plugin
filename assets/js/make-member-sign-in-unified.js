/**
 * Unified Member Sign-In JavaScript
 *
 * This consolidated implementation replaces the three separate sign-in files:
 * - make-member-sign-in.js (original)
 * - make-member-sign-in-hybrid.js (hybrid optimization)
 * - make-member-sign-in-optimized.js (full optimization)
 *
 * Features:
 * - Configurable loading strategy (full list vs search-based)
 * - Performance monitoring and optimization
 * - Consistent error handling
 * - Enhanced user experience with keyboard shortcuts
 * - Proper volunteer system integration
 *
 * @version 1.4.1
 * @author Make Santa Fe
 */

(function (root, $, undefined) {
  "use strict";

  // Configuration object - can be overridden via makeMember.config
  var config = {
    // Loading strategy: 'full' (load all members), 'search' (search-based), 'hybrid' (cached with search)
    loadingStrategy: "hybrid",

    // Search settings
    searchDebounceMs: 300,
    minSearchLength: 2,
    // Minimum characters before client-side filtering runs in hybrid/full
    minClientSearchLength: 2,
    maxSearchResults: 20,

    // UI settings
    autoReturnDelay: 15000,
    errorDisplayDelay: 5000,

    // Performance monitoring
    enablePerformanceLogging: true,

    // Volunteer integration
    enableVolunteerIntegration: true,
  };

  // Merge with global config if available
  if (typeof makeMember !== "undefined" && makeMember.config) {
    config = $.extend(config, makeMember.config);
  }

  // Store nonces for AJAX requests
  var nonces = {
    volunteer:
      typeof makeMember !== "undefined" && makeMember.volunteer_nonce
        ? makeMember.volunteer_nonce
        : "",
    signin:
      typeof makeMember !== "undefined" && makeMember.signin_nonce
        ? makeMember.signin_nonce
        : "",
  };

  $(function () {
    var metaContainer = $("#result");
    var memberContainer = $("#memberList");
    var searchTimeout;
    var memberList; // List.js instance for full/hybrid modes
    var currentSearchTerm = "";
    var isSearching = false;

    // Heading helper (available to all handlers)
    function setHeading(text) {
      var $h = $("#makesf-signin-heading");
      if (!$h.length) {
        $h = $("#MAKEMemberSignIn h1").first();
      }
      if (!$h.length) return;
      // Prefer updating existing <strong> to preserve styling
      var $strong = $h.find('strong').first();
      if ($strong.length) {
        $strong.text(text);
      } else {
        // Fallback: wrap in strong to keep weight consistent
        $h.html('<strong>' + text + '</strong>');
      }
    }

    // Offline helpers
    function isOffline() {
      return typeof navigator !== "undefined" && navigator && navigator.onLine === false;
    }
    function ensureOfflineBanner() {
      if ($("#makesf-offline-banner").length) return;
      var banner =
        '<div id="makesf-offline-banner" style="display:none;position:fixed;top:0;left:0;right:0;z-index:10000;background:#fff3cd;border-bottom:1px solid #ffeeba;color:#856404;padding:.6rem 1rem;text-align:center;font-weight:600;">' +
        '<span>No internet connection. Check Wi‑Fi and try again.</span>' +
        "</div>";
      $("body").append(banner);
    }
    function showOfflineBanner() { ensureOfflineBanner(); $("#makesf-offline-banner").stop(true,true).fadeIn(150); }
    function hideOfflineBanner() { $("#makesf-offline-banner").stop(true,true).fadeOut(150); }
    function showOfflineMessage($target) {
      showOfflineBanner();
      var html =
        '<div class="alert alert-warning text-center" style="max-width:720px;margin:2rem auto;">' +
        '<h3 style="margin-bottom:.5rem;">You appear to be offline</h3>' +
        '<div>Reconnect to Wi‑Fi to continue. This screen will update once you are back online.</div>' +
        '<div class="mt-3"><button class="btn btn-secondary" id="makesf-retry-btn" type="button">Retry</button></div>' +
        "</div>";
      $target.html(html);
      $(document).off("click.makesfRetry").on("click.makesfRetry", "#makesf-retry-btn", function(){ if(!isOffline()){ hideOfflineBanner(); returnToInterface(); } });
    }

    window.addEventListener("offline", function(){ showOfflineBanner(); });
    window.addEventListener("online", function(){
      hideOfflineBanner();
      if (metaContainer.children().length > 0) { returnToInterface(); }
      if (memberContainer.length && $("#member-list").is(":visible")) {
        $.ajax({ url: makeMember.ajax_url, type: "post", data: { action: "makeAllGetMembersOptimized" } })
          .done(function(res){ if(res && res.success && res.data && res.data.html){ memberContainer.html(res.data.html); initializeListJS(); enhanceSearchInterface(); } });
      }
    });

    // Performance monitoring utility
    var performanceLog = {
      timers: {},

      start: function (operation) {
        if (config.enablePerformanceLogging) {
          this.timers[operation] = performance.now();
        }
      },

      end: function (operation, additionalInfo) {
        if (config.enablePerformanceLogging && this.timers[operation]) {
          var duration = performance.now() - this.timers[operation];
          var message =
            operation + " completed in " + Math.round(duration) + "ms";
          if (additionalInfo) {
            message += " (" + additionalInfo + ")";
          }
          console.log(message);
          delete this.timers[operation];
        }
      },
    };

    // Initialize based on loading strategy
    $(document).ready(function () {
      // Only initialize if the member sign-in block is present on the page
      if (metaContainer.length > 0 || memberContainer.length > 0) {
        initializeSignInInterface();
        // Kiosk enhancements
        startActiveVolunteersPolling();
        startNonceRotation();
        startBackgroundMemberRefresh();
        setupInactivityAutoReset();
      } else {
        if (config.enablePerformanceLogging) {
          console.log(
            "Member sign-in block not found on this page, skipping initialization"
          );
        }
      }
    });

    /**
     * Initialize the appropriate sign-in interface based on configuration
     */
    function initializeSignInInterface() {
      // Double-check that required elements exist before proceeding
      if (metaContainer.length === 0 && memberContainer.length === 0) {
        if (config.enablePerformanceLogging) {
          console.log(
            "Required DOM elements not found, aborting initialization"
          );
        }
        return;
      }

      switch (config.loadingStrategy) {
        case "full":
          initializeFullListMode();
          break;
        case "search":
          initializeSearchMode();
          break;
        case "hybrid":
        default:
          initializeHybridMode();
          break;
      }
    }

    /**
     * Initialize full list mode (original behavior)
     */
    function initializeFullListMode() {
      // Ensure memberContainer exists before making AJAX call
      if (memberContainer.length === 0) {
        if (config.enablePerformanceLogging) {
          console.log(
            "memberContainer not found, skipping full list mode initialization"
          );
        }
        return;
      }

      performanceLog.start("fullListLoad");

      $.ajax({
        url: makeMember.ajax_url,
        type: "post",
        data: {
          action: "makeAllGetMembers",
        },
        beforeSend: function () {
          showLoadingState("Loading members...");
        },
        success: function (response) {
          hideLoadingState();
          performanceLog.end(
            "fullListLoad",
            response.data.member_count + " members"
          );

          if (response.success) {
            memberContainer.html(response.data.html);
            initializeListJS();
            console.log(
              "Full list mode: Loaded " +
                response.data.member_count +
                " members"
            );
          } else {
            showError("Error loading members. Please refresh the page.");
          }
        },
        error: function (xhr, status, error) {
          hideLoadingState();
          performanceLog.end("fullListLoad", "error");
          console.error("Full list load error:", error);
          showError("Error loading members. Please refresh the page.");
        },
      });
    }

    /**
     * Initialize hybrid mode (cached loading with enhanced search)
     */
    function initializeHybridMode() {
      // Ensure memberContainer exists before making AJAX call
      if (memberContainer.length === 0) {
        if (config.enablePerformanceLogging) {
          console.log(
            "memberContainer not found, skipping hybrid mode initialization"
          );
        }
        return;
      }

      performanceLog.start("hybridLoad");

      // Try optimized endpoint first, fallback to original
      var endpoint = "makeAllGetMembersOptimized";

      $.ajax({
        url: makeMember.ajax_url,
        type: "post",
        data: {
          action: endpoint,
        },
        beforeSend: function () {
          showLoadingState("Loading members...");
        },
        success: function (response) {
          hideLoadingState();
          performanceLog.end("hybridLoad", response.data.count + " members");

          if (response.success) {
            memberContainer.html(response.data.html);
            initializeListJS();
            enhanceSearchInterface();
            console.log(
              "Hybrid mode: Loaded " +
                response.data.count +
                " members with caching"
            );
          } else {
            // Fallback to original endpoint
            initializeFullListMode();
          }
        },
        error: function (xhr, status, error) {
          hideLoadingState();
          performanceLog.end("hybridLoad", "error - falling back");
          console.warn("Hybrid load failed, falling back to full list mode");
          initializeFullListMode();
        },
      });
    }

    /**
     * Initialize search-only mode (no upfront loading)
     */
    function initializeSearchMode() {
      // Ensure memberContainer exists before proceeding
      if (memberContainer.length === 0) {
        if (config.enablePerformanceLogging) {
          console.log(
            "memberContainer not found, skipping search mode initialization"
          );
        }
        return;
      }

      var searchHtml = buildSearchInterface();
      memberContainer.html(searchHtml);
      bindSearchEvents();
      $("#memberSearchInput").focus();
      console.log("Search mode: Ready for member search");
    }

    /**
     * Build the search interface HTML
     */
    function buildSearchInterface() {
      var html = '<div id="member-search-container">';
      html += '<div class="search-container w-50 mb-4 mx-auto">';
      html += '<div class="input-group input-group-lg">';
      html +=
        '<span class="input-group-text"><i class="fas fa-search"></i></span>';
      html +=
        '<input id="memberSearchInput" type="text" class="form-control" placeholder="Search members by name or email..." autocomplete="off" />';
      html +=
        '<button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" style="display:none;" title="Clear search"><i class="fas fa-times"></i></button>';
      html += "</div>";
      html +=
        '<div class="search-help text-muted text-center mt-2 small">Type at least ' +
        config.minSearchLength +
        " characters to search</div>";
      html += "</div>";
      html += '<div id="search-results"></div>';
      html += "</div>";
      return html;
    }

    /**
     * Initialize List.js for client-side search (full/hybrid modes)
     */
    function initializeListJS() {
      if ($("#member-list").length > 0) {
        memberList = new List("member-list", {
          valueNames: ["email", "name"],
          searchClass: "member-search",
        });

        // Make globally available for volunteer.js integration
        if (config.enableVolunteerIntegration) {
          window.memberList = memberList;
        }

        // Prewarm client-side search to avoid first-keystroke lag
        setTimeout(function prewarmClientSearch() {
          try {
            if (memberList) {
              memberList.search("\uFFFF"); // unlikely to match anything
              memberList.search(""); // clear quickly
            }
          } catch (e) {
            // no-op
          }
        }, 50);
      }
    }

    /**
     * Enhance search interface for hybrid mode
     */
    function enhanceSearchInterface() {
      var $searchInput = $("#memberSearch");

      if ($searchInput.length === 0) {
        console.warn("Search input not found for enhancement");
        return;
      }

      // Enhance styling if not already done
      if (!$searchInput.parent().hasClass("input-group")) {
        $searchInput.wrap('<div class="input-group input-group-lg"></div>');
        $searchInput.before(
          '<span class="input-group-text"><i class="fas fa-search"></i></span>'
        );
        $searchInput.after(
          '<button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" style="display:none;" title="Clear search"><i class="fas fa-times"></i></button>'
        );
      }

      bindEnhancedSearchEvents($searchInput);
    }

    /**
     * Bind enhanced search events for hybrid mode
     */
    function bindEnhancedSearchEvents($searchInput) {
      var $clearButton = $("#clearSearchBtn");

      $searchInput.off("input.unified").on("input.unified", function () {
        var searchTerm = $(this).val().trim();

        if (searchTerm.length > 0) {
          $clearButton.show();
        } else {
          $clearButton.hide();
        }

        if (searchTimeout) {
          clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(function () {
          var minLen = config.minClientSearchLength || config.minSearchLength || 2;
          if (searchTerm.length === 0) {
            performClientSideSearch("");
            return;
          }
          if (searchTerm.length < minLen) {
            // Avoid heavy DOM updates for single-character searches on large lists
            return;
          }
          performClientSideSearch(searchTerm);
        }, config.searchDebounceMs);
      });

      $clearButton.on("click", function () {
        $searchInput.val("").focus();
        $clearButton.hide();
        if (memberList) {
          memberList.search("");
          updateSearchFeedback(
            "",
            memberList.items.length,
            memberList.items.length
          );
        }
      });

      $searchInput.on("keypress.unified", function (e) {
        if (e.which === 13) {
          // Enter key
          e.preventDefault();
          var searchTerm = $(this).val().trim();
          if (searchTimeout) {
            clearTimeout(searchTimeout);
          }
          performClientSideSearch(searchTerm);
        }
      });
    }

    /**
     * Bind search events for search-only mode
     */
    function bindSearchEvents() {
      var $searchInput = $("#memberSearchInput");
      var $clearButton = $("#clearSearchBtn");
      var $searchResults = $("#search-results");

      $searchInput.on("input", function () {
        var searchTerm = $(this).val().trim();

        if (searchTerm.length > 0) {
          $clearButton.show();
        } else {
          $clearButton.hide();
          $searchResults.empty();
          return;
        }

        if (searchTimeout) {
          clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(function () {
          performServerSideSearch(searchTerm);
        }, config.searchDebounceMs);
      });

      $clearButton.on("click", function () {
        $searchInput.val("").focus();
        $clearButton.hide();
        $searchResults.empty();
        currentSearchTerm = "";
      });

      $searchInput.on("keypress", function (e) {
        if (e.which === 13) {
          // Enter key
          var searchTerm = $(this).val().trim();
          if (searchTerm.length >= config.minSearchLength) {
            if (searchTimeout) {
              clearTimeout(searchTimeout);
            }
            performServerSideSearch(searchTerm);
          }
        }
      });
    }

    /**
     * Perform client-side search (hybrid/full modes)
     */
    function performClientSideSearch(searchTerm) {
      if (!memberList) return;

      performanceLog.start("clientSearch");
      var minLen = config.minClientSearchLength || config.minSearchLength || 2;
      if (searchTerm !== "" && searchTerm.length < minLen) {
        performanceLog.end("clientSearch", "skipped < minLen");
        return;
      }
      memberList.search(searchTerm);
      var visibleCount = memberList.visibleItems.length;
      performanceLog.end("clientSearch", visibleCount + " results");
    }

    /**
     * Perform server-side search (search-only mode)
     */
    function performServerSideSearch(searchTerm) {
      if (searchTerm.length < config.minSearchLength) {
        $("#search-results").html(
          '<div class="text-center text-muted p-4">Type at least ' +
            config.minSearchLength +
            " characters to search...</div>"
        );
        return;
      }

      if (searchTerm === currentSearchTerm && !isSearching) {
        return; // Same search term, no need to search again
      }

      currentSearchTerm = searchTerm;
      isSearching = true;

      performanceLog.start("serverSearch");

      var $searchResults = $("#search-results");
      $searchResults.html(
        '<div class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Searching...</div>'
      );

      $.ajax({
        url: makeMember.ajax_url,
        type: "post",
        data: {
          action: "makeMemberSearch",
          search: searchTerm,
          limit: config.maxSearchResults,
        },
        success: function (response) {
          isSearching = false;
          performanceLog.end("serverSearch", response.data.count + " results");

          if (response.success) {
            $searchResults.html(response.data.html);
          } else {
            $searchResults.html(
              '<div class="alert alert-danger">Search failed. Please try again.</div>'
            );
          }
        },
        error: function (xhr, status, error) {
          isSearching = false;
          performanceLog.end("serverSearch", "error");
          console.error("Search error:", error);
          if (isOffline()) {
            $searchResults.html('<div class="alert alert-warning text-center">Offline — reconnect to search.</div>');
          } else {
            $searchResults.html('<div class="alert alert-danger">Search error. Please try again.</div>');
          }
        },
      });
    }

    /**
     * Update search feedback text
     */
    function updateSearchFeedback(searchTerm, visibleCount, totalCount) {
      var $feedback = $(".search-feedback");

      if (searchTerm === "") {
        $feedback.text("");
      } else if (visibleCount === 0) {
        $feedback.text('No members found matching "' + searchTerm + '"');
      } else if (visibleCount === 1) {
        $feedback.text('Found 1 member matching "' + searchTerm + '"');
      } else {
        $feedback.text(
          "Found " + visibleCount + ' members matching "' + searchTerm + '"'
        );
      }
    }

    /**
     * Handle member selection
     */
    $(document).on("click", ".profile-card", function () {
      var userID = $(this).data("user");
      var isPreloaded = $(this).data("preloaded");

      performanceLog.start("memberLoad");

      if (isPreloaded && config.loadingStrategy !== "full") {
        submitUserOptimized(userID, true);
      } else {
        submitUser(userID);
      }
    });

    /**
     * Optimized user submission
     */
    function submitUserOptimized(userID, preloaded = false) {
      $.ajax({
        url: makeMember.ajax_url,
        type: "post",
        data: {
          action: "makeGetMemberOptimized",
          userID: userID,
          preloaded: preloaded,
        },
        beforeSend: function () {
          hideSearchInterface();
          showLoadingState("Loading member...");
        },
        success: function (response) {
          hideLoadingState();
          performanceLog.end("memberLoad");
          handleMemberResponse(response);
        },
        error: function (xhr, status, error) {
          hideLoadingState();
          performanceLog.end("memberLoad", "error");
          if (isOffline()) {
            showOfflineMessage(metaContainer);
          } else {
            handleMemberError(error);
          }
        },
      });
    }

    /**
     * Standard user submission
     */
    function submitUser(userID = false, userEmail = false) {
      $.ajax({
        url: makeMember.ajax_url,
        type: "post",
        data: {
          action: "makeGetMember",
          userID: userID,
          userEmail: userEmail,
        },
        beforeSend: function () {
          hideSearchInterface();
          showLoadingState("Loading member...");
        },
        success: function (response) {
          hideLoadingState();
          performanceLog.end("memberLoad");
          handleMemberResponse(response);
        },
        error: function (xhr, status, error) {
          hideLoadingState();
          performanceLog.end("memberLoad", "error");
          if (isOffline()) {
            showOfflineMessage(metaContainer);
          } else {
            handleMemberError(error);
          }
        },
      });
    }

    /**
     * Handle member response
     */
    function handleMemberResponse(response) {
      if (response.success) {
        if (response.data.status === "userfound") {
          metaContainer.html(response.data.html);
          if (response.data.greeting_name) {
            setHeading("Hi " + response.data.greeting_name);
          }
        } else if (response.data.status === "volunteer_signout") {
          // Handle volunteer sign-out interface
          metaContainer.html(response.data.html);
          // Stop any prior volunteer session timer
          try { if (window.makesfVolunteerTimer) { clearInterval(window.makesfVolunteerTimer); window.makesfVolunteerTimer = null; } } catch(e) {}
          if (config.enableVolunteerIntegration) {
            $("body").addClass("volunteer-signout-mode");
          }
          if (response.data.greeting_name) {
            setHeading("See You Later " + response.data.greeting_name);
          }
        } else {
          // Error states (no waiver, no membership, etc.)
          metaContainer.html(response.data.html);

          // Auto-return unless in volunteer mode
          if (!$("body").hasClass("volunteer-signout-mode")) {
            setTimeout(function () {
              returnToInterface();
            }, config.autoReturnDelay);
          }
        }
      } else {
        showError("Error loading member data.");
        setTimeout(function () {
          returnToInterface();
        }, config.errorDisplayDelay);
      }
    }

    /**
     * Handle member loading error
     */
    function handleMemberError(error) {
      console.error("Member load error:", error);
      showError("Error loading member data.");
      setTimeout(function () {
        returnToInterface();
      }, config.errorDisplayDelay);
    }

    /**
     * Badge selection handling
     */
    $(document).on(
      "click",
      ".badge-item:not(.not-allowed), .activity-item",
      function () {
        $(this).toggleClass("selected");

        var selections = $(".selected").length;
        $("button.sign-in-done").prop("disabled", selections === 0);
      }
    );

    /**
     * Sign-in completion
     */
    $(document).on("click", ".sign-in-done", function () {
      var badges = [];
      var userID = $(this).data("user");

      $(".selected").each(function () {
        badges.push($(this).data("badge"));
      });

      $.ajax({
        url: makeMember.ajax_url,
        type: "post",
        data: {
          action: "makeMemberSignIn",
          badges: badges,
          userID: userID,
          _wpnonce: nonces.signin,
          volunteer_nonce: nonces.volunteer,
        },
        beforeSend: function () {
          showLoadingState("Signing in...");
        },
        success: function (response) {
          hideLoadingState();
          console.log("Sign-in response:", response);

          if (response.success) {
            metaContainer.html(response.data.html);

            // Handle volunteer sign-in vs regular sign-in
            if (response.data.status === "volunteer_signin_complete") {
              // Volunteer sign-in - auto-return after 15 seconds
              if (config.enableVolunteerIntegration) {
                $("body").addClass("volunteer-signin-mode");
              }
              // Update volunteer status in member list
              updateVolunteerStatus(userID, true);
              if (response.data.greeting_name) {
                setHeading("Hi " + response.data.greeting_name);
              }
              setTimeout(function () {
                returnToInterface();
              }, config.autoReturnDelay);
            } else {
              // Regular sign-in - auto-return
              if (response.data.greeting_name) {
                setHeading("Hi " + response.data.greeting_name);
              }
              setTimeout(function () {
                returnToInterface();
              }, config.autoReturnDelay);
            }
          } else {
            showError("Sign-in failed. Please try again.");
            setTimeout(function () {
              returnToInterface();
            }, config.errorDisplayDelay);
          }
        },
        error: function (xhr, status, error) {
          hideLoadingState();
          console.error("Sign-in error:", error);
          if (isOffline()) {
            showOfflineMessage(metaContainer);
          } else {
            showError("Sign-in failed. Please try again.");
            setTimeout(function () { returnToInterface(); }, config.errorDisplayDelay);
          }
        },
      });
    });

    /**
     * Utility functions
     */
    function showLoadingState(message = "Loading...") {
      metaContainer.html(
        '<div class="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.9); z-index: 9999; display: flex; align-items: center; justify-content: center;">' +
          '<div class="loading-content text-center" style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);">' +
          '<div><i class="fas fa-spinner fa-spin fa-2x" style="color: #be202e; margin-bottom: 1rem;"></i></div>' +
          '<div style="font-size: 1.1rem; color: #333; font-weight: 500;">' +
          message +
          "</div>" +
          "</div>" +
          "</div>"
      );
    }

    function hideLoadingState() {
      // Only clear if currently showing loading state
      if (metaContainer.find(".loading-overlay").length > 0) {
        metaContainer.html("");
      }
    }

    function showError(message) {
      metaContainer.html(
        '<div class="alert alert-danger text-center">' + message + "</div>"
      );
    }

    function hideSearchInterface() {
      $("#member-list, #search-results").addClass("d-none");
      $("#memberSearch, #memberSearchInput").val("");
      $("#clearSearchBtn").hide();
    }

    function returnToInterface() {
      // Stop any running volunteer session timers
      try { if (window.makesfVolunteerTimer) { clearInterval(window.makesfVolunteerTimer); window.makesfVolunteerTimer = null; } } catch(e) {}
      // Clear any loading states or content
      hideLoadingState();
      metaContainer.html("");
      $("body").removeClass(
        "volunteer-signout-mode volunteer-signin-mode ajax-loading"
      );
      // Reset heading to default
      setHeading("Member Sign In");

      if (config.loadingStrategy === "search") {
        $("#search-results").removeClass("d-none");
        $("#memberSearchInput").focus();
      } else {
        $("#member-list").removeClass("d-none");
        $("#memberSearch").focus();
        if (memberList) { memberList.search(""); }
      }

      currentSearchTerm = "";
    }

    /**
     * Update volunteer status for a specific user in the member list
     */
    function updateVolunteerStatus(userId, isVolunteering) {
      // Find the profile card for this user
      var $profileCard = $('.profile-card[data-user="' + userId + '"]');
      if ($profileCard.length > 0) {
        var $profileImage = $profileCard.find(".profile-image");
        if ($profileImage.length > 0) {
          if (isVolunteering) {
            // Add volunteer class if not already present
            if (!$profileImage.hasClass("volunteer-signed-in")) {
              $profileImage.addClass("volunteer-signed-in");
            }
          } else {
            // Remove volunteer class
            $profileImage.removeClass("volunteer-signed-in");
          }
        }
      }
    }

    /**
     * Poll active volunteers and update glow state (every 45s)
     */
    function startActiveVolunteersPolling() {
      var last = [];
      function tick() {
        $.ajax({
          url: makeMember.ajax_url,
          type: "post",
          data: { action: "makeGetActiveVolunteerIds" },
        })
          .done(function (res) {
            if (!res || !res.success || !res.data) return;
            var ids = res.data.active_user_ids || [];
            // Build lookup for quick checks
            var map = {};
            for (var i = 0; i < ids.length; i++) map[ids[i]] = true;

            // Update currently rendered cards
            $(".profile-card").each(function () {
              var uid = parseInt($(this).data("user"), 10);
              if (!uid) return;
              updateVolunteerStatus(uid, !!map[uid]);
            });
            last = ids;
          })
          .always(function () {
            setTimeout(tick, 45000);
          });
      }
      // Start after initial render
      setTimeout(tick, 5000);
    }

    /**
     * Rolling nonce refresh for long-lived kiosk (every 8 hours)
     */
    function startNonceRotation() {
      function refresh() {
        $.ajax({
          url: makeMember.ajax_url,
          type: "post",
          data: { action: "makeRefreshNonces" },
        })
          .done(function (res) {
            if (res && res.success && res.data) {
              makeMember.volunteer_nonce = res.data.volunteer_nonce || makeMember.volunteer_nonce;
              makeMember.signin_nonce = res.data.signin_nonce || makeMember.signin_nonce;
              // Sync local cache
              nonces.volunteer = makeMember.volunteer_nonce;
              nonces.signin = makeMember.signin_nonce;
              if (config.enablePerformanceLogging) {
                console.log("Nonces refreshed at", res.data.generated_at);
              }
            }
          })
          .always(function () {
            setTimeout(refresh, 8 * 60 * 60 * 1000);
          });
      }
      // First rotation later to avoid burst on load
      setTimeout(refresh, 30 * 60 * 1000);
    }

    /**
     * Background refresh of member list every 15 minutes
     */
    function startBackgroundMemberRefresh() {
      function refreshList() {
        // Only refresh if grid is visible and not in a modal/overlay state
        var inModal = $("body").hasClass("volunteer-signout-mode") || metaContainer.children().length > 0;
        if (config.loadingStrategy !== "search" && !inModal && memberContainer.length) {
          $.ajax({
            url: makeMember.ajax_url,
            type: "post",
            data: { action: "makeAllGetMembersOptimized" },
          })
            .done(function (res) {
              if (res && res.success && res.data && res.data.html) {
                memberContainer.html(res.data.html);
                initializeListJS();
                enhanceSearchInterface();
                if (config.enablePerformanceLogging) {
                  console.log("Background member list refreshed");
                }
              }
            })
            .always(function () {
              setTimeout(refreshList, 15 * 60 * 1000);
            });
        } else {
          setTimeout(refreshList, 15 * 60 * 1000);
        }
      }
      setTimeout(refreshList, 15 * 60 * 1000);
    }

    /**
     * Inactivity auto-reset (60s) to return to ready state
     */
    function setupInactivityAutoReset() {
      var timeoutMs = 60000;
      var timer;
      function resetTimer() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
          // Only auto-reset if not interacting with search
          if (!$("#member-list, #search-results").hasClass("d-none")) return;
          returnToInterface();
        }, timeoutMs);
      }
      $(document).on("click keydown touchstart", resetTimer);
      resetTimer();
    }

    /**
     * Keyboard shortcuts
     */
    $(document).on("keydown", function (e) {
      // ESC key to return to interface
      if (e.keyCode === 27) {
        if (!$("#member-list, #search-results").hasClass("d-none")) {
          returnToInterface();
        }
      }

      // Ctrl/Cmd + F to focus search
      if ((e.ctrlKey || e.metaKey) && e.keyCode === 70) {
        e.preventDefault();
        var $searchInput =
          config.loadingStrategy === "search"
            ? $("#memberSearchInput")
            : $("#memberSearch");
        if ($searchInput.length) {
          $searchInput.focus();
        }
      }
    });

    /**
     * Global AJAX loading states
     */
    $(document)
      .ajaxStart(function () { $("body").addClass("ajax-loading"); })
      .ajaxStop(function () { $("body").removeClass("ajax-loading"); });

    // Expose public API for external integration
    window.MakeSignIn = {
      config: config,
      returnToInterface: returnToInterface,
      performanceLog: performanceLog,
      updateVolunteerStatus: updateVolunteerStatus,
    };
  });
})(this, jQuery);
