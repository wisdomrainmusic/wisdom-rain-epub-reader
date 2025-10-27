/**
 * WRER PDF Reader
 * Handles PDF.js rendering with resume and navigation controls.
 */
(function () {
  const DEFAULT_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  const MIN_SCALE = 0.75;
  const MAX_SCALE = 2.5;
  const SCALE_STEP = 0.15;
  const storage = createSafeStorage();

  document.addEventListener('DOMContentLoaded', () => {
    const containers = document.querySelectorAll('.wrer-reader-item[data-src], .wrer-container[data-src]');
    if (!containers.length) {
      return;
    }

    containers.forEach((container) => {
      const src = container.getAttribute('data-src');
      if (!src || !/\.pdf(\?|#|$)/i.test(src)) {
        return;
      }

      initializeViewer(container, src).catch((error) => {
        console.error('WRER PDF viewer failed to initialize:', error);
        setStatus(container, '⚠️ ' + (error && error.message ? error.message : 'Unable to load document.'));
      });
    });
  });

  function setStatus(container, message) {
    const status = container.querySelector('.wrer-status');
    if (!status) {
      return;
    }

    status.hidden = !message;
    status.textContent = message || '';
  }

  async function loadPdfJs(workerSrc) {
    if (window.pdfjsLib) {
      if (workerSrc) {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = workerSrc;
      }
      return window.pdfjsLib;
    }

    await new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
      script.async = true;
      script.onload = resolve;
      script.onerror = () => reject(new Error('Failed to load PDF.js library.'));
      document.head.appendChild(script);
    });

    if (!window.pdfjsLib) {
      throw new Error('PDF.js library did not initialize correctly.');
    }

    window.pdfjsLib.GlobalWorkerOptions.workerSrc = workerSrc || DEFAULT_WORKER;
    return window.pdfjsLib;
  }

  async function initializeViewer(container, src) {
    const workerSrc = container.getAttribute('data-worker') || DEFAULT_WORKER;
    const resumeKey = container.getAttribute('data-resume-key') || `wrer-pdf-resume-${hashString(src)}`;
    const canvas = container.querySelector('.wrer-canvas');
    const currentPageEl = container.querySelector('.wrer-current-page');
    const totalPagesEl = container.querySelector('.wrer-total-pages');
    const zoomIndicator = container.querySelector('.wrer-zoom-indicator');
    const prevBtn = container.querySelector('.wrer-prev');
    const nextBtn = container.querySelector('.wrer-next');
    const zoomInBtn = container.querySelector('.wrer-zoom-in');
    const zoomOutBtn = container.querySelector('.wrer-zoom-out');

    if (!canvas) {
      throw new Error('Canvas element not found.');
    }

    setStatus(container, 'Loading document…');

    const pdfjsLib = await loadPdfJs(workerSrc);
    const loadingTask = pdfjsLib.getDocument({ url: src });
    const pdf = await loadingTask.promise;

    let currentPage = clampPage(readStoredPage(resumeKey) || 1, pdf.numPages);
    let scale = 1;
    let isRendering = false;
    let pendingPage = null;
    let pendingForce = false;

    totalPagesEl && (totalPagesEl.textContent = String(pdf.numPages));
    zoomIndicator && (zoomIndicator.textContent = `${Math.round(scale * 100)}%`);

    async function renderPage(pageNumber) {
      isRendering = true;
      setStatus(container, 'Rendering…');
      toggleButtons(true);

      try {
        const page = await pdf.getPage(pageNumber);
        const viewport = page.getViewport({ scale });
        const outputScale = window.devicePixelRatio || 1;

        canvas.width = Math.floor(viewport.width * outputScale);
        canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = `${viewport.width}px`;
        canvas.style.height = `${viewport.height}px`;

        const context = canvas.getContext('2d', { alpha: false });

        const renderContext = {
          canvasContext: context,
          viewport,
        };

        if (outputScale !== 1) {
          renderContext.transform = [outputScale, 0, 0, outputScale, 0, 0];
        }

        await page.render(renderContext).promise;

        currentPage = pageNumber;
        currentPageEl && (currentPageEl.textContent = String(currentPage));
        writeStoredPage(resumeKey, String(currentPage));
        setStatus(container, '');
      } catch (error) {
        console.error('WRER PDF render error:', error);
        setStatus(container, '⚠️ Failed to render page.');
      } finally {
        isRendering = false;
        toggleButtons(false);

        if (pendingPage !== null) {
          const nextPage = pendingPage;
          const nextForce = pendingForce;
          pendingPage = null;
          pendingForce = false;
          queueRenderPage(nextPage, nextForce);
        }
      }
    }

    function toggleButtons(disabled) {
      [prevBtn, nextBtn, zoomInBtn, zoomOutBtn].forEach((button) => {
        if (button) {
          button.disabled = disabled;
        }
      });

      if (!disabled) {
        prevBtn && (prevBtn.disabled = currentPage <= 1);
        nextBtn && (nextBtn.disabled = currentPage >= pdf.numPages);
        zoomOutBtn && (zoomOutBtn.disabled = scale <= MIN_SCALE + 0.01);
        zoomInBtn && (zoomInBtn.disabled = scale >= MAX_SCALE - 0.01);
      }
    }

    function queueRenderPage(targetPage, force = false) {
      const pageNumber = clampPage(targetPage, pdf.numPages);
      if (!force && pageNumber === currentPage && !isRendering) {
        return;
      }

      if (isRendering) {
        pendingPage = pageNumber;
        pendingForce = force || pendingForce;
        return;
      }

      renderPage(pageNumber);
    }

    function changePage(offset) {
      queueRenderPage(currentPage + offset);
    }

    function updateZoom(nextScale) {
      const newScale = clamp(nextScale, MIN_SCALE, MAX_SCALE);
      if (Math.abs(newScale - scale) < 0.01) {
        return;
      }

      scale = newScale;
      zoomIndicator && (zoomIndicator.textContent = `${Math.round(scale * 100)}%`);
      queueRenderPage(currentPage, true);
    }

    prevBtn && prevBtn.addEventListener('click', () => changePage(-1));
    nextBtn && nextBtn.addEventListener('click', () => changePage(1));
    zoomOutBtn && zoomOutBtn.addEventListener('click', () => updateZoom(scale - SCALE_STEP));
    zoomInBtn && zoomInBtn.addEventListener('click', () => updateZoom(scale + SCALE_STEP));

    document.addEventListener('keydown', (event) => {
      if (!container.contains(document.activeElement)) {
        return;
      }

      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        changePage(-1);
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        changePage(1);
      } else if ((event.ctrlKey || event.metaKey) && (event.key === '+' || event.key === '=')) {
        event.preventDefault();
        updateZoom(scale + SCALE_STEP);
      } else if ((event.ctrlKey || event.metaKey) && (event.key === '-' || event.key === '_')) {
        event.preventDefault();
        updateZoom(scale - SCALE_STEP);
      }
    });

    await renderPage(currentPage);
  }

  function clampPage(value, total) {
    if (Number.isNaN(value) || value < 1) {
      return 1;
    }
    if (value > total) {
      return total;
    }
    return value;
  }

  function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }

  function hashString(value) {
    let hash = 0;
    for (let i = 0; i < value.length; i += 1) {
      hash = (hash << 5) - hash + value.charCodeAt(i);
      hash |= 0; // Convert to 32bit integer
    }
    return Math.abs(hash).toString(16);
  }

  function createSafeStorage() {
    try {
      const { localStorage } = window;
      const testKey = '__wrer_test__';
      localStorage.setItem(testKey, '1');
      localStorage.removeItem(testKey);
      return localStorage;
    } catch (error) {
      console.warn('WRER PDF viewer: localStorage is not available.', error);
      return null;
    }
  }

  function readStoredPage(key) {
    if (!storage) {
      return null;
    }

    try {
      const value = storage.getItem(key);
      const parsed = parseInt(value, 10);
      return Number.isFinite(parsed) ? parsed : null;
    } catch (error) {
      console.warn('WRER PDF viewer: failed to read resume state.', error);
      return null;
    }
  }

  function writeStoredPage(key, value) {
    if (!storage) {
      return;
    }

    try {
      storage.setItem(key, value);
    } catch (error) {
      console.warn('WRER PDF viewer: failed to persist resume state.', error);
    }
  }
})();
