// Light/dark mode toggle
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.theme-toggle-btn, .theme-toggle-btn-light').forEach(btn => {
    btn.addEventListener('click', () => {
      const isLight = document.documentElement.getAttribute('data-theme') === 'light';
      if (isLight) {
        document.documentElement.removeAttribute('data-theme');
        try { localStorage.setItem('reliefx_theme', 'dark'); } catch (e) {}
      } else {
        document.documentElement.setAttribute('data-theme', 'light');
        try { localStorage.setItem('reliefx_theme', 'light'); } catch (e) {}
      }
    });
  });
});
