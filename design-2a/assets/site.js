(function () {
  // every page opens at the top (unless the link points to a section on it)
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  function toTop() { if (!location.hash) { document.documentElement.style.scrollBehavior = 'auto'; window.scrollTo(0, 0); try { document.body.scrollIntoView({ block: 'start' }); } catch (e) {} document.documentElement.style.scrollBehavior = ''; } }
  toTop();
  window.addEventListener('load', toTop);
  window.addEventListener('pageshow', function (e) { if (e.persisted) toTop(); });

  var body = document.body, btn = document.getElementById('menu-btn'), veil = document.getElementById('veil'), word = document.getElementById('menu-word');
  function setMenu(on) {
    body.classList.toggle('menu-on', on);
    btn.setAttribute('aria-expanded', on ? 'true' : 'false');
    veil.setAttribute('aria-hidden', on ? 'false' : 'true');
    word.textContent = on ? 'Close' : 'Menu';
  }
  btn.addEventListener('click', function () { setMenu(!body.classList.contains('menu-on')); });
  veil.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

  // menu preview image follows the hovered link
  var imgs = veil.querySelectorAll('.preview img'), cap = document.getElementById('preview-cap'), links = veil.querySelectorAll('ol a');
  links.forEach(function (a) {
    a.addEventListener('mouseenter', function () {
      var i = Number(a.dataset.img);
      imgs.forEach(function (im, j) { im.classList.toggle('on', j === i); });
      links.forEach(function (x) { x.classList.toggle('on', x === a); });
      cap.textContent = a.dataset.cap;
    });
  });

  // header turns solid once the hero is behind it
  var bar = document.getElementById('top-bar'), hero = document.querySelector('.hero, .phero');
  function onScroll() {
    var limit = hero ? hero.offsetHeight - 90 : 0;
    bar.classList.toggle('solid', window.scrollY > limit);
  }
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

  // in-page sub nav highlights the section in view
  var sub = document.querySelector('.subnav');
  if (sub && 'IntersectionObserver' in window) {
    var subLinks = sub.querySelectorAll('a');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        subLinks.forEach(function (a) { a.classList.toggle('on', a.getAttribute('href') === '#' + en.target.id); });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    subLinks.forEach(function (a) { var t = document.querySelector(a.getAttribute('href')); if (t) io.observe(t); });
  }

  // forms are previews: validate email, show the thank-you line
  document.querySelectorAll('form[data-preview]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var em = f.querySelector('input[type=email]');
      if (em && !/^\S+@\S+\.\S+$/.test(em.value)) { em.focus(); return; }
      var ok = f.querySelector('.ok'); if (ok) ok.hidden = false;
    });
  });

  // save (bookmark) toggles
  document.querySelectorAll('.save').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.preventDefault();
      b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
    });
  });

  // resource rails: arrows + progress bar
  document.querySelectorAll('[data-rail]').forEach(function (box) {
    var rail = box.querySelector('.rail'), prev = box.querySelector('[data-prev]'), next = box.querySelector('[data-next]'), bar = box.querySelector('.rail-bar i');
    function update() {
      var max = rail.scrollWidth - rail.clientWidth;
      if (prev) prev.disabled = rail.scrollLeft <= 2;
      if (next) next.disabled = rail.scrollLeft >= max - 2;
      if (bar) { var w = rail.clientWidth / rail.scrollWidth; bar.style.width = (w * 100) + '%'; bar.style.left = (max ? rail.scrollLeft / max * (1 - w) * 100 : 0) + '%'; }
    }
    function step(dir) { var card = rail.firstElementChild; rail.scrollBy({ left: dir * (card.offsetWidth + 24), behavior: 'smooth' }); }
    if (prev) prev.addEventListener('click', function () { step(-1); });
    if (next) next.addEventListener('click', function () { step(1); });
    rail.addEventListener('scroll', update, { passive: true }); window.addEventListener('resize', update); update();
  });

  // resource hub filters
  var chips = document.querySelectorAll('.chip');
  if (chips.length) {
    var cards = document.querySelectorAll('.res-grid .rc');
    function filter(type) {
      chips.forEach(function (c) { var on = c.dataset.f === type; c.classList.toggle('on', on); c.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      cards.forEach(function (c) { c.hidden = !(type === 'all' || c.dataset.type === type); });
    }
    chips.forEach(function (c) { c.addEventListener('click', function () { filter(c.dataset.f); }); });
    var start = (location.hash.match(/^#type-(\w+)/) || [])[1];
    if (start && document.querySelector('.chip[data-f="' + start + '"]')) filter(start);
  }

  // meetup finder
  var q = document.getElementById('city-q');
  if (q) {
    var cities = document.querySelectorAll('.city'), empty = document.getElementById('city-empty');
    q.addEventListener('input', function () {
      var v = q.value.trim().toLowerCase(), n = 0;
      cities.forEach(function (c) { var hit = !v || c.textContent.toLowerCase().indexOf(v) > -1; c.hidden = !hit; if (hit) n++; });
      empty.hidden = n > 0;
    });
  }
})();
