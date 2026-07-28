(() => {
  const filters = document.querySelector("[data-feed-filters]");
  const feed = document.querySelector("[data-feed]");
  const loader = document.querySelector("[data-feed-loader]");
  const sentinel = document.querySelector("[data-feed-sentinel]");
  const feedNow = document.querySelector("[data-feed-now]");

  // Live clock between topic filters and newest card (absolute local stamp, no relative)
  if (feedNow instanceof HTMLElement) {
    const formatNow = (date) => {
      const weekdays = [
        "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday",
      ];
      const months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December",
      ];
      const weekday = weekdays[date.getDay()].toUpperCase();
      const month = months[date.getMonth()].toUpperCase();
      const day = date.getDate();
      let hours = date.getHours();
      const minutes = String(date.getMinutes()).padStart(2, "0");
      const ampm = hours >= 12 ? "PM" : "AM";
      hours = hours % 12;
      if (hours === 0) hours = 12;
      return `${weekday}, ${month} ${day}, ${hours}:${minutes} ${ampm} — CURRENT`;
    };

    const tick = () => {
      feedNow.textContent = formatNow(new Date());
    };
    tick();
    setInterval(tick, 1000);
  }

  // Soft client-side highlight when navigating with history back/forward
  if (filters) {
    filters.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      const link = target.closest("a.filter-btn");
      if (!link) return;

      filters.querySelectorAll(".filter-btn").forEach((el) => {
        el.classList.remove("is-active");
        el.setAttribute("aria-selected", "false");
      });
      link.classList.add("is-active");
      link.setAttribute("aria-selected", "true");
    });
  }

  // Placeholder nav: prevent dead jumps for OTHER FEEDS / MAPS
  document.querySelectorAll(".nav-link--placeholder").forEach((el) => {
    el.addEventListener("click", (e) => {
      e.preventDefault();
    });
  });

  if (!(feed instanceof HTMLElement) || !(loader instanceof HTMLElement) || !(sentinel instanceof HTMLElement)) {
    return;
  }

  let loading = false;

  const setLoaderVisible = (visible) => {
    if (visible) {
      loader.hidden = false;
      loader.setAttribute("aria-hidden", "false");
      loader.classList.add("is-loading");
    } else {
      loader.hidden = true;
      loader.setAttribute("aria-hidden", "true");
      loader.classList.remove("is-loading");
    }
  };

  const hasMore = () => feed.dataset.hasMore === "1";

  const loadMore = async () => {
    if (loading || !hasMore()) return;
    loading = true;
    setLoaderVisible(true);

    const category = feed.dataset.category || "ALL";
    const offset = feed.dataset.offset || "0";
    const url = `/api/feed.php?category=${encodeURIComponent(category)}&offset=${encodeURIComponent(offset)}`;

    try {
      const res = await fetch(url, { headers: { Accept: "application/json" } });
      if (!res.ok) throw new Error(`Feed request failed (${res.status})`);
      const data = await res.json();
      if (!data || !data.ok) throw new Error("Invalid feed response");

      if (data.html) {
        feed.insertAdjacentHTML("beforeend", data.html);
      }

      feed.dataset.offset = String(data.nextOffset ?? offset);
      feed.dataset.hasMore = data.hasMore ? "1" : "0";

      if (!data.hasMore) {
        observer.disconnect();
      }
    } catch (err) {
      console.error(err);
      feed.dataset.hasMore = "0";
      observer.disconnect();
    } finally {
      loading = false;
      setLoaderVisible(false);
    }
  };

  const observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          loadMore();
        }
      }
    },
    { root: null, rootMargin: "240px 0px", threshold: 0 }
  );

  if (hasMore()) {
    observer.observe(sentinel);
  }
})();
