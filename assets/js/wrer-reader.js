document.addEventListener("DOMContentLoaded", function () {
  console.log("WRER EPUB Reader initialized after DOM is ready.");
});

(function () {
  "use strict";

  const HIDDEN_CLASS = "hidden";
  const SWIPE_THRESHOLD = 50;
  const TEXT = {
    loading: "Loading…",
    libraryMissing: "Reader library unavailable.",
    openFailed: "Unable to open this book.",
    bookmarkSaved: "Bookmark saved!",
    bookmarkExists: "Bookmark already added.",
  };

  const state = {
    wrapper: null,
    readerArea: null,
    progress: null,
    prevBtn: null,
    nextBtn: null,
    bookmarkBtn: null,
    fullscreenBtn: null,
    resumePopup: null,
    resumeYes: null,
    resumeNo: null,
    buyLink: null,
    book: null,
    rendition: null,
    readerId: "",
    bookId: "",
    lastLocation: null,
    pendingLocation: null,
    statusTimer: null,
    uiInitialized: false,
    fullscreenTarget: null,
    touchStartX: 0,
  };

  function qs(id) {
    return document.getElementById(id);
  }

  function slugify(value) {
    return value
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");
  }

  function deriveBookId(epubUrl, title) {
    if (title) {
      const slug = slugify(title);
      if (slug) {
        return slug;
      }
    }

    if (epubUrl) {
      let hash = 0;
      for (let i = 0; i < epubUrl.length; i += 1) {
        hash = (hash * 31 + epubUrl.charCodeAt(i)) >>> 0;
      }
      return "book-" + hash.toString(16);
    }

    return "book-default";
  }

  function clearStatusTimer() {
    if (state.statusTimer) {
      window.clearTimeout(state.statusTimer);
      state.statusTimer = null;
    }
  }

  function setProgressText(text) {
    if (state.progress) {
      state.progress.textContent = text;
    }
  }

  function showStatus(text) {
    setProgressText(text);
    clearStatusTimer();
    if (state.lastLocation) {
      state.statusTimer = window.setTimeout(function () {
        updateProgress(state.lastLocation);
      }, 1800);
    }
  }

  function storagePrefix() {
    if (!state.readerId || !state.bookId) {
      return "";
    }

    return "wrer_" + state.readerId + "_" + state.bookId;
  }

  function legacyProgressKey() {
    if (!state.readerId) {
      return "";
    }

    return "wrer_progress_" + state.readerId;
  }

  function legacyBookmarkKey() {
    if (!state.readerId) {
      return "";
    }

    return "wrer_bookmark_" + state.readerId;
  }

  function getSavedLocation() {
    try {
      const key = storagePrefix();
      if (!key) {
        return "";
      }
      const stored = window.localStorage.getItem(key + "_last_location") || "";
      if (stored) {
        return stored;
      }

      const legacyKey = legacyProgressKey();
      if (!legacyKey) {
        return "";
      }

      const legacyValue = window.localStorage.getItem(legacyKey) || "";
      if (legacyValue) {
        window.localStorage.setItem(key + "_last_location", legacyValue);
        window.localStorage.removeItem(legacyKey);
      }

      return legacyValue;
    } catch (error) {
      return "";
    }
  }

  function saveLocation(cfi) {
    try {
      const key = storagePrefix();
      if (!key) {
        return;
      }
      window.localStorage.setItem(key + "_last_location", cfi);

      const legacyKey = legacyProgressKey();
      if (legacyKey) {
        window.localStorage.setItem(legacyKey, cfi);
      }
    } catch (error) {
      // Ignore storage errors.
    }
  }

  function clearSavedLocation() {
    try {
      const key = storagePrefix();
      if (!key) {
        return;
      }
      window.localStorage.removeItem(key + "_last_location");

      const legacyKey = legacyProgressKey();
      if (legacyKey) {
        window.localStorage.removeItem(legacyKey);
      }
    } catch (error) {
      // Ignore storage errors.
    }
  }

  function saveBookmark(cfi) {
    try {
      const key = storagePrefix();
      if (!key) {
        return;
      }
      const bookmarkKey = key + "_bookmarks";
      let existing = [];
      const raw = window.localStorage.getItem(bookmarkKey);
      if (raw) {
        try {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) {
            existing = parsed;
          }
        } catch (error) {
          existing = [];
        }
      }

      const legacyKey = legacyBookmarkKey();
      if (legacyKey) {
        const legacyBookmark = window.localStorage.getItem(legacyKey);
        if (legacyBookmark && existing.indexOf(legacyBookmark) === -1) {
          existing.push(legacyBookmark);
        }
      }

      if (existing.indexOf(cfi) === -1) {
        existing.push(cfi);
        window.localStorage.setItem(bookmarkKey, JSON.stringify(existing));
        if (legacyKey) {
          window.localStorage.setItem(legacyKey, cfi);
        }
        showStatus(TEXT.bookmarkSaved);
      } else {
        showStatus(TEXT.bookmarkExists);
      }
    } catch (error) {
      showStatus(TEXT.bookmarkExists);
    }
  }

  function hideResume() {
    if (state.resumePopup) {
      state.resumePopup.classList.add(HIDDEN_CLASS);
    }
  }

  function showResume() {
    if (state.resumePopup) {
      state.resumePopup.classList.remove(HIDDEN_CLASS);
    } else {
      startDisplay(state.pendingLocation || null);
    }
  }

  function updateBuyLink(url) {
    if (!state.buyLink) {
      return;
    }

    if (url) {
      state.buyLink.href = url;
      state.buyLink.classList.remove("wrer-buy-btn--disabled");
    } else {
      state.buyLink.href = "#";
      state.buyLink.classList.add("wrer-buy-btn--disabled");
    }
  }

  function destroyRendition() {
    if (document.fullscreenElement && document.exitFullscreen) {
      try {
        document.exitFullscreen();
      } catch (error) {
        // Ignore exit fullscreen errors.
      }
    }

    clearStatusTimer();
    hideResume();

    if (state.rendition && typeof state.rendition.destroy === "function") {
      try {
        state.rendition.destroy();
      } catch (error) {
        // Ignore destruction errors.
      }
    }

    state.book = null;
    state.rendition = null;
    state.lastLocation = null;

    if (state.readerArea) {
      state.readerArea.innerHTML = "";
    }
  }

  function updateProgress(location) {
    if (location) {
      state.lastLocation = location;
    }

    if (!state.progress) {
      return;
    }

    if (!location || !location.start) {
      setProgressText(TEXT.loading);
      return;
    }

    const displayed = location.start.displayed;
    if (displayed && displayed.page && displayed.total) {
      setProgressText("Page " + displayed.page + " / " + displayed.total);
      return;
    }

    const cfi = location.start.cfi;
    if (state.book && state.book.locations && typeof state.book.locations.percentageFromCfi === "function" && cfi) {
      const percent = state.book.locations.percentageFromCfi(cfi);
      if (!Number.isNaN(percent)) {
        setProgressText("Page " + Math.round(percent * 100) + "%");
        return;
      }
    }

    setProgressText("Page updated");
  }

  function startDisplay(target) {
    if (!state.rendition) {
      return;
    }

    state.pendingLocation = null;
    try {
      state.rendition.display(target || undefined).then(function () {
        const current = state.rendition.currentLocation();
        if (current) {
          updateProgress(current);
        }
      });
    } catch (error) {
      // Ignore display errors.
    }
  }

  function handleResumeChoice(useSaved) {
    hideResume();

    if (!useSaved) {
      clearSavedLocation();
      startDisplay(null);
      return;
    }

    startDisplay(state.pendingLocation || null);
  }

  function ensureElements() {
    if (state.uiInitialized) {
      return;
    }

    state.wrapper = document.getElementById("wrer-reader-shell");
    state.readerArea = qs("wrer-reader");
    state.progress = qs("wrer-progress");
    state.prevBtn = qs("wrer-prev");
    state.nextBtn = qs("wrer-next");
    state.bookmarkBtn = qs("wrer-bookmark");
    state.fullscreenBtn = qs("wrer-fullscreen");
    state.resumePopup = qs("wrer-resume-popup");
    state.resumeYes = qs("wrer-resume-yes");
    state.resumeNo = qs("wrer-resume-no");
    state.buyLink = document.getElementById("wrer-buy-link");
    state.fullscreenTarget = state.readerArea || state.wrapper;

    if (!state.readerArea || !state.wrapper) {
      return;
    }

    if (state.prevBtn) {
      state.prevBtn.addEventListener("click", function () {
        if (state.rendition) {
          state.rendition.prev();
        }
      });
    }

    if (state.nextBtn) {
      state.nextBtn.addEventListener("click", function () {
        if (state.rendition) {
          state.rendition.next();
        }
      });
    }

    if (state.bookmarkBtn) {
      state.bookmarkBtn.addEventListener("click", function () {
        if (!state.rendition) {
          return;
        }
        const location = state.rendition.currentLocation();
        if (location && location.start && location.start.cfi) {
          saveBookmark(location.start.cfi);
        }
      });
    }

    if (state.fullscreenBtn) {
      state.fullscreenBtn.addEventListener("click", function () {
        const target = state.fullscreenTarget;
        if (!target) {
          return;
        }

        if (!document.fullscreenElement && target.requestFullscreen) {
          target.requestFullscreen().catch(function () {
            // Ignore fullscreen errors.
          });
        } else if (document.exitFullscreen) {
          document.exitFullscreen().catch(function () {
            // Ignore fullscreen errors.
          });
        }
      });
    }

    if (state.resumeYes) {
      state.resumeYes.addEventListener("click", function () {
        handleResumeChoice(true);
      });
    }

    if (state.resumeNo) {
      state.resumeNo.addEventListener("click", function () {
        handleResumeChoice(false);
      });
    }

    if (state.readerArea) {
      state.readerArea.addEventListener("touchstart", function (event) {
        if (!event.changedTouches || event.changedTouches.length === 0) {
          return;
        }
        state.touchStartX = event.changedTouches[0].screenX;
      });

      state.readerArea.addEventListener("touchend", function (event) {
        if (!event.changedTouches || event.changedTouches.length === 0) {
          return;
        }

        const touchEndX = event.changedTouches[0].screenX;
        const delta = touchEndX - state.touchStartX;
        if (!state.rendition) {
          return;
        }

        if (delta <= -SWIPE_THRESHOLD) {
          state.rendition.next();
        } else if (delta >= SWIPE_THRESHOLD) {
          state.rendition.prev();
        }
      });
    }

    document.addEventListener("fullscreenchange", function () {
      if (!state.fullscreenBtn) {
        return;
      }

      if (document.fullscreenElement) {
        state.fullscreenBtn.classList.add("wrer-btn--active");
      } else {
        state.fullscreenBtn.classList.remove("wrer-btn--active");
      }
    });

    state.uiInitialized = true;
  }

  function openBook(config) {
    if (!config || !config.epub) {
      return;
    }

    ensureElements();

    if (!state.readerArea || !state.wrapper) {
      return;
    }

    state.readerId = config.readerId || state.readerId || "";
    state.bookId = config.bookId || deriveBookId(config.epub, config.title || "");

    updateBuyLink(config.buyLink || "");

    destroyRendition();
    setProgressText(TEXT.loading);

    if (typeof window.ePub !== "function") {
      setProgressText(TEXT.libraryMissing);
      return;
    }

    try {
      state.book = window.ePub(config.epub);
    } catch (error) {
      setProgressText(TEXT.openFailed);
      return;
    }

    state.wrapper.classList.remove(HIDDEN_CLASS);

    try {
      state.rendition = state.book.renderTo(state.readerArea, {
        width: "100%",
        height: "100%",
        spread: config.spread || "auto",
      });
    } catch (error) {
      setProgressText(TEXT.openFailed);
      state.book = null;
      return;
    }

    state.pendingLocation = null;
    const savedLocation = config.autoOpen === false ? "" : getSavedLocation();

    if (savedLocation && !config.ignoreSavedLocation) {
      state.pendingLocation = savedLocation;
      showResume();
    } else {
      startDisplay(config.startLocation || null);
    }

    state.book.ready
      .then(function () {
        return state.book.locations.generate(1200);
      })
      .catch(function () {
        // Ignore generation errors.
      })
      .then(function () {
        if (!state.pendingLocation && state.rendition) {
          const current = state.rendition.currentLocation();
          if (current) {
            updateProgress(current);
          }
        }
      });

    state.rendition.on("relocated", function (location) {
      if (!location || !location.start || !location.start.cfi) {
        return;
      }
      updateProgress(location);
      saveLocation(location.start.cfi);
    });
  }

  function attachReadButtons() {
    const containers = document.querySelectorAll(".wrer-reader-container");
    containers.forEach(function (container) {
      const readerId = container.getAttribute("data-reader-id") || "";
      const buttons = container.querySelectorAll(".wrer-read-btn");
      buttons.forEach(function (button) {
        if (readerId) {
          button.dataset.reader = readerId;
        }
      });
    });
  }

  function wrerInitReader(epubUrl, readerId) {
    if (!epubUrl) return;

    const container = document.getElementById("wrer-reader");
    if (!container) return;

    const book = ePub(epubUrl);
    const rendition = book.renderTo(container, {
      width: "100%",
      height: "600px"
    });

    // Sayfa kaldığı yerden devam
    const lastLocation = localStorage.getItem(`wrer_location_${readerId}`);
    if (lastLocation) {
      rendition.display(lastLocation);
    } else {
      rendition.display();
    }

    rendition.on("relocated", function(location) {
      localStorage.setItem(`wrer_location_${readerId}`, location.start.cfi);
    });

    state.readerArea = container;
    state.readerId = readerId || state.readerId || "";
    if (!state.bookId) {
      state.bookId = deriveBookId(epubUrl || "", "");
    }
    state.book = book;
    state.rendition = rendition;

    book.ready
      .then(function () {
        if (book.locations && typeof book.locations.generate === "function") {
          return book.locations.generate(1200);
        }
        return null;
      })
      .catch(function () {
        // Ignore generation errors.
      })
      .then(function () {
        const current = rendition.currentLocation();
        if (current) {
          state.lastLocation = current;
          updateProgress(current);
        }
      });

    rendition.on("rendered", function () {
      const current = rendition.currentLocation();
      if (current) {
        state.lastLocation = current;
        updateProgress(current);
      }
    });

    rendition.on("relocated", function (location) {
      if (!location || !location.start || !location.start.cfi) {
        return;
      }
      state.lastLocation = location;
      saveLocation(location.start.cfi);
      updateProgress(location);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    ensureElements();
    attachReadButtons();

    const selector = document.getElementById("wrer-language");
    if (!selector) {
      return;
    }

    selector.addEventListener("change", function (event) {
      const selected = event.target.value;
      document.querySelectorAll(".wrer-book").forEach(function (book) {
        const lang = book.getAttribute("data-language");
        book.style.display = selected === "all" || selected === lang ? "flex" : "none";
      });
      try {
        window.localStorage.setItem("wrer_selected_lang", selected);
      } catch (error) {
        // Ignore storage errors.
      }
    });

    try {
      const saved = window.localStorage.getItem("wrer_selected_lang");
      if (saved && selector.querySelector('option[value="' + saved + '"]')) {
        selector.value = saved;
        selector.dispatchEvent(new Event("change"));
      }
    } catch (error) {
      // Ignore retrieval errors.
    }
  });

  function handleReadButton(btn) {
    if (!btn) {
      return;
    }

    const epubUrl = btn.dataset.epub;
    let readerId = btn.dataset.reader || "";
    if (!readerId) {
      const wrapper = btn.closest(".wrer-reader-container");
      if (wrapper && typeof wrapper.getAttribute === "function") {
        readerId = wrapper.getAttribute("data-reader-id") || "";
      }
    }

    if (!epubUrl) {
      window.alert("EPUB URL not found.");
      return;
    }

    window.console.log("Opening EPUB:", epubUrl);

    ensureElements();
    destroyRendition();

    const bookId = btn.dataset.bookId || deriveBookId(epubUrl, btn.dataset.title || "");
    state.readerId = readerId || state.readerId || "";
    state.bookId = bookId;
    state.pendingLocation = null;
    state.lastLocation = null;
    hideResume();

    updateBuyLink(btn.dataset.buy || "");
    setProgressText(TEXT.loading);

    if (state.wrapper) {
      state.wrapper.classList.remove(HIDDEN_CLASS);
    }

    const container = document.getElementById("wrer-reader");
    if (container) {
      container.innerHTML = '<div class="wrer-loading">Loading…</div>';
    }

    wrerInitReader(epubUrl, readerId);
  }

  // WRER Read Now Button Listener
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".wrer-read-btn");
    if (!btn) {
      return;
    }

    handleReadButton(btn);
  });

  window.wrerInitReader = wrerInitReader;
  window.wrerHandleReadButton = handleReadButton;
})();
