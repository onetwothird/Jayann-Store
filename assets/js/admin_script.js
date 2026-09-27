(function () {
  'use strict';

  const $  = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  function on(el, type, handler, opts) {
    if (el) el.addEventListener(type, handler, opts);
  }

  function initSidebar() {
    const sidebar = $('[data-sidebar]');
    const scrim   = $('[data-scrim]');
    const openers = $$('[data-sidebar-open]');
    if (!sidebar || !openers.length) return;

    const setOpen = (open) => {
      sidebar.classList.toggle('is-open', open);
      if (scrim) {
        scrim.classList.toggle('is-open', open);
        scrim.hidden = !open;
      }
      document.body.style.overflow = open ? 'hidden' : '';
      openers.forEach((b) => b.setAttribute('aria-expanded', String(open)));
    };

    openers.forEach((b) => on(b, 'click', () => setOpen(!sidebar.classList.contains('is-open'))));
    on(scrim, 'click', () => setOpen(false));
    on(document, 'keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
    $$('.sidebar__link', sidebar).forEach((a) => on(a, 'click', () => setOpen(false)));
  }

  const ICONS = {
    success: 'fa-circle-check',
    error:   'fa-circle-exclamation',
    warning: 'fa-triangle-exclamation',
    info:    'fa-circle-info'
  };

  function dismiss(toast) {
    if (!toast || toast.dataset.closing) return;
    toast.dataset.closing = '1';
    toast.classList.add('is-hiding');
    setTimeout(() => toast.remove(), 250);
  }

  function toast(message, type) {
    type = ICONS[type] ? type : 'info';
    let stack = $('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      stack.setAttribute('role', 'status');
      stack.setAttribute('aria-live', 'polite');
      document.body.appendChild(stack);
    }

    const el = document.createElement('div');
    el.className = 'toast toast--' + type;
    el.innerHTML =
      '<i class="fa-solid ' + ICONS[type] + '" aria-hidden="true"></i>' +
      '<span></span>' +
      '<button class="toast__close" type="button" aria-label="Dismiss">&times;</button>';
    el.querySelector('span').textContent = message;

    on(el.querySelector('.toast__close'), 'click', () => dismiss(el));
    stack.appendChild(el);
    setTimeout(() => dismiss(el), 5200);

    while (stack.children.length > 4) stack.firstElementChild.remove();
  }

  function initFlashes() {
    $$('[data-flash]').forEach((el) => {
      toast(el.dataset.flash, el.dataset.flashType);
      el.remove();
    });
  }

  function initPasswordToggles() {
    $$('[data-toggle-password]').forEach((btn) => {
      on(btn, 'click', () => {
        const input = document.getElementById(btn.dataset.togglePassword);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        btn.classList.toggle('is-on', show);
      });
    });
  }

  function initConfirms() {
    on(document, 'click', (e) => {
      const link = e.target.closest('a[data-confirm]');
      if (link && !window.confirm(link.dataset.confirm)) {
        e.preventDefault();
      }
    });

    on(document, 'submit', (e) => {
      const form = e.target;
      if (form.dataset && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        e.preventDefault();
      }
    });
  }

  function initForms() {

    $$('[data-auto-submit]').forEach((el) => on(el, 'change', () => el.form && el.form.submit()));

    $$('form[data-validate]').forEach((form) => {
      on(form, 'submit', (e) => {
        const bad = $$('[required]', form).find((field) => !String(field.value).trim());
        if (bad) {
          e.preventDefault();
          bad.focus();
          toast('Please fill in the highlighted field.', 'warning');
        }
      });
    });

    $$('[data-image-input]').forEach((input) => {
      on(input, 'change', () => {
        const file = input.files && input.files[0];
        const preview = document.getElementById(input.dataset.imageInput);
        if (!file || !preview) return;
        preview.innerHTML = '';
        const img = document.createElement('img');
        img.alt = '';
        img.src = URL.createObjectURL(file);
        on(img, 'load', () => URL.revokeObjectURL(img.src), { once: true });
        preview.appendChild(img);
      });
    });
  }

  function initFilters() {

    $$('[data-search]').forEach((input) => {
      on(input, 'keydown', (e) => {
        if (e.key === 'Enter' && input.form) input.form.submit();
      });
    });

    $$('[data-filter-param]').forEach((link) => {
      on(link, 'click', (e) => {
        e.preventDefault();
        const url = new URL(link.href, window.location.href);
        const params = new URLSearchParams(url.searchParams);
        const pairs = link.dataset.filterParam.split('&');
        pairs.forEach((pair) => {
          const [key, value] = pair.split('=');
          if (params.get(key) === decodeURIComponent(value || '')) {
            params.delete(key);
          } else {
            params.set(key, value);
          }
        });
        url.search = params.toString();
        window.location.href = url.toString();
      });
    });

    $$('[data-tabs]').forEach((group) => {
      const buttons = $$('[data-tab]', group);
      buttons.forEach((btn) => {
        on(btn, 'click', (e) => {
          e.preventDefault();
          buttons.forEach((b) => b.classList.toggle('is-active', b === btn));
          const scope = group.closest('[data-tabs-scope]') || document;
          $$('[data-panel]', scope).forEach((panel) => {
            panel.hidden = panel.dataset.panel !== btn.dataset.tab;
          });
        });
      });
    });

    $$('[data-strike]').forEach((el) => on(el, 'click', () => el.classList.toggle('is-done')));
  }

  function initSparkline() {
    const canvas = $('[data-sparkline]');
    if (!canvas || !canvas.dataset.values) return;

    const values = canvas.dataset.values.split(',').map(Number).filter((n) => !isNaN(n));
    if (values.length < 2) return;

    const dpr = window.devicePixelRatio || 1;
    const w = canvas.clientWidth || 260;
    const h = canvas.clientHeight || 64;
    canvas.width = w * dpr;
    canvas.height = h * dpr;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.scale(dpr, dpr);

    const max = Math.max.apply(null, values);
    const min = Math.min.apply(null, values);
    const span = max - min || 1;
    const step = w / (values.length - 1);
    const pt = (v, i) => [i * step, h - ((v - min) / span) * (h - 6) - 3];

    const gradient = ctx.createLinearGradient(0, 0, 0, h);
    gradient.addColorStop(0, 'rgba(238, 77, 45, .22)');
    gradient.addColorStop(1, 'rgba(238, 77, 45, 0)');

    ctx.beginPath();
    values.forEach((v, i) => {
      const [x, y] = pt(v, i);
      i ? ctx.lineTo(x, y) : ctx.moveTo(x, y);
    });
    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.closePath();
    ctx.fillStyle = gradient;
    ctx.fill();

    ctx.beginPath();
    values.forEach((v, i) => {
      const [x, y] = pt(v, i);
      i ? ctx.lineTo(x, y) : ctx.moveTo(x, y);
    });
    ctx.strokeStyle = '#ee4d2d';
    ctx.lineWidth = 2;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.stroke();

    const [lx, ly] = pt(values[values.length - 1], values.length - 1);
    ctx.beginPath();
    ctx.arc(lx, ly, 3.5, 0, Math.PI * 2);
    ctx.fillStyle = '#ee4d2d';
    ctx.fill();
  }

  function init() {
    initSidebar();
    initFlashes();
    initPasswordToggles();
    initConfirms();
    initForms();
    initFilters();
    initSparkline();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.adminToast = toast;
})();
