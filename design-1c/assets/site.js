(function () {
  // every page opens at the top (unless the link points to a section on it)
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  function toTop() { if (!location.hash) { document.documentElement.style.scrollBehavior = 'auto'; window.scrollTo(0, 0); try { document.body.scrollIntoView({ block: 'start' }); } catch (e) {} document.documentElement.style.scrollBehavior = ''; } }
  toTop();
  window.addEventListener('load', toTop);
  window.addEventListener('pageshow', function (e) { if (e.persisted) toTop(); });

  // Mega menu: full-screen panel, preview photo follows the hovered link
  var body = document.body, mbtn = document.getElementById('mega-btn'), mega = document.getElementById('mega'), mword = document.getElementById('mega-word');
  if (mbtn && mega) {
    var setMenu = function (on) {
      body.classList.toggle('mega-on', on);
      mbtn.setAttribute('aria-expanded', on ? 'true' : 'false');
      mega.setAttribute('aria-hidden', on ? 'false' : 'true');
      mword.textContent = on ? 'Close' : 'Menu';
    };
    mbtn.addEventListener('click', function () { setMenu(!body.classList.contains('mega-on')); });
    mega.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });
    var imgs = mega.querySelectorAll('.preview img'), cap = document.getElementById('preview-cap'), mlinks = mega.querySelectorAll('ol a');
    mlinks.forEach(function (a) {
      a.addEventListener('mouseenter', function () {
        var i = Number(a.dataset.img);
        imgs.forEach(function (im, k) { im.classList.toggle('on', k === i); });
        mlinks.forEach(function (x) { x.classList.toggle('on', x === a); });
        cap.textContent = a.dataset.cap;
      });
    });
  }

  // In-page nav sits just under the sticky header and highlights the section in view
  var head = document.querySelector('.site-header'), sub = document.querySelector('.subnav');
  if (sub && head) {
    var place = function () { sub.style.top = head.getBoundingClientRect().height + 'px'; };
    place(); window.addEventListener('resize', place);
    if ('IntersectionObserver' in window) {
      var links = sub.querySelectorAll('a');
      var io = new IntersectionObserver(function (en) {
        en.forEach(function (x) { if (x.isIntersecting) links.forEach(function (a) { a.classList.toggle('on', a.getAttribute('href') === '#' + x.target.id); }); });
      }, { rootMargin: '-40% 0px -55% 0px' });
      links.forEach(function (a) { var t = document.querySelector(a.getAttribute('href')); if (t) io.observe(t); });
    }
  }

  // Preview forms: no backend yet, just show the thank-you note
  document.querySelectorAll('form[data-preview]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var email = f.querySelector('input[type="email"]');
      if (email && !/^\S+@\S+\.\S+$/.test(email.value)) { email.focus(); return; }
      var ok = f.querySelector('.thanks'); if (ok) ok.hidden = false;
    });
  });

  // Save for later
  document.querySelectorAll('.save').forEach(function (b) {
    b.addEventListener('click', function () { b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true'); });
  });

  // Resource rails
  document.querySelectorAll('[data-rail]').forEach(function (box) {
    var rail = box.querySelector('.rail'), prev = box.querySelector('[data-prev]'), next = box.querySelector('[data-next]'), bi = box.querySelector('.rail-bar i');
    if (!rail) return;
    var update = function () {
      var max = rail.scrollWidth - rail.clientWidth;
      if (prev) prev.setAttribute('aria-disabled', rail.scrollLeft <= 2 ? 'true' : 'false');
      if (next) next.setAttribute('aria-disabled', rail.scrollLeft >= max - 2 ? 'true' : 'false');
      if (bi) { var w = rail.clientWidth / rail.scrollWidth; bi.style.width = (w * 100) + '%'; bi.style.left = (max ? rail.scrollLeft / max * (1 - w) * 100 : 0) + '%'; }
    };
    var step = function (d) { var c = rail.firstElementChild; rail.scrollBy({ left: d * (c.offsetWidth + 24), behavior: 'smooth' }); };
    if (prev) prev.addEventListener('click', function () { step(-1); });
    if (next) next.addEventListener('click', function () { step(1); });
    rail.addEventListener('scroll', update, { passive: true }); window.addEventListener('resize', update); update();
  });

  // Resource filters
  var chips = document.querySelectorAll('.chip[data-f]');
  if (chips.length) {
    var cards = document.querySelectorAll('.res-grid .rc');
    var filter = function (type) {
      chips.forEach(function (c) { var on = c.dataset.f === type; c.classList.toggle('on', on); c.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      cards.forEach(function (c) { c.hidden = !(type === 'all' || c.dataset.type === type); });
    };
    chips.forEach(function (c) { c.addEventListener('click', function () { filter(c.dataset.f); }); });
    var start = (location.hash.match(/^#type-(\w+)/) || [])[1];
    filter(start && document.querySelector('.chip[data-f="' + start + '"]') ? start : 'all');
    if (start) { var all = document.getElementById('all'); if (all) all.scrollIntoView(); }
  }

  // City search
  var q = document.getElementById('city-q');
  if (q) {
    var cities = document.querySelectorAll('.city'), empty = document.getElementById('city-empty');
    q.addEventListener('input', function () {
      var v = q.value.trim().toLowerCase(), n = 0;
      cities.forEach(function (c) { var hit = !v || c.textContent.toLowerCase().indexOf(v) > -1; c.hidden = !hit; if (hit) n++; });
      if (empty) empty.hidden = n > 0;
    });
  }
})();
