// Shared by the four media pages. Each page's #media-root says what to show:
//   data-type         which "type" from media.json (video, podcast, print, book)
//   data-placeholder  word drawn on the grey box when an item has no usable thumbnail
// The card markup (overlay symbol, labels) comes from the page's own <template>.
(function() {
  const tpl = document.getElementById('media-item-tpl');
  const root = document.getElementById('media-root');
  const type = root.dataset.type;

  const placeholder = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(`
    <svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 16 9">
      <rect width="16" height="9" fill="#ddd" />
      <text x="8" y="4.7" font-size="1.1" text-anchor="middle" fill="#666" font-family="Arial, Helvetica, sans-serif">${root.dataset.placeholder}</text>
    </svg>
  `);

  // Dates look like "September 2023"; some browsers only parse that as "1 September 2023"
  function parseDate(s) {
    if (!s) return null;
    const d = new Date(s);
    if (!isNaN(d)) return d;
    const d2 = new Date('1 ' + s);
    return isNaN(d2) ? null : d2;
  }

  function youTubeThumbnail(url) {
    const m = (url || '').match(/(?:v=|\/videos\/|embed\/|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
    return m ? `https://i.ytimg.com/vi/${m[1]}/hqdefault.jpg` : null;
  }

  function render(items) {
    if (!items.length) {
      root.innerHTML = `<div class="col-12"><div class="alert alert-info">No ${type} entries found in media.json.</div></div>`;
      return;
    }

    items.forEach(item => {
      const card = tpl.content.firstElementChild.cloneNode(true);
      card.querySelector('.card-title').textContent = item.title || '';
      card.querySelector('.media-meta').textContent = item.date || '';
      card.querySelector('.thumb-link').href = item.link || '#';

      // Try the item's own thumbnail, then YouTube's, then the grey placeholder
      const img = card.querySelector('.thumb-img');
      const fallbacks = [youTubeThumbnail(item.link), placeholder].filter(Boolean);
      img.loading = 'lazy';
      if (item.title) img.alt = `Thumbnail for ${item.title}`;
      img.addEventListener('error', function() {
        if (fallbacks.length) img.src = fallbacks.shift();
      });
      img.src = item.thumbnail || fallbacks.shift();

      root.appendChild(card);
    });
  }

  fetch('media.json')
    .then(r => { if (!r.ok) throw new Error('Failed to load media.json'); return r.json(); })
    .then(data => {
      const items = data.filter(it => it.type === type);
      items.forEach(it => { it.parsedDate = parseDate(it.date); });

      // Newest first; undated items last
      items.sort((a, b) => {
        if (a.parsedDate && b.parsedDate) return b.parsedDate - a.parsedDate;
        if (a.parsedDate) return -1;
        if (b.parsedDate) return 1;
        return 0;
      });

      render(items);
    })
    .catch(err => {
      root.innerHTML = '<div class="col-12"><div class="alert alert-warning">Unable to load media.json — check console for details.</div></div>';
      console.error(err);
    });
})();
