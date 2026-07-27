/**
 * Shared admin data-table enhancement: search, column sort, pagination.
 *
 * Opt in: add class `admin-data-table` (and/or `data-admin-table`) on a <table>.
 *
 * Optional attributes:
 *   data-admin-table-search="on|off|external"  (default: on)
 *   data-admin-table-search-input="#selector"  (required when search=external)
 *   data-admin-table-empty-message="…"         (no-results copy)
 *   data-admin-table-search-label="…"          (aria-label / placeholder)
 *
 * Sortable headers: all <th> except those with data-sortable="false"
 * or headers that only contain .admin-sr-only (actions columns).
 * Use data-sort-value on a cell for a custom sort key.
 */
(function () {
  "use strict";

  var PAGE_SIZE_KEY = "ncst-admin-table-page-size";
  var PAGE_SIZES = [10, 25, 50];
  var DEFAULT_PAGE_SIZE = 10;
  var uid = 0;

  function readPageSize() {
    try {
      var stored = parseInt(localStorage.getItem(PAGE_SIZE_KEY) || "", 10);
      if (PAGE_SIZES.indexOf(stored) !== -1) return stored;
    } catch (err) {
      /* ignore */
    }
    return DEFAULT_PAGE_SIZE;
  }

  function writePageSize(size) {
    try {
      localStorage.setItem(PAGE_SIZE_KEY, String(size));
    } catch (err) {
      /* ignore */
    }
  }

  function isEmptyPlaceholderRow(row, colCount) {
    if (row.hasAttribute("data-admin-empty-row")) return true;
    var cells = row.children;
    if (cells.length === 1) {
      var colspan = parseInt(cells[0].getAttribute("colspan") || "1", 10);
      if (colspan >= colCount) return true;
    }
    return false;
  }

  function isActionsHeader(th) {
    if (th.getAttribute("data-sortable") === "false") return true;
    var clone = th.cloneNode(true);
    var srOnly = clone.querySelectorAll(".admin-sr-only");
    for (var i = 0; i < srOnly.length; i += 1) {
      srOnly[i].parentNode.removeChild(srOnly[i]);
    }
    return (clone.textContent || "").trim() === "";
  }

  function cellSortValue(cell) {
    if (!cell) return "";
    var raw = cell.getAttribute("data-sort-value");
    if (raw !== null) return raw.trim().toLowerCase();
    return (cell.textContent || "").replace(/\s+/g, " ").trim().toLowerCase();
  }

  function compareValues(a, b) {
    var numA = parseFloat(a);
    var numB = parseFloat(b);
    var aIsNum = a !== "" && !isNaN(numA) && /^-?\d+(\.\d+)?$/.test(a);
    var bIsNum = b !== "" && !isNaN(numB) && /^-?\d+(\.\d+)?$/.test(b);
    if (aIsNum && bIsNum) {
      if (numA < numB) return -1;
      if (numA > numB) return 1;
      return 0;
    }
    if (a < b) return -1;
    if (a > b) return 1;
    return 0;
  }

  function rowMatchesQuery(row, query) {
    if (!query) return true;
    var haystack = (row.getAttribute("data-search") || row.textContent || "")
      .replace(/\s+/g, " ")
      .trim()
      .toLowerCase();
    return haystack.indexOf(query) !== -1;
  }

  function enhance(table) {
    if (table.getAttribute("data-admin-table-ready") === "1") return;

    var thead = table.tHead;
    var tbody = table.tBodies[0];
    if (!thead || !tbody) return;

    var headerRow = thead.rows[0];
    if (!headerRow) return;

    var headerCells = Array.prototype.slice.call(headerRow.children);
    var colCount = headerCells.length;
    var allRows = Array.prototype.slice.call(tbody.rows);
    var dataRows = allRows.filter(function (row) {
      return !isEmptyPlaceholderRow(row, colCount);
    });

    if (dataRows.length === 0) return;

    table.setAttribute("data-admin-table-ready", "1");

    var searchMode = (table.getAttribute("data-admin-table-search") || "on").toLowerCase();
    if (searchMode !== "off" && searchMode !== "external") searchMode = "on";

    var emptyMessage =
      table.getAttribute("data-admin-table-empty-message") ||
      "No results match your search.";
    var searchLabel =
      table.getAttribute("data-admin-table-search-label") || "Filter table";

    var wrap = table.closest(".tu-table-wrap") || table.parentNode;
    var root = document.createElement("div");
    root.className = "admin-data-table__root";
    wrap.parentNode.insertBefore(root, wrap);
    root.appendChild(wrap);

    var toolbar = document.createElement("div");
    toolbar.className = "admin-data-table__toolbar";
    root.insertBefore(toolbar, wrap);

    var searchInput = null;
    if (searchMode === "on") {
      var searchWrap = document.createElement("div");
      searchWrap.className = "admin-data-table__search-wrap";
      searchInput = document.createElement("input");
      searchInput.type = "search";
      searchInput.className = "tu-input admin-data-table__search";
      searchInput.placeholder = searchLabel.indexOf("…") !== -1 || searchLabel.indexOf("...") !== -1
        ? searchLabel
        : searchLabel + "…";
      searchInput.setAttribute("aria-label", searchLabel);
      searchWrap.appendChild(searchInput);
      toolbar.appendChild(searchWrap);
    } else if (searchMode === "external") {
      var selector = table.getAttribute("data-admin-table-search-input") || "";
      searchInput = selector ? document.querySelector(selector) : null;
    }

    var pageSizeWrap = document.createElement("div");
    pageSizeWrap.className = "admin-data-table__page-size";
    var pageSizeId = "admin-table-page-size-" + String((uid += 1));
    var pageSizeLabel = document.createElement("label");
    pageSizeLabel.setAttribute("for", pageSizeId);
    pageSizeLabel.textContent = "Rows per page";
    var pageSizeSelect = document.createElement("select");
    pageSizeSelect.id = pageSizeId;
    pageSizeSelect.className = "tu-input admin-data-table__page-size-select";
    pageSizeSelect.setAttribute("aria-label", "Rows per page");
    PAGE_SIZES.forEach(function (size) {
      var opt = document.createElement("option");
      opt.value = String(size);
      opt.textContent = String(size);
      pageSizeSelect.appendChild(opt);
    });
    pageSizeWrap.appendChild(pageSizeLabel);
    pageSizeWrap.appendChild(pageSizeSelect);
    toolbar.appendChild(pageSizeWrap);

    var footer = document.createElement("div");
    footer.className = "admin-data-table__footer";
    var status = document.createElement("p");
    status.className = "admin-data-table__status";
    status.setAttribute("aria-live", "polite");
    var pagination = document.createElement("nav");
    pagination.className = "admin-data-table__pagination";
    pagination.setAttribute("aria-label", "Table pagination");
    footer.appendChild(status);
    footer.appendChild(pagination);
    root.appendChild(footer);

    var noResults = document.createElement("p");
    noResults.className = "tu-empty admin-data-table__no-results";
    noResults.hidden = true;
    noResults.textContent = emptyMessage;
    root.appendChild(noResults);

    var state = {
      query: "",
      sortCol: -1,
      sortDir: "asc",
      page: 1,
      pageSize: readPageSize(),
    };
    pageSizeSelect.value = String(state.pageSize);

    var sortButtons = [];

    headerCells.forEach(function (th, index) {
      if (isActionsHeader(th)) {
        th.setAttribute("data-sortable", "false");
        return;
      }

      var labelText = (th.textContent || "").replace(/\s+/g, " ").trim() || "Column";
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "admin-data-table__sort";
      btn.setAttribute("aria-label", "Sort by " + labelText);
      while (th.firstChild) {
        btn.appendChild(th.firstChild);
      }
      var indicator = document.createElement("span");
      indicator.className = "admin-data-table__sort-indicator";
      indicator.setAttribute("aria-hidden", "true");
      btn.appendChild(indicator);
      th.appendChild(btn);
      th.setAttribute("aria-sort", "none");
      sortButtons.push({ th: th, btn: btn, index: index });

      btn.addEventListener("click", function () {
        if (state.sortCol === index) {
          state.sortDir = state.sortDir === "asc" ? "desc" : "asc";
        } else {
          state.sortCol = index;
          state.sortDir = "asc";
        }
        state.page = 1;
        render();
      });
    });

    function sortedRows(rows) {
      if (state.sortCol < 0) return rows.slice();
      var col = state.sortCol;
      var dir = state.sortDir === "desc" ? -1 : 1;
      return rows.slice().sort(function (rowA, rowB) {
        var a = cellSortValue(rowA.children[col]);
        var b = cellSortValue(rowB.children[col]);
        return compareValues(a, b) * dir;
      });
    }

    function render() {
      var filtered = dataRows.filter(function (row) {
        return rowMatchesQuery(row, state.query);
      });
      var ordered = sortedRows(filtered);
      var total = ordered.length;
      var pageCount = Math.max(1, Math.ceil(total / state.pageSize) || 1);
      if (state.page > pageCount) state.page = pageCount;
      if (state.page < 1) state.page = 1;

      var start = (state.page - 1) * state.pageSize;
      var end = start + state.pageSize;

      /* Re-append in sorted order so visual order matches sort */
      ordered.forEach(function (row) {
        tbody.appendChild(row);
      });

      dataRows.forEach(function (row) {
        var idx = ordered.indexOf(row);
        var onPage = idx !== -1 && idx >= start && idx < end;
        row.hidden = !onPage;
        row.classList.toggle("is-filtered-out", !onPage);
      });

      sortButtons.forEach(function (item) {
        if (item.index === state.sortCol) {
          item.th.setAttribute("aria-sort", state.sortDir === "asc" ? "ascending" : "descending");
          item.btn.classList.add("is-sorted");
          item.btn.classList.toggle("is-asc", state.sortDir === "asc");
          item.btn.classList.toggle("is-desc", state.sortDir === "desc");
        } else {
          item.th.setAttribute("aria-sort", "none");
          item.btn.classList.remove("is-sorted", "is-asc", "is-desc");
        }
      });

      var hasMatches = total > 0;
      wrap.hidden = !hasMatches;
      footer.hidden = !hasMatches;
      noResults.hidden = hasMatches;

      if (!hasMatches) {
        status.textContent = "0 results";
        pagination.innerHTML = "";
        return;
      }

      var from = start + 1;
      var to = Math.min(end, total);
      status.textContent = "Showing " + from + "–" + to + " of " + total;

      pagination.innerHTML = "";
      if (pageCount <= 1) return;

      function addPageBtn(label, page, opts) {
        opts = opts || {};
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "admin-data-table__page-btn" + (opts.active ? " is-active" : "");
        btn.textContent = label;
        if (opts.label) btn.setAttribute("aria-label", opts.label);
        if (opts.active) btn.setAttribute("aria-current", "page");
        btn.disabled = !!opts.disabled;
        btn.addEventListener("click", function () {
          state.page = page;
          render();
        });
        pagination.appendChild(btn);
      }

      addPageBtn("Prev", state.page - 1, {
        label: "Previous page",
        disabled: state.page <= 1,
      });

      var windowSize = 5;
      var startPage = Math.max(1, state.page - Math.floor(windowSize / 2));
      var endPage = Math.min(pageCount, startPage + windowSize - 1);
      startPage = Math.max(1, endPage - windowSize + 1);

      if (startPage > 1) {
        addPageBtn("1", 1, { label: "Page 1" });
        if (startPage > 2) {
          var ellipsis = document.createElement("span");
          ellipsis.className = "admin-data-table__ellipsis";
          ellipsis.textContent = "…";
          ellipsis.setAttribute("aria-hidden", "true");
          pagination.appendChild(ellipsis);
        }
      }

      for (var p = startPage; p <= endPage; p += 1) {
        addPageBtn(String(p), p, {
          label: "Page " + p,
          active: p === state.page,
        });
      }

      if (endPage < pageCount) {
        if (endPage < pageCount - 1) {
          var ellipsisEnd = document.createElement("span");
          ellipsisEnd.className = "admin-data-table__ellipsis";
          ellipsisEnd.textContent = "…";
          ellipsisEnd.setAttribute("aria-hidden", "true");
          pagination.appendChild(ellipsisEnd);
        }
        addPageBtn(String(pageCount), pageCount, { label: "Page " + pageCount });
      }

      addPageBtn("Next", state.page + 1, {
        label: "Next page",
        disabled: state.page >= pageCount,
      });
    }

    function onSearchChange() {
      if (!searchInput) return;
      state.query = (searchInput.value || "").trim().toLowerCase();
      state.page = 1;
      render();
    }

    if (searchInput) {
      ["input", "keyup", "search", "change"].forEach(function (evt) {
        searchInput.addEventListener(evt, onSearchChange);
      });
      state.query = (searchInput.value || "").trim().toLowerCase();
    }

    pageSizeSelect.addEventListener("change", function () {
      var next = parseInt(pageSizeSelect.value, 10);
      if (PAGE_SIZES.indexOf(next) === -1) next = DEFAULT_PAGE_SIZE;
      state.pageSize = next;
      state.page = 1;
      writePageSize(next);
      render();
    });

    render();
  }

  function init() {
    document
      .querySelectorAll("table.admin-data-table, table[data-admin-table]")
      .forEach(enhance);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
