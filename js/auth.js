document.addEventListener('DOMContentLoaded', () => {
  animateStatCounters();
  initScrollReveal();
  initScrollProgress();
  initBackToTop();
  initFaqAccordion();
  initTiltCards();
  initHeroSpotlight();
  initPasswordToggles();
});

// Adds a show/hide eye button to every password field on the page.
const PW_EYE_SHOW_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
const PW_EYE_HIDE_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-6.06M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.22 4.44M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';

function initPasswordToggles() {
  document.querySelectorAll('input[type="password"]').forEach(input => {
    if (input.dataset.pwToggled) return;
    input.dataset.pwToggled = '1';

    let wrap = input.closest('.input-icon');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'pw-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
    }
    wrap.classList.add('has-toggle');

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle-btn';
    btn.setAttribute('aria-label', 'পাসওয়ার্ড দেখান/লুকান');
    btn.innerHTML = `<span class="pw-eye-show">${PW_EYE_SHOW_SVG}</span><span class="pw-eye-hide">${PW_EYE_HIDE_SVG}</span>`;
    btn.addEventListener('click', () => {
      const willShow = input.type === 'password';
      input.type = willShow ? 'text' : 'password';
      wrap.classList.toggle('pw-visible', willShow);
    });
    wrap.appendChild(btn);
  });
}

function animateStatCounters() {
  const els = document.querySelectorAll('[data-count]');
  if (!els.length) return;

  function runCounter(el) {
    const target = parseInt(el.dataset.count, 10) || 0;
    const suffix = el.dataset.suffix || '';
    const duration = 1100;
    const start = performance.now();
    function tick(now) {
      const p = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(eased * target).toLocaleString() + suffix;
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  if (typeof IntersectionObserver === 'undefined') {
    els.forEach(runCounter);
    return;
  }

  const obs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        runCounter(entry.target);
        obs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.4 });

  els.forEach(el => obs.observe(el));
}

// Fades sections in as they scroll into view
function initScrollReveal() {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;

  if (typeof IntersectionObserver === 'undefined') {
    els.forEach(el => el.classList.add('in-view'));
    return;
  }

  const obs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        obs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });

  els.forEach(el => obs.observe(el));
}

// Fills the top progress bar as the user scrolls
function initScrollProgress() {
  const fill = document.getElementById('scrollProgress');
  if (!fill) return;
  function update() {
    const scrollable = document.documentElement.scrollHeight - window.innerHeight;
    const pct = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 0;
    fill.style.width = pct + '%';
  }
  window.addEventListener('scroll', update, { passive: true });
  update();
}

// Back-to-top button, shown after scrolling a screen down
function initBackToTop() {
  const btn = document.getElementById('backToTop');
  if (!btn) return;
  window.addEventListener('scroll', () => {
    btn.classList.toggle('show', window.scrollY > window.innerHeight * 0.6);
  }, { passive: true });
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

// FAQ accordion — only one answer open at a time.
function initFaqAccordion() {
  const items = document.querySelectorAll('.faq-item');
  if (!items.length) return;
  items.forEach(item => {
    const btn = item.querySelector('.faq-question');
    btn.addEventListener('click', () => {
      const wasOpen = item.classList.contains('open');
      items.forEach(i => i.classList.remove('open'));
      if (!wasOpen) item.classList.add('open');
    });
  });
}

// 3D tilt on cards, disabled on touch devices
function initTiltCards() {
  if (window.matchMedia('(hover: none)').matches) return;
  document.querySelectorAll('.tilt').forEach(card => {
    card.addEventListener('mouseenter', () => { card.style.transition = 'none'; });
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const px = (e.clientX - rect.left) / rect.width - 0.5;
      const py = (e.clientY - rect.top) / rect.height - 0.5;
      card.style.transform = `perspective(700px) rotateX(${(-py * 7).toFixed(2)}deg) rotateY(${(px * 7).toFixed(2)}deg) translateY(-4px)`;
    });
    card.addEventListener('mouseleave', () => {
      card.style.transition = 'transform .4s ease';
      card.style.transform = '';
    });
  });
}

// Background glow follows the cursor on the login hero
function initHeroSpotlight() {
  const side = document.querySelector('.auth-form-side');
  if (!side || window.matchMedia('(hover: none)').matches) return;
  side.addEventListener('mousemove', (e) => {
    const rect = side.getBoundingClientRect();
    side.style.setProperty('--mx', ((e.clientX - rect.left) / rect.width * 100) + '%');
    side.style.setProperty('--my', ((e.clientY - rect.top) / rect.height * 100) + '%');
  });
}
