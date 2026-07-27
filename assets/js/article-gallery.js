(() => {
  const gallery = document.querySelector(".article-gallery");
  if (!(gallery instanceof HTMLElement)) return;

  const triggers = Array.from(
    gallery.querySelectorAll(".article-gallery__trigger[data-gallery-src]")
  ).filter((el) => el instanceof HTMLButtonElement);

  if (triggers.length === 0) return;

  const items = triggers.map((btn) => ({
    src: btn.getAttribute("data-gallery-src") || "",
    alt: btn.getAttribute("data-gallery-alt") || "",
    caption: btn.getAttribute("data-gallery-caption") || "",
    trigger: btn,
  })).filter((item) => item.src !== "");

  if (items.length === 0) return;

  let index = 0;
  let lastFocus = null;
  let open = false;

  const root = document.createElement("div");
  root.className = "gallery-lightbox";
  root.hidden = true;
  root.innerHTML = `
    <div class="gallery-lightbox__backdrop" aria-hidden="true"></div>
    <div
      class="gallery-lightbox__dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="gallery-lightbox-title"
      tabindex="-1"
    >
      <div class="gallery-lightbox__toolbar">
        <p class="gallery-lightbox__counter" id="gallery-lightbox-title"></p>
        <button type="button" class="gallery-lightbox__close" aria-label="Close gallery">&times;</button>
      </div>
      <div class="gallery-lightbox__stage">
        <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--prev" aria-label="Previous image">&#8249;</button>
        <img class="gallery-lightbox__image" alt="" src="">
        <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--next" aria-label="Next image">&#8250;</button>
      </div>
      <p class="gallery-lightbox__caption"></p>
    </div>
  `;
  document.body.appendChild(root);

  const dialog = root.querySelector(".gallery-lightbox__dialog");
  const backdrop = root.querySelector(".gallery-lightbox__backdrop");
  const closeBtn = root.querySelector(".gallery-lightbox__close");
  const prevBtn = root.querySelector(".gallery-lightbox__nav--prev");
  const nextBtn = root.querySelector(".gallery-lightbox__nav--next");
  const image = root.querySelector(".gallery-lightbox__image");
  const caption = root.querySelector(".gallery-lightbox__caption");
  const counter = root.querySelector(".gallery-lightbox__counter");

  if (
    !(dialog instanceof HTMLElement) ||
    !(backdrop instanceof HTMLElement) ||
    !(closeBtn instanceof HTMLButtonElement) ||
    !(prevBtn instanceof HTMLButtonElement) ||
    !(nextBtn instanceof HTMLButtonElement) ||
    !(image instanceof HTMLImageElement) ||
    !(caption instanceof HTMLElement) ||
    !(counter instanceof HTMLElement)
  ) {
    return;
  }

  const focusableSelector =
    'button:not([disabled]):not([hidden]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

  const getFocusable = () =>
    Array.from(dialog.querySelectorAll(focusableSelector)).filter(
      (el) => el instanceof HTMLElement && !el.hidden && el.getClientRects().length > 0
    );

  const render = () => {
    const item = items[index];
    if (!item) return;

    image.src = item.src;
    image.alt = item.alt;
    caption.textContent = item.caption;
    counter.textContent = `Image ${index + 1} of ${items.length}`;

    const multi = items.length > 1;
    prevBtn.hidden = !multi;
    nextBtn.hidden = !multi;
    prevBtn.disabled = !multi;
    nextBtn.disabled = !multi;
  };

  const show = (startIndex) => {
    if (startIndex < 0 || startIndex >= items.length) return;
    index = startIndex;
    lastFocus = document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;
    render();
    root.hidden = false;
    open = true;
    document.body.classList.add("gallery-lightbox-open");
    closeBtn.focus();
  };

  const hide = () => {
    if (!open) return;
    open = false;
    root.hidden = true;
    document.body.classList.remove("gallery-lightbox-open");
    image.removeAttribute("src");
    if (lastFocus instanceof HTMLElement) {
      lastFocus.focus();
    }
    lastFocus = null;
  };

  const step = (delta) => {
    if (items.length < 2) return;
    index = (index + delta + items.length) % items.length;
    render();
  };

  triggers.forEach((btn) => {
    btn.addEventListener("click", () => {
      const raw = btn.getAttribute("data-gallery-index");
      const parsed = raw !== null ? Number.parseInt(raw, 10) : Number.NaN;
      const start = Number.isFinite(parsed) ? parsed : items.findIndex((item) => item.trigger === btn);
      show(start >= 0 ? start : 0);
    });
  });

  closeBtn.addEventListener("click", hide);
  backdrop.addEventListener("click", hide);
  prevBtn.addEventListener("click", () => step(-1));
  nextBtn.addEventListener("click", () => step(1));

  document.addEventListener("keydown", (event) => {
    if (!open) return;

    if (event.key === "Escape") {
      event.preventDefault();
      hide();
      return;
    }

    if (event.key === "ArrowLeft") {
      event.preventDefault();
      step(-1);
      return;
    }

    if (event.key === "ArrowRight") {
      event.preventDefault();
      step(1);
      return;
    }

    if (event.key !== "Tab") return;

    const focusable = getFocusable();
    if (focusable.length === 0) {
      event.preventDefault();
      dialog.focus();
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;

    if (!dialog.contains(active)) {
      event.preventDefault();
      first.focus();
      return;
    }

    if (event.shiftKey && active === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
  });
})();
