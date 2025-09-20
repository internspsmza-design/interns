document.addEventListener('DOMContentLoaded', () => {
  // Sidebar
  const btn = document.getElementById('hamburger');
  const sb  = document.getElementById('sidebar');
  if (btn && sb) btn.addEventListener('click', () => sb.classList.toggle('hidden'));

  // Theme
  const root = document.documentElement; // <html>
  const toggle = document.getElementById('theme-toggle');

  const apply = (t) => {
    root.setAttribute('data-theme', t);
    if (toggle) {
      // Show the opposite icon (sun when dark, moon when light)
      toggle.textContent = (t === 'dark') ? '🌞' : '🌙';
      toggle.setAttribute('aria-label', `Switch to ${(t === 'dark') ? 'light' : 'dark'} mode`);
      toggle.title = toggle.getAttribute('aria-label');
    }
  };

  // Initial: saved pref or system pref, default dark
  const saved = localStorage.getItem('theme');
  let start = saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
  apply(start);

  if (toggle) {
    toggle.addEventListener('click', () => {
      const next = (root.getAttribute('data-theme') === 'light') ? 'dark' : 'light';
      apply(next);
      localStorage.setItem('theme', next);
    });
  }
});
