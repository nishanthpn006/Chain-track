// ChainTrack – Core JavaScript
document.addEventListener('DOMContentLoaded', () => {

  // Auto-dismiss flash alerts after 4 seconds
  document.querySelectorAll('.ct-alert').forEach(el => {
    if (el.closest('#ct-topbar')) {
      setTimeout(() => {
        el.style.transition = 'opacity .5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
      }, 4000);
    }
  });

  // Confirm dangerous actions
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // Mobile sidebar toggle
  const toggleBtn = document.getElementById('sidebar-toggle');
  const sidebar   = document.getElementById('ct-sidebar');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  }

  // Active nav highlighting (fallback)
  const path = window.location.pathname;
  document.querySelectorAll('.ct-nav-item').forEach(link => {
    const href = link.getAttribute('href') || '';
    if (href && path.includes(href.split('/').slice(-2, -1)[0])) {
      link.classList.add('active');
    }
  });

});
