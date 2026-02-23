(function () {
  function normalizeTags(tags) {
    if (Array.isArray(tags)) {
      return tags
        .map(tag => String(tag || '').trim())
        .filter(Boolean);
    }
    return [];
  }

  function normalizeImages(images) {
    if (!Array.isArray(images)) return [];
    return images
      .map(item => {
        if (typeof item === 'string') return item;
        if (item && typeof item.image === 'string') return item.image;
        return '';
      })
      .map(path => path.trim())
      .filter(Boolean);
  }

  CMS.registerEventListener({
    name: 'preSave',
    handler: ({ entry }) => {
      const data = entry.get('data');
      const items = data && data.get('items');

      if (!items || typeof items.toJS !== 'function') {
        return entry;
      }

      const normalizedItems = items
        .toJS()
        .map(item => {
          const normalized = { ...item };
          normalized.id = Number(item.id) || 0;
          normalized.title = String(item.title || '').trim();
          normalized.slug = String(item.slug || '').trim();
          normalized.date = String(item.date || '').trim();
          normalized.content = String(item.content || '').trim();
          normalized.body = String(item.body || '').trim();
          normalized.tags = normalizeTags(item.tags);
          normalized.images = normalizeImages(item.images);

          if (!normalized.image && normalized.images.length) {
            normalized.image = normalized.images[0];
          }

          return normalized;
        })
        .sort((first, second) => (Number(second.id) || 0) - (Number(first.id) || 0));

      const immutableItems = window.Immutable && typeof window.Immutable.fromJS === 'function'
        ? window.Immutable.fromJS(normalizedItems)
        : normalizedItems;

      const updatedData = entry.get('data').set('items', immutableItems);
      return entry.set('data', updatedData);
    }
  });
})();
