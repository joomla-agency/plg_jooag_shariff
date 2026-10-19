/**
 * JooAg Shariff 8: share windows, print, copy link and share counts – without libraries.
 * Without this script the buttons still work as plain links.
 *
 * @copyright  (C) 2014 - 2026 Joomla Agentur, newtastic GmbH
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
(() => {
  'use strict';

  // Older way, for browsers without the Clipboard API or when it is refused
  const copyBySelection = (text) => {
    const field = document.createElement('textarea');
    let copied = false;
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    try {
      copied = document.execCommand('copy');
    } catch (e) {
      copied = false;
    }
    document.body.removeChild(field);
    return copied;
  };

  const copy = (link) => {
    const item = link.closest('li');
    const done = () => {
      item.classList.add('shariff-copied');
      window.setTimeout(() => item.classList.remove('shariff-copied'), 1500);
    };
    const fallback = () => {
      if (copyBySelection(link.href)) {
        done();
      }
    };

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(link.href).then(done, fallback);
    } else {
      fallback();
    }
  };

  document.addEventListener('click', (event) => {
    const link = event.target.closest('.shariff .shariff-button a');

    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
      return;
    }

    const action = link.dataset.shariffAction;

    if (action === 'print') {
      event.preventDefault();
      window.print();
    } else if (action === 'copy') {
      event.preventDefault();
      copy(link);
    } else if (link.dataset.rel === 'popup') {
      event.preventDefault();
      window.open(link.href, '_blank', 'width=600,height=460,noopener,noreferrer');
    }
  });

  // Share counts (only when the counter is switched on in the plugin)
  const requests = new Map();
  const format = (count) => (count >= 1000 ? `${Math.round(count / 1000)}k` : String(count));

  document.querySelectorAll('.shariff[data-backend-url]').forEach((box) => {
    const url = `${box.dataset.backendUrl}&url=${encodeURIComponent(box.dataset.url)}&sig=${encodeURIComponent(box.dataset.sig || '')}`;

    if (!requests.has(url)) {
      requests.set(url, fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : null))
        .then((json) => (json && Array.isArray(json.data) && json.data[0]) || {})
        .catch(() => ({})));
    }

    requests.get(url).then((counts) => {
      Object.keys(counts).forEach((service) => {
        const count = parseInt(counts[service], 10);

        if (!(count > 0) || !/^[a-z-]+$/.test(service)) {
          return;
        }

        box.querySelectorAll(`.shariff-button.${service} a`).forEach((link) => {
          if (!link.querySelector('.share_count')) {
            const badge = document.createElement('span');
            badge.className = 'share_count';
            badge.textContent = format(count);
            link.appendChild(badge);
          }
        });
      });
    });
  });
})();
