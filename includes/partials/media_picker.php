<?php

declare(strict_types=1);
?>
<div id="media-picker-overlay" class="admin-modal-overlay media-picker-overlay" hidden>
  <div
    id="media-picker-modal"
    class="admin-modal media-picker-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="media-picker-title"
    tabindex="-1"
  >
    <div class="admin-modal__header">
      <h2 id="media-picker-title" class="admin-modal__title">Choose from library</h2>
      <button type="button" class="admin-modal__close" data-media-picker-close aria-label="Close">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    <div class="admin-modal__body">
      <div class="media-picker__toolbar">
        <input id="media-picker-search" class="tu-input" type="search" placeholder="Search images…" aria-label="Search media">
        <button type="button" class="tu-btn tu-btn--secondary tu-btn--modal" id="media-picker-upload-new">Upload new</button>
      </div>
      <div id="media-picker-grid" class="media-picker__grid" role="listbox" aria-label="Media items"></div>
      <p id="media-picker-empty" class="tu-empty" hidden>No matching media.</p>
    </div>
    <div class="admin-modal__footer">
      <button type="button" class="tu-btn tu-btn--brand tu-btn--modal" id="media-picker-select" disabled>Select</button>
      <button type="button" class="tu-btn tu-btn--secondary tu-btn--modal" data-media-picker-close>Cancel</button>
    </div>
  </div>
</div>
