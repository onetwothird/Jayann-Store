(function () {
  'use strict';

  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  function initStickyHeader() {
    var header = $('#siteHeader');
    if (!header) return;

    var ticking = false;
    var apply = function () {
      header.classList.toggle('is-stuck', window.scrollY > 8);
      ticking = false;
    };

    window.addEventListener('scroll', function () {
      if (!ticking) { window.requestAnimationFrame(apply); ticking = true; }
    }, { passive: true });

    apply();
  }

  function initDrawer() {
    var drawer = $('#mobileNav');
    var toggle = $('#navToggle');
    if (!drawer || !toggle) return;

    var panel = $('.drawer__panel', drawer);
    var lastFocus = null;

    function open() {
      lastFocus = document.activeElement;
      drawer.hidden = false;

      window.requestAnimationFrame(function () { drawer.classList.add('is-open'); });
      document.body.classList.add('is-locked');
      toggle.setAttribute('aria-expanded', 'true');
      var first = $('a, button, input', panel);
      if (first) first.focus();
    }

    function close() {
      drawer.classList.remove('is-open');
      document.body.classList.remove('is-locked');
      toggle.setAttribute('aria-expanded', 'false');
      window.setTimeout(function () { drawer.hidden = true; }, 260);
      if (lastFocus) lastFocus.focus();
    }

    toggle.addEventListener('click', function () {
      drawer.classList.contains('is-open') ? close() : open();
    });

    $$('[data-drawer-close]', drawer).forEach(function (btn) {
      btn.addEventListener('click', close);
    });

    drawer.addEventListener('click', function (e) {
      if (e.target === drawer) close();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) close();
    });
  }

  function initDropdowns() {
    $$('[data-dropdown]').forEach(function (root) {
      var toggle = $('[data-dropdown-toggle]', root);
      if (!toggle) return;

      function close() {
        root.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
      function open() {
        root.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
      }

      toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        root.classList.contains('is-open') ? close() : open();
      });

      document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) close();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
      });
    });
  }

  function initToasts() {
    var stack = $('#toastStack');
    if (!stack) return;

    function dismiss(toast) {
      if (!toast || toast.classList.contains('is-hiding')) return;
      toast.classList.add('is-hiding');
      window.setTimeout(function () { toast.remove(); }, 260);
    }

    stack.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-dismiss-toast]');
      if (btn) dismiss(btn.closest('.toast'));
    });

    $$('.toast', stack).forEach(function (toast, i) {
      window.setTimeout(function () { dismiss(toast); }, 4500 + i * 600);
    });

    var all = $$('.toast', stack);
    if (all.length > 4) all.slice(0, all.length - 4).forEach(dismiss);
  }

  function initToTop() {
    var btn = $('#toTop');
    if (!btn) return;

    var ticking = false;
    var apply = function () {
      btn.classList.toggle('is-visible', window.scrollY > 400);
      ticking = false;
    };

    window.addEventListener('scroll', function () {
      if (!ticking) { window.requestAnimationFrame(apply); ticking = true; }
    }, { passive: true });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    apply();
  }

  function initQtyInputs() {
    $$('input[type="number"][name="qty"]').forEach(function (input) {
      var min = input.min !== '' ? parseInt(input.min, 10) : 1;
      var max = input.max !== '' && input.max !== null ? parseInt(input.max, 10) : Infinity;

      input.addEventListener('change', function () {
        var value = parseInt(input.value, 10);
        if (isNaN(value)) value = min;
        input.value = Math.min(Math.max(value, min), max);
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });

      input.addEventListener('input', function () {
        var value = parseInt(input.value, 10);
        if (!isNaN(value) && (value < min || value > max)) {
          input.value = Math.min(Math.max(value, min), max);
        }
      });
    });
  }

  function initCartTotals() {
    var root = $('[data-cart]');
    if (!root) return;

    var totalEl   = $('[data-cart-total]');
    var countEl   = $('[data-cart-count-total]');
    var shipFill  = $('[data-ship-fill]');
    var shipText  = $('[data-ship-text]');
    var freeOver  = parseFloat(root.getAttribute('data-free-over') || '0');

    function money(n) {
      return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalc() {
      var subtotal = 0;
      var count = 0;

      $$('[data-line-unit]', root).forEach(function (input) {
        var qty = parseInt(input.value, 10);
        if (isNaN(qty) || qty < 1) qty = 1;
        var unit = parseFloat(input.getAttribute('data-line-unit'));
        var line = unit * qty;

        var lineEl = input.closest('[data-line]').querySelector('[data-line-total]');
        if (lineEl) lineEl.textContent = money(line);

        subtotal += line;
        count += qty;
      });

      var shipping = (subtotal > 0 && subtotal < freeOver)
        ? parseFloat(root.getAttribute('data-shipping-fee') || '0')
        : 0;
      var total = subtotal + shipping;

      if (totalEl)   totalEl.textContent = money(total);
      if (countEl)   countEl.textContent = count;

      var shipEl = $('[data-cart-shipping]');
      if (shipEl) shipEl.textContent = shipping > 0 ? money(shipping) : 'Free';

      if (shipFill && freeOver > 0) {
        var pct = Math.min(100, (subtotal / freeOver) * 100);
        shipFill.style.width = pct + '%';
        if (shipText) {
          shipText.textContent = subtotal >= freeOver
            ? 'You have unlocked free shipping.'
            : 'Add ' + money(freeOver - subtotal) + ' more for free shipping.';
        }
      }
    }

    $$('[data-line-unit]', root).forEach(function (input) {
      input.addEventListener('input', recalc);
    });

    recalc();
  }

  function initSlider() {
    var el = $('.hero .swiper');
    if (!el || typeof window.Swiper === 'undefined') return;

    new window.Swiper(el, {
      loop: true,
      speed: 600,
      autoplay: { delay: 3000, disableOnInteraction: false, pauseOnMouseEnter: true },
      pagination: { el: '.hero .swiper-pagination', clickable: true },
      navigation: { nextEl: '.hero .swiper-button-next', prevEl: '.hero .swiper-button-prev' },
      a11y: {
        prevSlideMessage: 'Previous slide',
        nextSlideMessage: 'Next slide',
        paginationBulletMessage: 'Go to slide {{index}}'
      }
    });
  }

  function initQuickView() {
    var modal = $('#quickView');
    if (!modal) return;

    var body = $('[data-qv-body]', modal);
    var lastFocus = null;

    function open() {
      lastFocus = document.activeElement;
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('is-locked');
      var closeBtn = $('[data-qv-close]', modal);
      if (closeBtn) closeBtn.focus();
    }

    function close() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('is-locked');
      if (lastFocus) lastFocus.focus();
    }

    function render(html) {
      body.innerHTML = html;
      open();
      initQtyInputs();

      var form = $('[data-qv-form]', body);
      if (form) form.addEventListener('submit', onQuickAdd);
    }

    function onQuickAdd(e) {
      e.preventDefault();
      var form = e.currentTarget;
      var btn = $('button[type="submit"]', form);
      var original = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; }

      fetch(form.action || window.location.pathname, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        redirect: 'follow'
      })
        .then(function (r) { return r.text().then(function (body) { return { r: r, body: body }; }); })
        .then(function (res) {

          if (/login\.php$/.test(res.r.url)) {
            window.location.href = res.r.url;
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          if (btn) { btn.disabled = false; btn.innerHTML = original; }
        });
    }

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-quick-view]');
      if (!trigger) return;
      e.preventDefault();

      var id = trigger.getAttribute('data-quick-view');
      body.innerHTML = '<div class="qv__loading"><i class="fa-solid fa-spinner fa-spin fa-2xl" style="color:var(--brand-500)"></i></div>';
      open();

      fetch('quick_view.php?partial=1&pid=' + encodeURIComponent(id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) { return r.text(); })
        .then(render)
        .catch(function () {
          body.innerHTML = '<div class="qv__loading"><p>Sorry, we could not load this product.</p></div>';
        });
    });

    modal.addEventListener('click', function (e) {
      if (e.target === modal || e.target.closest('[data-qv-close]')) close();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
    });
  }

  function initAccordions() {
    $$('[data-acc-btn]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        var panel = document.getElementById(btn.getAttribute('aria-controls'));
        btn.setAttribute('aria-expanded', String(!expanded));
        if (panel) panel.classList.toggle('is-open', !expanded);
      });
    });
  }

  function initAddToCartForms() {
    $$('[data-add-to-cart]').forEach(function (form) {
      form.addEventListener('submit', function () {
        var btn = $('button[type="submit"]', form);
        if (!btn) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        window.setTimeout(function () { btn.disabled = false; }, 4000);
      });
    });
  }

  function initCopy() {
    $$('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy');
        var done = function () {
          var original = btn.innerHTML;
          btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
          window.setTimeout(function () { btn.innerHTML = original; }, 1600);
        };
        if (navigator.clipboard) {
          navigator.clipboard.writeText(text).then(done).catch(function () {});
        }
      });
    });
  }

  function initAutoSubmit() {
    $$('form[data-auto-submit] select').forEach(function (select) {
      select.addEventListener('change', function () { select.form.submit(); });
    });
  }

  function initCatnavScroll() {
    var catnav = $('.catnav');
    var inner = $('.catnav__inner', catnav);
    if (!catnav || !inner) return;

    function updateIndicators() {
      var scrollLeft = inner.scrollLeft;
      var maxScroll = inner.scrollWidth - inner.clientWidth;
      var threshold = 4; // px tolerance

      catnav.classList.toggle('has-scroll-start', scrollLeft > threshold);
      catnav.classList.toggle('has-scroll-end', scrollLeft < maxScroll - threshold);
    }

    inner.addEventListener('scroll', updateIndicators, { passive: true });
    window.addEventListener('resize', updateIndicators);
    updateIndicators();
  }

  function initPasswordToggles() {
    $$('[data-toggle-password]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = $('input', btn.parentNode);
        if (!input) return;

        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(show));
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        $('i', btn).className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
      });
    });
  }

  function init() {
    initStickyHeader();
    initDrawer();
    initDropdowns();
    initToasts();
    initToTop();
    initQtyInputs();
    initCartTotals();
    initSlider();
    initQuickView();
    initAccordions();
    initAddToCartForms();
    initCopy();
    initAutoSubmit();
    initPasswordToggles();
    initCatnavScroll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.JayannStore = { initQtyInputs: initQtyInputs };
})();
