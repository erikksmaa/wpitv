import './bootstrap';

(function () {
  'use strict';

  // ===================== THEME TOGGLE =====================
  const root = document.documentElement;
  const THEME_KEY = 'wpitv-v2-theme';

  function applyTheme(theme) {
    if (theme === 'dark') {
      root.classList.add('dark');
      root.setAttribute('data-theme', 'dark');
    } else {
      root.classList.remove('dark');
      root.setAttribute('data-theme', 'light');
    }
    localStorage.setItem(THEME_KEY, theme);
  }

  // Initialize theme
  const savedTheme = localStorage.getItem(THEME_KEY);
  if (savedTheme === 'dark' || savedTheme === 'light') {
    applyTheme(savedTheme);
  } else {
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(prefersDark ? 'dark' : 'light');
  }

  let floatingScrollBound = false;

  function syncBackToTopVisibility() {
    const scrollY = window.scrollY || window.pageYOffset || 0;
    const visible = scrollY >= 400;

    document.querySelectorAll('[data-back-to-top]').forEach(function (button) {
      button.classList.toggle('is-visible', visible);
      button.setAttribute('aria-hidden', visible ? 'false' : 'true');
      button.setAttribute('tabindex', visible ? '0' : '-1');
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initInteractive();
  });

  // Re-run on Livewire navigation/morph if applicable
  document.addEventListener('livewire:navigated', () => {
    initInteractive();
  });

  function initInteractive() {
    // ===================== GLOBAL PUBLIC SEARCH =====================
    const globalSearch = document.querySelector('[data-global-search]');
    const searchTrigger = document.getElementById('searchBtn');
    if (globalSearch && searchTrigger && globalSearch.dataset.bound !== 'true') {
      globalSearch.dataset.bound = 'true';

      function getSearchInput() {
        return globalSearch.querySelector('[data-global-search-input]');
      }

      function resetSearch() {
        const searchInput = getSearchInput();
        if (!searchInput || searchInput.value === '') return;
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input', { bubbles: true }));
      }

      function closeSearch(restoreFocus = false) {
        globalSearch.classList.remove('is-open');
        globalSearch.setAttribute('aria-hidden', 'true');
        searchTrigger.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('global-search-open');
        resetSearch();
        if (restoreFocus) searchTrigger.focus();
      }

      function openSearch() {
        const mobilePanel = document.getElementById('mobilePanel');
        const burgerButton = document.getElementById('burgerBtn');
        mobilePanel?.classList.remove('open');
        burgerButton?.classList.remove('open');
        burgerButton?.setAttribute('aria-expanded', 'false');
        mobilePanel?.setAttribute('aria-hidden', 'true');

        globalSearch.classList.add('is-open');
        globalSearch.setAttribute('aria-hidden', 'false');
        searchTrigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('global-search-open');
        window.setTimeout(function () {
          getSearchInput()?.focus({ preventScroll: true });
        }, 60);
      }

      searchTrigger.addEventListener('click', openSearch);
      globalSearch.addEventListener('click', function (event) {
        if (event.target.closest('[data-global-search-close]') || event.target === globalSearch) {
          closeSearch(true);
        }
      });
      globalSearch.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          event.preventDefault();
          closeSearch(true);
        }
      });
    }

    // ===================== HOMEPAGE FEATURED VIDEO =====================
    document.querySelectorAll('[data-home-featured]').forEach(function (featured) {
      if (featured.dataset.bound === 'true') return;

      featured.dataset.bound = 'true';
      const section = featured.closest('.home-featured-hero');
      const player = featured.querySelector('[data-home-featured-player]');
      const frame = featured.querySelector('[data-home-featured-frame]');
      const poster = featured.querySelector('[data-home-featured-poster]');
      const playerLabel = featured.querySelector('[data-home-featured-player-label]');
      const status = featured.querySelector('[data-home-featured-status]');
      const detailLink = featured.querySelector('[data-home-featured-link]');
      const detailLinkLabel = featured.querySelector('[data-home-featured-link-label]');
      const metaPrimary = section?.querySelector('[data-home-featured-meta-primary]');
      const metaSecondary = section?.querySelector('[data-home-featured-meta-secondary]');
      const metaSecondaryWrap = section?.querySelector('[data-home-featured-meta-secondary-wrap]');
      const options = Array.from(featured.querySelectorAll('[data-home-video-option]'));

      if (!player || !frame || !poster || options.length === 0) return;

      function selectVideo(option, moveFocus = false) {
        const key = option.dataset.key;
        if (!key || featured.dataset.activeKey === key) {
          if (moveFocus) option.focus();
          return;
        }

        const title = option.dataset.title || 'Video WPiTV';
        const source = option.dataset.embedUrl
          ? option.dataset.embedUrl + '?playsinline=1&controls=1&rel=0&modestbranding=1'
          : '';

        if (!source) return;

        featured.dataset.activeKey = key;
        player.classList.add('is-switching');
        player.classList.remove('is-player-ready');

        options.forEach(function (item) {
          const active = item === option;
          item.classList.toggle('is-active', active);
          item.setAttribute('aria-selected', active ? 'true' : 'false');
          item.setAttribute('tabindex', active ? '0' : '-1');
        });

        poster.src = option.dataset.thumbnailUrl || '';
        poster.alt = 'Cuplikan ' + title;
        frame.title = title + ' — WPiTV';
        frame.dataset.src = source;
        frame.dataset.loaded = 'true';
        frame.dataset.loadKey = key;
        frame.src = source;

        if (playerLabel) playerLabel.textContent = option.dataset.label || 'Video unggulan';
        if (metaPrimary) metaPrimary.textContent = option.dataset.metaPrimary || title;

        const secondary = option.dataset.program
          ? 'Program: ' + option.dataset.program
          : (option.dataset.metaSecondary || '');
        if (metaSecondary) metaSecondary.textContent = secondary;
        metaSecondaryWrap?.classList.toggle('hidden', !secondary);

        if (detailLink) {
          const detailUrl = option.dataset.detailUrl || '';
          const externalUrl = option.dataset.externalUrl || '';
          detailLink.href = detailUrl || externalUrl;
          detailLink.classList.toggle('hidden', !detailUrl && !externalUrl);

          if (detailUrl) {
            detailLink.removeAttribute('target');
            detailLink.removeAttribute('rel');
            if (detailLinkLabel) detailLinkLabel.textContent = 'Lihat detail tayangan';
          } else {
            detailLink.target = '_blank';
            detailLink.rel = 'noopener noreferrer';
            if (detailLinkLabel) detailLinkLabel.textContent = 'Tonton di YouTube';
          }
        }

        if (status) status.textContent = 'Video aktif: ' + title;
        window.setTimeout(function () {
          player.classList.remove('is-switching');
        }, 180);

        if (moveFocus) option.focus();
      }

      if (!frame.dataset.switchBound) {
        frame.dataset.switchBound = 'true';
        frame.addEventListener('load', function () {
          if (frame.dataset.loadKey === featured.dataset.activeKey || !frame.dataset.loadKey) {
            player.classList.add('is-player-ready');
            player.classList.remove('is-switching');
          }
        });
      }

      options.forEach(function (option, index) {
        option.addEventListener('click', function () {
          selectVideo(option);
        });

        option.addEventListener('keydown', function (event) {
          let nextIndex = null;

          if (event.key === 'ArrowRight') nextIndex = (index + 1) % options.length;
          if (event.key === 'ArrowLeft') nextIndex = (index - 1 + options.length) % options.length;
          if (event.key === 'Home') nextIndex = 0;
          if (event.key === 'End') nextIndex = options.length - 1;

          if (nextIndex !== null) {
            event.preventDefault();
            selectVideo(options[nextIndex], true);
          }
        });
      });
    });

    // ===================== PROGRAM PRODUCTION CREW =====================
    document.querySelectorAll('[data-program-crew]').forEach(function (crew) {
      if (crew.dataset.bound === 'true') return;

      crew.dataset.bound = 'true';
      const tabs = Array.from(crew.querySelectorAll('[data-program-crew-tab]'));
      const panels = Array.from(crew.querySelectorAll('[data-program-crew-panel]'));
      const panelGroup = crew.querySelector('.program-crew-panels');

      if (tabs.length < 2 || panels.length === 0) return;

      function selectGroup(tab, moveFocus = false) {
        const groupId = tab.dataset.programCrewTab;
        if (!groupId) return;

        tabs.forEach(function (item) {
          const active = item === tab;
          item.classList.toggle('is-active', active);
          item.setAttribute('aria-selected', active ? 'true' : 'false');
          item.setAttribute('tabindex', active ? '0' : '-1');
        });

        panels.forEach(function (panel) {
          panel.hidden = panel.dataset.programCrewPanel !== groupId;
        });

        if (panelGroup) panelGroup.dataset.activeGroup = groupId;
        if (moveFocus) tab.focus();
      }

      tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () {
          selectGroup(tab);
        });

        tab.addEventListener('keydown', function (event) {
          let nextIndex = null;

          if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
          if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
          if (event.key === 'Home') nextIndex = 0;
          if (event.key === 'End') nextIndex = tabs.length - 1;

          if (nextIndex !== null) {
            event.preventDefault();
            selectGroup(tabs[nextIndex], true);
          }
        });
      });
    });

    // Keep the fast thumbnail visible during the first paint, then initialize
    // the single active YouTube player when the browser is idle. Playback is
    // never started automatically.
    document.querySelectorAll('[data-hero-video]').forEach(function (frame) {
      if (frame.dataset.scheduled === 'true') return;

      frame.dataset.scheduled = 'true';
      const loadPlayer = function () {
        if (!frame.isConnected || frame.dataset.loaded === 'true') return;
        frame.dataset.loaded = 'true';
        frame.addEventListener('load', function () {
          frame.closest('[data-home-featured-player], .hero-video-card, .video-player-wrap')?.classList.add('is-player-ready');
        }, { once: true });
        frame.src = frame.dataset.src;
      };
      const schedulePlayer = function () {
        if ('requestIdleCallback' in window) {
          window.requestIdleCallback(loadPlayer, { timeout: 2200 });
        } else {
          window.setTimeout(loadPlayer, 1200);
        }
      };

      if (document.readyState === 'complete') schedulePlayer();
      else window.addEventListener('load', schedulePlayer, { once: true });
    });

    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn && !themeBtn.dataset.bound) {
      themeBtn.dataset.bound = 'true';
      themeBtn.addEventListener('click', function () {
        const isDark = root.classList.contains('dark') || root.getAttribute('data-theme') === 'dark';
        applyTheme(isDark ? 'light' : 'dark');
      });
    }

    // ===================== HEADER SCROLL =====================
    const header = document.getElementById('siteHeader');
    if (header) {
      window.addEventListener('scroll', function () {
        header.classList.toggle('scrolled', window.scrollY > 10);
      }, { passive: true });
    }

    // ===================== TV CLUB NAVIGATION =====================
    document.querySelectorAll('[data-tvclub-nav]').forEach(function (dropdown) {
      if (dropdown.dataset.bound === 'true') return;

      dropdown.dataset.bound = 'true';
      const trigger = dropdown.querySelector('[data-tvclub-nav-trigger]');
      const menu = dropdown.querySelector('[data-tvclub-nav-menu]');
      if (!trigger || !menu) return;

      function setOpen(open, restoreFocus = false) {
        dropdown.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (restoreFocus) trigger.focus();
      }

      trigger.addEventListener('click', function () {
        setOpen(!dropdown.classList.contains('is-open'));
      });

      dropdown.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && dropdown.classList.contains('is-open')) {
          event.preventDefault();
          setOpen(false, true);
        }
      });

      document.addEventListener('click', function (event) {
        if (!dropdown.contains(event.target)) setOpen(false);
      });
    });

    document.querySelectorAll('[data-tvclub-mobile]').forEach(function (accordion) {
      if (accordion.dataset.bound === 'true') return;

      accordion.dataset.bound = 'true';
      const trigger = accordion.querySelector('[data-tvclub-mobile-trigger]');
      const menu = accordion.querySelector('[data-tvclub-mobile-menu]');
      if (!trigger || !menu) return;

      trigger.addEventListener('click', function () {
        const open = accordion.classList.toggle('is-open');
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.setAttribute('aria-hidden', open ? 'false' : 'true');
      });
    });

    // ===================== MOBILE MENU =====================
    const burger = document.getElementById('burgerBtn');
    const mPanel = document.getElementById('mobilePanel');
    if (burger && mPanel && !burger.dataset.bound) {
      burger.dataset.bound = 'true';
      burger.addEventListener('click', function () {
        const open = mPanel.classList.toggle('open');
        burger.classList.toggle('open', open);
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        mPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (!open) {
          mPanel.querySelectorAll('[data-tvclub-mobile]').forEach(function (accordion) {
            accordion.classList.remove('is-open');
            accordion.querySelector('[data-tvclub-mobile-trigger]')?.setAttribute('aria-expanded', 'false');
            accordion.querySelector('[data-tvclub-mobile-menu]')?.setAttribute('aria-hidden', 'true');
          });
        }
      });

      mPanel.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
          mPanel.classList.remove('open');
          burger.classList.remove('open');
          burger.setAttribute('aria-expanded', 'false');
          mPanel.setAttribute('aria-hidden', 'true');
        });
      });
    }

    // ===================== SCROLL REVEAL =====================
    if ('IntersectionObserver' in window) {
      const rvObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            e.target.classList.add('in-view');
            rvObs.unobserve(e.target);
          }
        });
      }, { threshold: 0.10 });

      document.querySelectorAll('[data-reveal]').forEach(function (el) {
        rvObs.observe(el);
      });
    } else {
      document.querySelectorAll('[data-reveal]').forEach(function (el) {
        el.classList.add('in-view');
      });
    }


    // ===================== COUNT UP ANIMATION =====================
    const counters = document.querySelectorAll('[data-count]');
    function doCount(el) {
      const target = parseInt(el.getAttribute('data-target'), 10);
      const suffix = el.getAttribute('data-suffix') || '';
      if (isNaN(target)) return;
      if (target > 1000) {
        el.textContent = target + suffix;
        return;
      }
      const dur = 1500;
      let start = null;
      function step(ts) {
        if (!start) start = ts;
        const p = Math.min((ts - start) / dur, 1);
        const e = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(e * target) + suffix;
        if (p < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }

    if (counters.length) {
      if ('IntersectionObserver' in window) {
        const cObs = new IntersectionObserver(function (entries) {
          entries.forEach(function (e) {
            if (e.isIntersecting) {
              doCount(e.target);
              cObs.unobserve(e.target);
            }
          });
        }, { threshold: 0.4 });
        counters.forEach(function (el) {
          cObs.observe(el);
        });
      } else {
        counters.forEach(function (el) {
          el.textContent = (el.getAttribute('data-target') || '0') + (el.getAttribute('data-suffix') || '');
        });
      }
    }

    // ===================== FLOATING BACK TO TOP =====================
    if (!floatingScrollBound) {
      floatingScrollBound = true;
      window.addEventListener('scroll', syncBackToTopVisibility, { passive: true });
    }
    syncBackToTopVisibility();

    document.querySelectorAll('[data-back-to-top]').forEach(function (button) {
      if (button.dataset.bound === 'true') return;

      button.dataset.bound = 'true';
      button.addEventListener('click', function () {
        const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({
          top: 0,
          behavior: reduced ? 'auto' : 'smooth',
        });
      });
    });

    // ===================== FLOATING VISITOR WIDGET =====================
    document.querySelectorAll('[data-visitor-widget]').forEach(function (widget) {
      if (widget.dataset.bound === 'true') return;

      widget.dataset.bound = 'true';
      const trigger = widget.querySelector('[data-visitor-widget-trigger]');
      const panel = widget.querySelector('[data-visitor-widget-panel]');
      if (!trigger || !panel) return;

      function setExpanded(expanded, restoreFocus = false) {
        widget.classList.toggle('is-expanded', expanded);
        trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        panel.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        if (restoreFocus) trigger.focus();
      }

      trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        const isOpen = widget.classList.contains('is-expanded');
        setExpanded(!isOpen);
      });

      widget.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && widget.classList.contains('is-expanded')) {
          event.preventDefault();
          setExpanded(false, true);
        }
      });

      document.addEventListener('click', function (event) {
        if (!widget.contains(event.target)) {
          setExpanded(false);
        }
      });
    });

    // Fallback error image
    document.querySelectorAll('img').forEach(function (img) {
      img.addEventListener('error', function () {
        if (this.dataset.fallback) {
          this.src = this.dataset.fallback;
        }
      });
    });
  }
})();
