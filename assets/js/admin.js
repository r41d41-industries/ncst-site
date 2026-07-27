(function () {
  "use strict";

  var PANEL_COLLAPSED_KEY = "ncst-admin-panel-collapsed";
  var sidenav = document.getElementById("admin-sidenav");
  var panel = document.getElementById("admin-panel");
  var overlay = document.getElementById("admin-overlay");
  var openBtn = document.getElementById("admin-nav-open");
  var closeBtn = document.getElementById("admin-nav-close");
  var panelToggle = document.getElementById("admin-panel-toggle");
  var searchInput = document.getElementById("admin-panel-search");
  var userTrigger = document.getElementById("admin-user-trigger");
  var userMenu = document.getElementById("admin-user-menu");
  var railButtons = document.querySelectorAll("[data-rail-section]");
  var panelSections = document.querySelectorAll("[data-panel-section]");
  var panelTitle = document.getElementById("admin-panel-title");
  var panelAdd = document.getElementById("admin-panel-add");
  var accountOverlay = document.getElementById("admin-account-overlay");
  var accountModal = document.getElementById("admin-account-modal");
  var accountForm = document.getElementById("admin-account-form");
  var accountStatus = document.getElementById("admin-account-status");
  var lastFocus = null;
  var accountLastFocus = null;

  function setNavOpen(open) {
    if (!sidenav) return;
    sidenav.classList.toggle("is-open", open);
    if (overlay) {
      overlay.classList.toggle("is-open", open);
      overlay.hidden = !open;
    }
    document.body.style.overflow = open ? "hidden" : "";
    if (openBtn) {
      openBtn.setAttribute("aria-expanded", open ? "true" : "false");
    }
    if (open) {
      lastFocus = document.activeElement;
      var focusTarget = closeBtn || sidenav.querySelector("a, button");
      if (focusTarget) focusTarget.focus();
    } else if (lastFocus && typeof lastFocus.focus === "function") {
      lastFocus.focus();
      lastFocus = null;
    }
  }

  function isMobileNav() {
    return window.matchMedia("(max-width: 899px)").matches;
  }

  function readPanelCollapsed() {
    try {
      return localStorage.getItem(PANEL_COLLAPSED_KEY) === "1";
    } catch (err) {
      return false;
    }
  }

  function writePanelCollapsed(collapsed) {
    try {
      localStorage.setItem(PANEL_COLLAPSED_KEY, collapsed ? "1" : "0");
    } catch (err) {
      /* ignore quota / private mode */
    }
  }

  function setPanelCollapsed(collapsed, persist) {
    if (!sidenav || !panelToggle) return;
    sidenav.classList.toggle("is-panel-collapsed", collapsed);
    if (panel) {
      panel.setAttribute("aria-hidden", collapsed ? "true" : "false");
      if (collapsed) {
        panel.setAttribute("inert", "");
      } else {
        panel.removeAttribute("inert");
      }
    }
    panelToggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
    panelToggle.setAttribute(
      "aria-label",
      collapsed ? "Expand navigation panel" : "Collapse navigation panel"
    );
    panelToggle.title = collapsed ? "Expand panel" : "Collapse panel";
    if (persist !== false) {
      writePanelCollapsed(collapsed);
    }
  }

  function applyStoredPanelCollapsed() {
    if (isMobileNav()) {
      /* Mobile keeps full rail+panel off-canvas; ignore desktop preference for layout */
      if (sidenav) sidenav.classList.remove("is-panel-collapsed");
      if (panel) {
        panel.setAttribute("aria-hidden", "false");
        panel.removeAttribute("inert");
      }
      if (panelToggle) {
        panelToggle.setAttribute("aria-expanded", "true");
        panelToggle.setAttribute("aria-label", "Collapse navigation panel");
        panelToggle.title = "Collapse panel";
      }
      return;
    }
    setPanelCollapsed(readPanelCollapsed(), false);
  }

  applyStoredPanelCollapsed();
  document.documentElement.classList.remove("admin-panel-collapsed-pending");

  if (panelToggle) {
    panelToggle.addEventListener("click", function () {
      if (isMobileNav()) return;
      var shouldCollapse = panelToggle.getAttribute("aria-expanded") === "true";
      setPanelCollapsed(shouldCollapse, true);
    });
  }

  if (openBtn) {
    openBtn.addEventListener("click", function () {
      setNavOpen(true);
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener("click", function () {
      setNavOpen(false);
    });
  }

  if (overlay) {
    overlay.addEventListener("click", function () {
      setNavOpen(false);
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      if (accountOverlay && !accountOverlay.hidden) {
        closeAccountModal();
        return;
      }
      if (userMenu && userMenu.classList.contains("is-open")) {
        closeUserMenu();
        return;
      }
      if (sidenav && sidenav.classList.contains("is-open") && isMobileNav()) {
        setNavOpen(false);
      }
    }
  });

  window.addEventListener("resize", function () {
    if (!isMobileNav() && sidenav && sidenav.classList.contains("is-open")) {
      setNavOpen(false);
    }
    applyStoredPanelCollapsed();
  });

  function activateSection(section) {
    railButtons.forEach(function (btn) {
      var active = btn.getAttribute("data-rail-section") === section;
      btn.classList.toggle("is-active", active);
      if (active) {
        btn.setAttribute("aria-current", "page");
      } else {
        btn.removeAttribute("aria-current");
      }
    });

    panelSections.forEach(function (panelSection) {
      panelSection.classList.toggle(
        "is-active",
        panelSection.getAttribute("data-panel-section") === section
      );
    });

    if (panelTitle) {
      var labels = {
        posts: "Posts",
        media: "Media",
        reports: "Reports",
        settings: "Settings",
      };
      panelTitle.textContent = labels[section] || "Posts";
    }

    if (panelAdd) {
      var showAdd = section === "posts";
      panelAdd.hidden = !showAdd;
      panelAdd.classList.toggle("is-hidden", !showAdd);
      panelAdd.setAttribute("aria-hidden", showAdd ? "false" : "true");
    }

    if (searchInput) {
      searchInput.value = "";
      searchInput.dispatchEvent(new Event("input"));
      var placeholders = {
        posts: "Search posts…",
        media: "Search media…",
        reports: "Search reports…",
        settings: "Search settings…",
      };
      searchInput.placeholder = placeholders[section] || "Search…";
      searchInput.setAttribute(
        "aria-label",
        section === "posts" ? "Filter posts" : "Filter " + section + " (placeholder)"
      );
    }

    if (!isMobileNav() && sidenav && sidenav.classList.contains("is-panel-collapsed")) {
      setPanelCollapsed(false, true);
    }
  }

  railButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var section = btn.getAttribute("data-rail-section");
      if (!section) return;
      if (section === "media" && window.location.pathname.indexOf("/admin/media") !== 0) {
        window.location.href = "/admin/media/";
        return;
      }
      if (section === "settings" && window.location.pathname.indexOf("/admin/settings") !== 0) {
        /* keep in-panel activation; settings landing is posts page when already there */
      }
      activateSection(section);
      if (isMobileNav() && section !== "posts") {
        /* keep nav open so placeholder content is visible */
      }
    });
  });

  document.querySelectorAll("[data-nav-toggle]").forEach(function (toggle) {
    toggle.addEventListener("click", function () {
      var expanded = toggle.getAttribute("aria-expanded") === "true";
      toggle.setAttribute("aria-expanded", expanded ? "false" : "true");
      var panelId = toggle.getAttribute("aria-controls");
      var sub = panelId ? document.getElementById(panelId) : null;
      if (sub) {
        sub.classList.toggle("is-open", !expanded);
        sub.hidden = expanded;
      }
    });
  });

  function closeUserMenu() {
    if (!userMenu || !userTrigger) return;
    userMenu.classList.remove("is-open");
    userMenu.hidden = true;
    userTrigger.setAttribute("aria-expanded", "false");
  }

  function openUserMenu() {
    if (!userMenu || !userTrigger) return;
    userMenu.classList.add("is-open");
    userMenu.hidden = false;
    userTrigger.setAttribute("aria-expanded", "true");
  }

  if (userTrigger && userMenu) {
    userTrigger.addEventListener("click", function () {
      var open = userTrigger.getAttribute("aria-expanded") === "true";
      if (open) {
        closeUserMenu();
      } else {
        openUserMenu();
      }
    });

    document.addEventListener("click", function (event) {
      if (!userMenu.classList.contains("is-open")) return;
      var target = event.target;
      if (userMenu.contains(target) || userTrigger.contains(target)) return;
      closeUserMenu();
    });
  }

  function filterPosts() {
    if (!searchInput) return;
    var query = searchInput.value.trim().toLowerCase();
    var rows = document.querySelectorAll("[data-post-row]");
    var visible = 0;

    rows.forEach(function (row) {
      var haystack = (row.getAttribute("data-search") || row.textContent || "").toLowerCase();
      var match = query === "" || haystack.indexOf(query) !== -1;
      row.classList.toggle("is-filtered-out", !match);
      if (match) visible += 1;
    });

    var empty = document.getElementById("admin-posts-empty-filter");
    if (empty) {
      empty.hidden = visible !== 0 || rows.length === 0;
    }
  }

  if (searchInput) {
    ["input", "keyup", "search", "change"].forEach(function (evt) {
      searchInput.addEventListener(evt, filterPosts);
    });
  }

  function getFocusable(container) {
    if (!container) return [];
    return Array.prototype.slice.call(
      container.querySelectorAll(
        'a[href], button:not([disabled]), textarea, input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
      )
    ).filter(function (el) {
      return !el.hasAttribute("disabled") && el.offsetParent !== null;
    });
  }

  function setAccountStatus(message, isError) {
    if (!accountStatus) return;
    accountStatus.textContent = message || "";
    accountStatus.className = "admin-modal__status" + (message ? (isError ? " tu-alert tu-alert--danger" : " tu-alert tu-alert--success") : "");
  }

  function openAccountModal() {
    if (!accountOverlay || !accountModal) return;
    closeUserMenu();
    accountLastFocus = document.activeElement;
    accountOverlay.hidden = false;
    document.body.style.overflow = "hidden";
    var focusables = getFocusable(accountModal);
    var firstInput = document.getElementById("account-display-name") || focusables[0] || accountModal;
    if (firstInput && typeof firstInput.focus === "function") {
      firstInput.focus();
    }
  }

  function closeAccountModal() {
    if (!accountOverlay) return;
    accountOverlay.hidden = true;
    if (!sidenav || !sidenav.classList.contains("is-open")) {
      document.body.style.overflow = "";
    }
    setAccountStatus("", false);
    if (accountLastFocus && typeof accountLastFocus.focus === "function") {
      accountLastFocus.focus();
    }
    accountLastFocus = null;
  }

  document.querySelectorAll("[data-account-open]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      openAccountModal();
    });
  });

  document.querySelectorAll("[data-account-close]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      closeAccountModal();
    });
  });

  if (accountOverlay) {
    accountOverlay.addEventListener("click", function (event) {
      if (event.target === accountOverlay) {
        closeAccountModal();
      }
    });
  }

  if (accountModal) {
    accountModal.addEventListener("keydown", function (event) {
      if (event.key !== "Tab" || accountOverlay.hidden) return;
      var focusables = getFocusable(accountModal);
      if (!focusables.length) return;
      var first = focusables[0];
      var last = focusables[focusables.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  if (accountForm) {
    accountForm.addEventListener("submit", function (event) {
      event.preventDefault();
      setAccountStatus("", false);

      var formData = new FormData(accountForm);
      var payload = {};
      formData.forEach(function (value, key) {
        payload[key] = value;
      });

      var submitBtn = accountForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      fetch("/admin/account.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(payload),
        credentials: "same-origin",
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.data || !result.data.ok) {
            setAccountStatus((result.data && result.data.error) || "Could not update account.", true);
            return;
          }
          setAccountStatus(result.data.message || "Account updated.", false);
          var nameEl = document.querySelector(".admin-user__name");
          var avatarEl = document.querySelector(".admin-user__avatar");
          var user = result.data.user || {};
          var label = user.display_name || user.username || "";
          if (nameEl && label) nameEl.textContent = label;
          if (avatarEl && label) avatarEl.textContent = label.charAt(0).toUpperCase();
          var newPass = document.getElementById("account-new-password");
          var confirmPass = document.getElementById("account-new-password-confirm");
          var currentPass = document.getElementById("account-current-password");
          if (newPass) newPass.value = "";
          if (confirmPass) confirmPass.value = "";
          if (currentPass) currentPass.value = "";
        })
        .catch(function () {
          setAccountStatus("Network error. Try again.", true);
        })
        .finally(function () {
          if (submitBtn) submitBtn.disabled = false;
        });
    });
  }
})();
