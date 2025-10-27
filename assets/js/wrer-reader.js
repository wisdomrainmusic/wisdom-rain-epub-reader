/**
 * WRER Reader Initialization Script
 * Handles EPUB loading, rendering, and session resume
 */

document.addEventListener("DOMContentLoaded", function() {
  const readerView = document.getElementById("wrer-reader-view");
  if (!readerView) {
    console.error("❌ WRER Reader container not found.");
    return;
  }

  const epubUrl = readerView.getAttribute("data-epub-url");
  const readerId = readerView.getAttribute("data-reader-id") || "";
  const storageKey = readerId ? `wrer-last-location-${readerId}` : "wrer-last-location";
  if (!epubUrl) {
    console.error("❌ No EPUB URL provided in #wrer-reader-view element.");
    return;
  }

  if (typeof ePub !== "function") {
    console.error("❌ ePub.js library is not available.");
    return;
  }

  console.log("📖 Opening EPUB:", epubUrl);

  try {
    const book = ePub(epubUrl, { openAs: "epub" });

    book.ready
      .then(() => {
        console.log("✅ EPUB loaded successfully.");
        const rendition = book.renderTo("wrer-reader-view", {
          width: "100%",
          height: "100%",
          spread: "always"
        });

        // Resume from last location if exists
        const savedLoc = localStorage.getItem(storageKey);
        if (savedLoc) {
          console.log("🔁 Resuming from saved location:", savedLoc);
          rendition.display(savedLoc);
        } else {
          rendition.display();
        }

        // Save location on page turn
        rendition.on("relocated", (location) => {
          if (location && location.start && location.start.cfi) {
            localStorage.setItem(storageKey, location.start.cfi);
          }
        });

        // Simple navigation controls
        const nextBtn = document.getElementById("next");
        const prevBtn = document.getElementById("prev");
        if (nextBtn) nextBtn.onclick = () => rendition.next();
        if (prevBtn) prevBtn.onclick = () => rendition.prev();

      })
      .catch(err => {
        console.error("⚠️ EPUB render error:", err);
      });

  } catch (err) {
    console.error("🚫 WRER Reader failed to initialize:", err);
  }
});
