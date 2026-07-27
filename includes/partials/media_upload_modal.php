<?php

declare(strict_types=1);
?>
<div id="media-upload-overlay" class="admin-modal-overlay media-upload-overlay" hidden>
  <div
    id="media-upload-modal"
    class="admin-modal media-upload-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="media-upload-title"
    tabindex="-1"
  >
    <div class="admin-modal__header">
      <h2 id="media-upload-title" class="admin-modal__title">Upload media</h2>
      <button type="button" class="admin-modal__close" data-media-upload-close aria-label="Close">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    <div class="admin-modal__body media-upload-modal__body">
      <div class="media-upload-modal__panes">
        <aside class="media-upload-modal__meta" aria-label="Shared metadata">
          <div class="tu-form-row">
            <label for="media-upload-title-field">Title <span class="tu-help" style="display:inline;font-weight:400;">(optional)</span></label>
            <input id="media-upload-title-field" type="text" maxlength="255" placeholder="Applied to all files in this batch">
          </div>
          <div class="tu-form-row">
            <label for="media-upload-alt-field">Alt text <span class="tu-help" style="display:inline;font-weight:400;">(images)</span></label>
            <input id="media-upload-alt-field" type="text" maxlength="255" placeholder="Describe images for accessibility">
          </div>
          <div class="tu-form-row">
            <label for="media-upload-desc-field">Description</label>
            <textarea id="media-upload-desc-field" rows="4" placeholder="Optional notes for this batch"></textarea>
          </div>
          <div class="tu-form-row">
            <label for="media-upload-kind-hint">Kind filter hint</label>
            <select id="media-upload-kind-hint">
              <option value="">Any (auto-detect)</option>
              <option value="image">Images only</option>
              <option value="audio">Audio only</option>
              <option value="document">Documents only</option>
            </select>
            <p class="tu-help">Limits accepted types for this upload. Leave as Any to auto-detect.</p>
          </div>
        </aside>
        <div class="media-upload-modal__files">
          <div
            id="media-upload-dropzone"
            class="media-upload-dropzone"
            tabindex="0"
            role="button"
            aria-label="Drop files here or browse"
          >
            <svg class="media-upload-dropzone__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
              <path d="M12 16V4M12 4l-4 4M12 4l4 4" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 14v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4" stroke-linecap="round"/>
            </svg>
            <p class="media-upload-dropzone__text">Drag and drop files here</p>
            <p class="media-upload-dropzone__hints">
              Images JPEG/PNG/WebP/GIF ≤5MB · Audio MP3/WAV/OGG/M4A ≤25MB · Docs PDF/DOC/DOCX/TXT ≤10MB
            </p>
            <button type="button" class="tu-btn tu-btn--secondary tu-btn--modal" id="media-upload-browse">Browse files</button>
            <input
              id="media-upload-input"
              type="file"
              multiple
              hidden
              accept="image/jpeg,image/png,image/webp,image/gif,audio/mpeg,audio/wav,audio/ogg,audio/mp4,audio/x-m4a,.mp3,.wav,.ogg,.m4a,application/pdf,.doc,.docx,text/plain"
            >
          </div>
          <ul id="media-upload-queue" class="media-upload-queue" aria-live="polite" aria-label="Upload queue"></ul>
        </div>
      </div>
    </div>
    <div class="admin-modal__footer media-upload-modal__footer">
      <button type="button" class="tu-btn tu-btn--brand tu-btn--modal" id="media-upload-start" disabled>Upload</button>
      <button type="button" class="tu-btn tu-btn--secondary tu-btn--modal" id="media-upload-clear" disabled>Clear queue</button>
      <button type="button" class="tu-btn tu-btn--tertiary tu-btn--modal" data-media-upload-close>Close</button>
    </div>
  </div>
</div>
