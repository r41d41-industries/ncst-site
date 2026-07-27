(function () {
  "use strict";

  var pickerOverlay = document.getElementById("media-picker-overlay");
  var pickerModal = document.getElementById("media-picker-modal");
  var pickerGrid = document.getElementById("media-picker-grid");
  var pickerEmpty = document.getElementById("media-picker-empty");
  var pickerSearch = document.getElementById("media-picker-search");
  var pickerSelect = document.getElementById("media-picker-select");
  var pickerCount = document.getElementById("media-picker-count");
  var pickerTitle = document.getElementById("media-picker-title");
  var pickerUploadNew = document.getElementById("media-picker-upload-new");
  var selectedId = null;
  var selectedItem = null;
  var selectedMap = {};
  var multiSelect = false;
  var pickerKind = "image";
  var onPick = null;
  var lastFocus = null;
  var itemsCache = [];
  var defaultSelectLabel = pickerSelect ? pickerSelect.textContent : "Select";

  function csrf() {
    var hidden = document.querySelector('input[name="_csrf"]');
    if (hidden) return hidden.value;
    if (window.NCST_ADMIN && window.NCST_ADMIN.csrf) return window.NCST_ADMIN.csrf;
    return "";
  }

  function selectedCount() {
    return Object.keys(selectedMap).length;
  }

  function isSelected(id) {
    if (multiSelect) return !!selectedMap[String(id)];
    return selectedId != null && Number(selectedId) === Number(id);
  }

  function updateSelectUi() {
    var count = multiSelect ? selectedCount() : selectedId ? 1 : 0;
    if (pickerSelect) {
      pickerSelect.disabled = count === 0;
      if (multiSelect) {
        pickerSelect.textContent =
          count > 0 ? "Add selected (" + count + ")" : "Add selected";
      } else {
        pickerSelect.textContent = defaultSelectLabel || "Select";
      }
    }
    if (pickerCount) {
      if (multiSelect && count > 0) {
        pickerCount.hidden = false;
        pickerCount.textContent = count + " selected";
      } else {
        pickerCount.hidden = true;
        pickerCount.textContent = "";
      }
    }
    if (pickerGrid) {
      pickerGrid.setAttribute("aria-multiselectable", multiSelect ? "true" : "false");
    }
  }

  function toggleMultiSelection(item) {
    var key = String(item.id);
    if (selectedMap[key]) {
      delete selectedMap[key];
    } else {
      selectedMap[key] = item;
    }
  }

  function getSelectedItems() {
    if (!multiSelect) {
      return selectedItem ? [selectedItem] : [];
    }
    var ordered = [];
    itemsCache.forEach(function (item) {
      if (selectedMap[String(item.id)]) ordered.push(selectedMap[String(item.id)]);
    });
    Object.keys(selectedMap).forEach(function (key) {
      var already = ordered.some(function (item) {
        return String(item.id) === key;
      });
      if (!already) ordered.push(selectedMap[key]);
    });
    return ordered;
  }

  function renderGrid(items) {
    if (!pickerGrid) return;
    pickerGrid.innerHTML = "";
    itemsCache = items || [];
    if (!itemsCache.length) {
      if (pickerEmpty) pickerEmpty.hidden = false;
      updateSelectUi();
      return;
    }
    if (pickerEmpty) pickerEmpty.hidden = true;
    itemsCache.forEach(function (item) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "media-picker__item";
      btn.setAttribute("role", "option");
      var selected = isSelected(item.id);
      btn.setAttribute("aria-selected", selected ? "true" : "false");
      if (selected) btn.classList.add("is-selected");
      btn.title = item.title || item.original_name || "#" + item.id;
      if (item.kind === "image") {
        var img = document.createElement("img");
        img.src = "/" + String(item.path || "").replace(/^\//, "");
        img.alt = item.alt_text || item.title || "";
        btn.appendChild(img);
      } else {
        btn.textContent = item.title || item.original_name || item.kind;
      }
      if (multiSelect) {
        var mark = document.createElement("span");
        mark.className = "media-picker__check";
        mark.setAttribute("aria-hidden", "true");
        btn.appendChild(mark);
      }
      btn.addEventListener("click", function () {
        if (multiSelect) {
          toggleMultiSelection(item);
        } else {
          selectedId = item.id;
          selectedItem = item;
        }
        renderGrid(itemsCache);
        updateSelectUi();
      });
      pickerGrid.appendChild(btn);
    });
    updateSelectUi();
  }

  function loadItems(query) {
    var url = "/admin/media/api_list.php?kind=" + encodeURIComponent(pickerKind);
    if (query) url += "&q=" + encodeURIComponent(query);
    fetch(url, { credentials: "same-origin" })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        renderGrid((data && data.items) || []);
      })
      .catch(function () {
        renderGrid([]);
      });
  }

  function openPicker(options) {
    options = options || {};
    pickerKind = options.kind || "image";
    multiSelect = !!options.multiple;
    onPick = typeof options.onSelect === "function" ? options.onSelect : null;
    selectedId = options.selectedId || null;
    selectedItem = null;
    selectedMap = {};
    if (pickerTitle) {
      pickerTitle.textContent = multiSelect
        ? "Add from library"
        : "Choose from library";
    }
    if (pickerSearch) {
      pickerSearch.value = "";
      pickerSearch.placeholder =
        pickerKind === "audio" ? "Search audio…" : "Search images…";
    }
    updateSelectUi();
    if (!pickerOverlay || !pickerModal) return;
    lastFocus = document.activeElement;
    pickerOverlay.hidden = false;
    document.body.style.overflow = "hidden";
    loadItems("");
    pickerModal.focus();
  }

  function closePicker() {
    if (!pickerOverlay) return;
    pickerOverlay.hidden = true;
    document.body.style.overflow = "";
    multiSelect = false;
    selectedMap = {};
    updateSelectUi();
    if (pickerTitle) pickerTitle.textContent = "Choose from library";
    if (lastFocus && typeof lastFocus.focus === "function") {
      lastFocus.focus();
    }
  }

  if (pickerSearch) {
    var searchTimer = null;
    pickerSearch.addEventListener("input", function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        loadItems(pickerSearch.value.trim());
      }, 200);
    });
  }

  if (pickerSelect) {
    pickerSelect.addEventListener("click", function () {
      if (multiSelect) {
        var items = getSelectedItems();
        if (items.length && typeof onPick === "function") {
          onPick(items);
        }
        closePicker();
        return;
      }
      if (!selectedItem && selectedId) {
        selectedItem = itemsCache.find(function (i) {
          return i.id === selectedId;
        });
      }
      if (selectedItem && typeof onPick === "function") {
        onPick(selectedItem);
      }
      closePicker();
    });
  }

  if (pickerUploadNew) {
    pickerUploadNew.addEventListener("click", function () {
      if (!window.NCSTMediaUpload) return;
      window.NCSTMediaUpload.open({
        kind: pickerKind,
        onComplete: function (uploaded) {
          if (uploaded && uploaded.length) {
            uploaded.forEach(function (item) {
              if (multiSelect) {
                selectedMap[String(item.id)] = item;
              }
            });
            if (!multiSelect) {
              selectedId = uploaded[0].id;
              selectedItem = uploaded[0];
            }
            var merged = itemsCache.slice();
            uploaded.forEach(function (item) {
              var exists = merged.some(function (row) {
                return row.id === item.id;
              });
              if (!exists) merged.unshift(item);
            });
            renderGrid(merged);
            updateSelectUi();
          }
        },
      });
    });
  }

  document.querySelectorAll("[data-media-picker-close]").forEach(function (btn) {
    btn.addEventListener("click", closePicker);
  });

  if (pickerOverlay) {
    pickerOverlay.addEventListener("click", function (event) {
      if (event.target === pickerOverlay) closePicker();
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && pickerOverlay && !pickerOverlay.hidden) {
      closePicker();
    }
  });

  function bindMediaField(root) {
    if (!root || root.getAttribute("data-media-field-bound") === "1") return;
    root.setAttribute("data-media-field-bound", "1");
    var idInput = root.querySelector("[data-media-id]");
    var pathInput = root.querySelector("[data-media-path]");
    var thumb = root.querySelector("[data-media-thumb]");
    var label = root.querySelector("[data-media-label]");
    var chooseBtn = root.querySelector("[data-media-choose]");
    var clearBtn = root.querySelector("[data-media-clear]");
    var kind = root.getAttribute("data-media-kind") || "image";

    function apply(item) {
      if (idInput) idInput.value = item && item.id ? String(item.id) : "";
      if (pathInput) pathInput.value = item && item.path ? item.path : "";
      if (thumb) {
        if (item && item.path) {
          thumb.hidden = false;
          thumb.src = "/" + String(item.path).replace(/^\//, "");
          thumb.alt = (item.alt_text || item.title || "") + "";
        } else {
          thumb.hidden = true;
          thumb.removeAttribute("src");
        }
      }
      if (label) {
        label.textContent = item
          ? item.title || item.original_name || "Selected media #" + item.id
          : "No media selected";
      }
    }

    if (chooseBtn) {
      chooseBtn.addEventListener("click", function () {
        openPicker({
          kind: kind,
          selectedId: idInput && idInput.value ? parseInt(idInput.value, 10) : null,
          onSelect: apply,
        });
      });
    }

    if (clearBtn) {
      clearBtn.addEventListener("click", function () {
        apply(null);
      });
    }
  }

  document.querySelectorAll("[data-media-field]").forEach(bindMediaField);

  /* Cropper on edit page */
  var cropImg = document.getElementById("media-crop-image");
  var cropper = null;
  if (cropImg && window.Cropper) {
    cropper = new window.Cropper(cropImg, {
      viewMode: 1,
      autoCropArea: 1,
      responsive: true,
      background: false,
    });
  }

  var cropSaveBtn = document.getElementById("media-crop-save");
  var cropForm = document.getElementById("media-crop-form");
  var cropDataInput = document.getElementById("media-crop-data");
  var cropMimeInput = document.getElementById("media-crop-mime");
  if (cropSaveBtn && cropper && cropForm && cropDataInput) {
    cropSaveBtn.addEventListener("click", function () {
      var canvas = cropper.getCroppedCanvas({
        maxWidth: 2400,
        maxHeight: 2400,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: "high",
      });
      if (!canvas) return;
      var mime = (cropMimeInput && cropMimeInput.value) || "image/jpeg";
      canvas.toBlob(
        function (blob) {
          if (!blob) return;
          var reader = new FileReader();
          reader.onload = function () {
            var result = String(reader.result || "");
            var base64 = result.indexOf(",") >= 0 ? result.split(",")[1] : result;
            cropDataInput.value = base64;
            cropForm.submit();
          };
          reader.readAsDataURL(blob);
        },
        mime,
        0.92
      );
    });
  }

  /* Collection ordered picker helpers */
  function appendCollectionItem(list, kind, item) {
    if (!list || !item) return false;
    var existing = list.querySelector(
      '.media-collection-items__row[data-media-id="' + String(item.id) + '"]'
    );
    if (existing) return false;
    var li = document.createElement("li");
    li.className = "media-collection-items__row";
    li.setAttribute("data-media-id", String(item.id));
    var thumbHtml =
      kind === "image"
        ? '<img src="/' + String(item.path).replace(/^\//, "") + '" alt="">'
        : '<span class="media-grid__thumb-icon">Audio</span>';
    li.innerHTML =
      '<span class="media-collection-items__ord"></span>' +
      thumbHtml +
      '<div><strong></strong><input type="hidden" name="item_media_ids[]" value="' +
      item.id +
      '">' +
      '<input class="tu-input" type="text" name="item_captions[]" placeholder="Optional override" style="margin-top:6px;"></div>' +
      '<button type="button" class="tu-btn tu-btn--secondary" data-collection-remove>Remove</button>';
    li.querySelector("strong").textContent =
      item.title || item.original_name || "#" + item.id;
    list.appendChild(li);
    return true;
  }

  document.querySelectorAll("[data-collection-add]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var list = document.getElementById(btn.getAttribute("data-collection-list") || "");
      var kind = btn.getAttribute("data-media-kind") || "image";
      if (!list) return;
      openPicker({
        kind: kind,
        multiple: true,
        onSelect: function (items) {
          var picked = Array.isArray(items) ? items : items ? [items] : [];
          var added = false;
          picked.forEach(function (item) {
            if (appendCollectionItem(list, kind, item)) added = true;
          });
          if (added) renumberCollection(list);
        },
      });
    });
  });

  document.addEventListener("click", function (event) {
    var removeBtn = event.target.closest("[data-collection-remove]");
    if (!removeBtn) return;
    var row = removeBtn.closest(".media-collection-items__row");
    var list = row && row.parentElement;
    if (row) row.remove();
    if (list) renumberCollection(list);
  });

  function renumberCollection(list) {
    Array.prototype.forEach.call(list.children, function (row, index) {
      var ord = row.querySelector(".media-collection-items__ord");
      if (ord) ord.textContent = String(index + 1);
    });
  }

  document.querySelectorAll(".media-collection-items").forEach(renumberCollection);

  /* Panel search for media rows */
  var searchInput = document.getElementById("admin-panel-search");
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      var q = searchInput.value.trim().toLowerCase();
      document.querySelectorAll("[data-media-row]").forEach(function (row) {
        var hay = (row.getAttribute("data-search") || row.textContent || "").toLowerCase();
        row.classList.toggle("is-filtered-out", q !== "" && hay.indexOf(q) === -1);
      });
    });
  }

  window.NCSTMediaPicker = {
    open: openPicker,
    close: closePicker,
    bindField: bindMediaField,
  };
})();
