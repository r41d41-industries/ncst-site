(() => {
  const root = document.querySelector("[data-article-playlist]");
  if (!(root instanceof HTMLElement)) return;

  const audio = root.querySelector(".article-playlist__audio");
  const playBtn = root.querySelector(".article-playlist__play");
  const muteBtn = root.querySelector(".article-playlist__mute");
  const seek = root.querySelector(".article-playlist__seek");
  const nowTitle = root.querySelector(".article-playlist__now-title");
  const timeCurrent = root.querySelector(".article-playlist__time--current");
  const timeDuration = root.querySelector(".article-playlist__time--duration");
  const trackButtons = Array.from(
    root.querySelectorAll(".article-playlist__track[data-playlist-src]")
  ).filter((el) => el instanceof HTMLButtonElement);

  if (
    !(audio instanceof HTMLAudioElement) ||
    !(playBtn instanceof HTMLButtonElement) ||
    !(muteBtn instanceof HTMLButtonElement) ||
    !(seek instanceof HTMLInputElement) ||
    !(nowTitle instanceof HTMLElement) ||
    !(timeCurrent instanceof HTMLElement) ||
    !(timeDuration instanceof HTMLElement) ||
    trackButtons.length === 0
  ) {
    return;
  }

  const tracks = trackButtons
    .map((btn) => ({
      src: (btn.getAttribute("data-playlist-src") || "").trim(),
      title: (btn.getAttribute("data-playlist-title") || "Audio").trim() || "Audio",
      button: btn,
    }))
    .filter((track) => track.src !== "");

  if (tracks.length === 0) return;

  let index = 0;
  let seeking = false;
  let autoAdvance = true;

  const formatTime = (seconds) => {
    if (!Number.isFinite(seconds) || seconds < 0) return "0:00";
    const total = Math.floor(seconds);
    const m = Math.floor(total / 60);
    const s = total % 60;
    return `${m}:${String(s).padStart(2, "0")}`;
  };

  const setPlayingUi = (playing) => {
    root.classList.toggle("is-playing", playing);
    playBtn.setAttribute("aria-label", playing ? "Pause" : "Play");
    playBtn.classList.toggle("is-playing", playing);
  };

  const setMuteUi = (muted) => {
    muteBtn.setAttribute("aria-pressed", muted ? "true" : "false");
    muteBtn.setAttribute("aria-label", muted ? "Unmute" : "Mute");
    muteBtn.classList.toggle("is-muted", muted);
  };

  const updateTimes = () => {
    const current = audio.currentTime || 0;
    const duration = Number.isFinite(audio.duration) ? audio.duration : 0;
    timeCurrent.textContent = formatTime(current);
    timeDuration.textContent = formatTime(duration);

    if (!seeking) {
      seek.max = String(duration > 0 ? duration : 0);
      seek.value = String(duration > 0 ? Math.min(current, duration) : 0);
    }
  };

  const highlightTrack = (activeIndex) => {
    tracks.forEach((track, i) => {
      const active = i === activeIndex;
      track.button.classList.toggle("is-active", active);
      track.button.setAttribute("aria-current", active ? "true" : "false");
    });
  };

  const loadTrack = (nextIndex, { play = false } = {}) => {
    if (nextIndex < 0 || nextIndex >= tracks.length) return;

    index = nextIndex;
    const track = tracks[index];
    const wasPlaying = play || !audio.paused;

    audio.src = track.src;
    nowTitle.textContent = track.title;
    highlightTrack(index);
    updateTimes();

    playBtn.disabled = false;
    muteBtn.disabled = false;
    seek.disabled = false;

    if (wasPlaying || play) {
      const playPromise = audio.play();
      if (playPromise && typeof playPromise.catch === "function") {
        playPromise.catch(() => {
          setPlayingUi(false);
        });
      }
    } else {
      setPlayingUi(false);
    }
  };

  const togglePlay = () => {
    if (!audio.src) {
      loadTrack(index, { play: true });
      return;
    }
    if (audio.paused) {
      const playPromise = audio.play();
      if (playPromise && typeof playPromise.catch === "function") {
        playPromise.catch(() => setPlayingUi(false));
      }
    } else {
      audio.pause();
    }
  };

  playBtn.addEventListener("click", togglePlay);

  muteBtn.addEventListener("click", () => {
    audio.muted = !audio.muted;
    setMuteUi(audio.muted);
  });

  seek.addEventListener("pointerdown", () => {
    seeking = true;
  });

  seek.addEventListener("pointerup", () => {
    seeking = false;
  });

  seek.addEventListener("change", () => {
    const next = Number.parseFloat(seek.value);
    if (Number.isFinite(next)) {
      audio.currentTime = next;
    }
    seeking = false;
    updateTimes();
  });

  seek.addEventListener("input", () => {
    seeking = true;
    const next = Number.parseFloat(seek.value);
    timeCurrent.textContent = formatTime(Number.isFinite(next) ? next : 0);
  });

  tracks.forEach((track, i) => {
    track.button.addEventListener("click", () => {
      if (i === index && audio.src) {
        togglePlay();
        return;
      }
      loadTrack(i, { play: true });
    });
  });

  audio.addEventListener("play", () => setPlayingUi(true));
  audio.addEventListener("pause", () => setPlayingUi(false));
  audio.addEventListener("timeupdate", updateTimes);
  audio.addEventListener("loadedmetadata", updateTimes);
  audio.addEventListener("durationchange", updateTimes);
  audio.addEventListener("volumechange", () => setMuteUi(audio.muted || audio.volume === 0));

  audio.addEventListener("ended", () => {
    setPlayingUi(false);
    if (!autoAdvance || index >= tracks.length - 1) {
      updateTimes();
      return;
    }
    loadTrack(index + 1, { play: true });
  });

  root.addEventListener("keydown", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !root.contains(target)) return;

    if (event.key === " " && target === playBtn) {
      // Native button activation handles Space; avoid page scroll elsewhere.
      return;
    }

    if (event.key === "ArrowRight" && (target === seek || target === playBtn)) {
      event.preventDefault();
      if (Number.isFinite(audio.duration)) {
        audio.currentTime = Math.min(audio.duration, (audio.currentTime || 0) + 5);
      }
      return;
    }

    if (event.key === "ArrowLeft" && (target === seek || target === playBtn)) {
      event.preventDefault();
      audio.currentTime = Math.max(0, (audio.currentTime || 0) - 5);
    }
  });

  setMuteUi(false);
  setPlayingUi(false);
  loadTrack(0, { play: false });
})();
