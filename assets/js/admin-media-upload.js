(function () {
  "use strict";

  var overlay = document.getElementById("media-upload-overlay");
  var modal = document.getElementById("media-upload-modal");
  var dropzone = document.getElementById("media-upload-dropzone");
  var input = document.getElementById("media-upload-input");
  var browseBtn = document.getElementById("media-upload-browse");
  var queueEl = document.getElementById("media-upload-queue");
  var startBtn = document.getElementById("media-upload-start");
  var clearBtn = document.getElementById("media-upload-clear");
  var titleField = document.getElementById("media-upload-title-field");
  var altField = document.getElementById("media-upload-alt-field");
  var descField = document.getElementById("media-upload-desc-field");
  var kindHint = document.getElementById("media-upload-kind-hint");
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrfToken =
    (csrfMeta && csrfMeta.getAttribute("content")) ||
    (window.NCST_ADMIN && window.NCST_ADMIN.csrf) ||
    "";

  var queue = [];
  var uploading = false;
  var lastFocus = null;
  var onComplete = null;
  var concurrency = 2;

  function csrf() {
    if (csrfToken) return csrfToken;
    var hidden = document.querySelector('input[name="_csrf"]');
    return hidden ? hidden.value : "";
  }

  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
  }

  function syncButtons() {
    var pending = queue.some(function (item) {
      return item.status === "queued" || item.status === "uploading";
    });
    var hasItems = queue.length > 0;
    if (startBtn) startBtn.disabled = !hasItems || uploading || !queue.some(function (i) { return i.status === "queued"; });
    if (clearBtn) clearBtn.disabled = !hasItems || uploading;
  }

  function renderQueue() {
    if (!queueEl) return;
    queueEl.innerHTML = "";
    queue.forEach(function (item, index) {
      var li = document.createElement("li");
      li.className = "media-upload-queue__item";
      if (item.status === "done") li.classList.add("is-done");
      if (item.status === "error") li.classList.add("is-error");
      li.innerHTML =
        '<div class="media-upload-queue__name"></div>' +
        '<div class="media-upload-queue__meta"></div>' +
        '<div class="media-upload-queue__bar" aria-hidden="true"><span></span></div>';
      li.querySelector(".media-upload-queue__name").textContent = item.file.name;
      var meta = formatBytes(item.file.size) + " · " + item.status;
      if (item.error) meta += " — " + item.error;
      if (item.media && item.media.id) meta += " · #" + item.media.id;
      li.querySelector(".media-upload-queue__meta").textContent = meta;
      li.querySelector(".media-upload-queue__bar > span").style.width =
        Math.max(0, Math.min(100, item.progress || 0)) + "%";
      queueEl.appendChild(li);
      item._el = li;
    });
    syncButtons();
  }

  function addFiles(fileList) {
    Array.prototype.forEach.call(fileList || [], function (file) {
      queue.push({
        file: file,
        status: "queued",
        progress: 0,
        error: null,
        media: null,
      });
    });
    renderQueue();
  }

  function clearQueue() {
    if (uploading) return;
    queue = [];
    renderQueue();
  }

  function uploadOne(item) {
    return new Promise(function (resolve) {
      item.status = "uploading";
      item.progress = 0;
      item.error = null;
      renderQueue();

      var form = new FormData();
      form.append("file", item.file);
      form.append("_csrf", csrf());
      if (titleField && titleField.value.trim()) form.append("title", titleField.value.trim());
      if (altField && altField.value.trim()) form.append("alt_text", altField.value.trim());
      if (descField && descField.value.trim()) form.append("description", descField.value.trim());
      if (kindHint && kindHint.value) form.append("kind", kindHint.value);

      var xhr = new XMLHttpRequest();
      xhr.open("POST", "/admin/media/api_upload.php");
      xhr.responseType = "json";
      xhr.upload.onprogress = function (event) {
        if (!event.lengthComputable) return;
        item.progress = Math.round((event.loaded / event.total) * 100);
        if (item._el) {
          var bar = item._el.querySelector(".media-upload-queue__bar > span");
          if (bar) bar.style.width = item.progress + "%";
        }
      };
      xhr.onload = function () {
        var data = xhr.response;
        if (xhr.status >= 200 && xhr.status < 300 && data && data.ok) {
          item.status = "done";
          item.progress = 100;
          item.media = data.media || null;
        } else {
          item.status = "error";
          item.error =
            (data && data.error) ||
            "Upload failed (" + xhr.status + ")";
        }
        renderQueue();
        resolve(item);
      };
      xhr.onerror = function () {
        item.status = "error";
        item.error = "Network error";
        renderQueue();
        resolve(item);
      };
      xhr.send(form);
    });
  }

  function runUploads() {
    if (uploading) return;
    uploading = true;
    syncButtons();

    var pending = queue.filter(function (item) {
      return item.status === "queued";
    });
    var index = 0;
    var active = 0;
    var results = [];

    function next() {
      if (index >= pending.length && active === 0) {
        uploading = false;
        syncButtons();
        var uploaded = results.filter(function (r) {
          return r.status === "done" && r.media;
        }).map(function (r) {
          return r.media;
        });
        if (typeof onComplete === "function") {
          onComplete(uploaded);
        } else if (uploaded.length && window.location.pathname.indexOf("/admin/media") === 0) {
          window.location.reload();
        }
        return;
      }
      while (active < concurrency && index < pending.length) {
        (function (item) {
          active += 1;
          uploadOne(item).then(function (result) {
            results.push(result);
            active -= 1;
            next();
          });
        })(pending[index]);
        index += 1;
      }
    }
    next();
  }

  function openModal(options) {
    options = options || {};
    onComplete = typeof options.onComplete === "function" ? options.onComplete : null;
    if (kindHint) {
      kindHint.value = options.kind || "";
    }
    if (!overlay || !modal) return;
    lastFocus = document.activeElement;
    overlay.hidden = false;
    document.body.style.overflow = "hidden";
    modal.focus();
  }

  function closeModal() {
    if (!overlay) return;
    if (uploading) return;
    overlay.hidden = true;
    document.body.style.overflow = "";
    if (lastFocus && typeof lastFocus.focus === "function") {
      lastFocus.focus();
    }
  }

  if (browseBtn && input) {
    browseBtn.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();
      input.click();
    });
  }

  if (dropzone && input) {
    dropzone.addEventListener("click", function () {
      input.click();
    });
    dropzone.addEventListener("keydown", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        input.click();
      }
    });
    ["dragenter", "dragover"].forEach(function (evt) {
      dropzone.addEventListener(evt, function (event) {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.add("is-dragover");
      });
    });
    ["dragleave", "drop"].forEach(function (evt) {
      dropzone.addEventListener(evt, function (event) {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.remove("is-dragover");
      });
    });
    dropzone.addEventListener("drop", function (event) {
      if (event.dataTransfer && event.dataTransfer.files) {
        addFiles(event.dataTransfer.files);
      }
    });
  }

  if (input) {
    input.addEventListener("change", function () {
      if (input.files) addFiles(input.files);
      input.value = "";
    });
  }

  if (startBtn) {
    startBtn.addEventListener("click", function () {
      runUploads();
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener("click", clearQueue);
  }

  document.querySelectorAll("[data-media-upload-close]").forEach(function (btn) {
    btn.addEventListener("click", closeModal);
  });

  document.querySelectorAll("[data-media-upload-open]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      openModal({
        kind: btn.getAttribute("data-media-kind") || "",
      });
    });
  });

  if (overlay) {
    overlay.addEventListener("click", function (event) {
      if (event.target === overlay) closeModal();
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && overlay && !overlay.hidden) {
      closeModal();
    }
  });

  window.NCSTMediaUpload = {
    open: openModal,
    close: closeModal,
  };
})();
