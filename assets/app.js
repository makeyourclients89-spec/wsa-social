/* -------------------------------------------------------------------------
   WSA Multi-Channel Publisher - Enterprise Client-Side Architecture
   Zero Inline JS, Fully Delegated, Accessible, MNC Standard
------------------------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
  // 1. Theme Toggle Controller (Crisp MNC SVG Icons)
  const themeBtn = document.getElementById('theme-toggle');
  const sunSvg = '<svg class="icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
  const moonSvg = '<svg class="icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

  if (themeBtn) {
    const slot = themeBtn.querySelector('.theme-icon-slot') || themeBtn;
    if (document.documentElement.getAttribute('data-theme') === 'dark') {
      slot.innerHTML = sunSvg;
    }
    themeBtn.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme');
      const next = cur === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('wsa_theme', next);
      slot.innerHTML = next === 'dark' ? sunSvg : moonSvg;
    });
  }

  // 2. Mobile Responsive Drawer Controller
  const navToggle = document.querySelector('.nav-toggle');
  const aside = document.querySelector('aside');
  if (navToggle && aside) {
    navToggle.addEventListener('click', () => {
      const isOpen = aside.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  // 3. Global Form Confirmation Handler (Replaces inline onsubmit)
  document.addEventListener('submit', (e) => {
    const form = e.target;
    const confirmMsg = form.getAttribute('data-confirm');
    if (confirmMsg) {
      if (!window.confirm(confirmMsg)) {
        e.preventDefault();
        e.stopPropagation();
      }
    }
  });

  // 4. One-Click Clipboard Copy Handler (Replaces inline onclick)
  document.addEventListener('click', async (e) => {
    const target = e.target.closest('[data-copy-text]');
    if (!target) return;
    const text = target.getAttribute('data-copy-text');
    if (!text) return;

    try {
      await navigator.clipboard.writeText(text);
      const originalText = target.innerText;
      target.innerText = 'Copied!';
      target.classList.add('copy-success');
      setTimeout(() => {
        target.innerText = originalText;
        target.classList.remove('copy-success');
      }, 1500);
    } catch (err) {
      console.error('Failed to copy to clipboard', err);
    }
  });

  // 5. Universal Instant Table Filter & Search Engine
  const filterInputs = document.querySelectorAll('[data-table-filter]');
  filterInputs.forEach((input) => {
    const targetTableId = input.getAttribute('data-table-filter');
    const table = document.getElementById(targetTableId);
    if (!table) return;

    input.addEventListener('input', () => {
      const query = input.value.toLowerCase().trim();
      const rows = table.querySelectorAll('tbody tr:not(.empty-row)');
      let visibleCount = 0;

      rows.forEach((row) => {
        const text = row.innerText.toLowerCase();
        const matches = text.includes(query);
        row.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
      });

      let noMatchRow = table.querySelector('.no-filter-match');
      if (visibleCount === 0 && query !== '') {
        if (!noMatchRow) {
          const colCount = table.querySelectorAll('thead th').length || 1;
          noMatchRow = document.createElement('tr');
          noMatchRow.className = 'no-filter-match';
          noMatchRow.innerHTML = `<td colspan="${colCount}" class="empty text-center text-muted">No records match "${input.value}"</td>`;
          table.querySelector('tbody').appendChild(noMatchRow);
        }
      } else if (noMatchRow) {
        noMatchRow.remove();
      }
    });
  });

  // 6. Live Character Counter & Social Network Constraints Meter
  const msgInput = document.getElementById('cmp_msg');
  const countDisplay = document.getElementById('char-counter-text');
  const platformBadges = document.querySelectorAll('.platform-limit-badge');

  if (msgInput && countDisplay) {
    const limits = {
      x: 280,
      instagram: 2200,
      threads: 500,
      linkedin: 3000,
      pinterest: 500
    };

    const updateCounts = () => {
      const len = msgInput.value.length;
      countDisplay.innerText = `${len} character${len === 1 ? '' : 's'}`;

      platformBadges.forEach((badge) => {
        const p = badge.getAttribute('data-platform');
        const max = limits[p];
        if (max) {
          const remaining = max - len;
          const statusText = badge.querySelector('.limit-status');
          if (remaining < 0) {
            badge.classList.add('badge-danger');
            badge.classList.remove('badge-safe');
            if (statusText) statusText.innerText = `${Math.abs(remaining)} over limit`;
          } else {
            badge.classList.remove('badge-danger');
            badge.classList.add('badge-safe');
            if (statusText) statusText.innerText = `${remaining} left`;
          }
        }
      });
    };

    msgInput.addEventListener('input', updateCounts);
    updateCounts(); // initialize on load
  }
});
