const CACHE_NAME = 'familia-almeida-shopping-v1';
const LATEST_SHOPPING_PAGE = '/__familia_shopping_latest__';
const STATIC_ASSETS = [
  '/assets/style.css',
  '/assets/shopping.js',
  '/manifest.webmanifest'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type !== 'CACHE_SHOPPING_PAGE' || !data.url) return;

  event.waitUntil(
    fetch(data.url, { credentials: 'include', cache: 'no-store' })
      .then((response) => {
        if (!response.ok) return;
        return caches.open(CACHE_NAME).then((cache) => cache.put(LATEST_SHOPPING_PAGE, response.clone()));
      })
      .catch(() => {})
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin) return;

  if (request.mode === 'navigate' && url.pathname === '/compras/mercado') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(LATEST_SHOPPING_PAGE, clone));
          }
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(LATEST_SHOPPING_PAGE);
          if (cached) return cached;

          return new Response(
            '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lista offline</title><body style="font-family:system-ui;padding:32px;background:#f4f7f8;color:#173044"><h2>Lista ainda não disponível offline</h2><p>Abra o modo compra com internet pelo menos uma vez para salvar a lista neste aparelho.</p></body></html>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
          );
        })
    );
    return;
  }

  if (STATIC_ASSETS.includes(url.pathname)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const network = fetch(request)
          .then((response) => {
            if (response.ok) {
              const clone = response.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
            }
            return response;
          })
          .catch(() => cached);

        return cached || network;
      })
    );
  }
});

function openShoppingDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('familia-almeida-shopping', 1);

    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains('states')) {
        db.createObjectStore('states', { keyPath: 'key' });
      }
      if (!db.objectStoreNames.contains('outbox')) {
        db.createObjectStore('outbox', { keyPath: 'client_purchase_id' });
      }
    };

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function outboxAll() {
  const db = await openShoppingDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('outbox', 'readonly');
    const request = tx.objectStore('outbox').getAll();
    request.onsuccess = () => resolve(request.result || []);
    request.onerror = () => reject(request.error);
  });
}

async function deleteOutbox(id) {
  const db = await openShoppingDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('outbox', 'readwrite');
    tx.objectStore('outbox').delete(id);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

self.addEventListener('sync', (event) => {
  if (event.tag !== 'familia-shopping-sync') return;

  event.waitUntil((async () => {
    const queued = await outboxAll();

    for (const payload of queued) {
      try {
        const response = await fetch('/api/compras/sincronizar', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(payload)
        });

        const contentType = response.headers.get('content-type') || '';
        if (!response.ok || !contentType.includes('application/json')) continue;

        const body = await response.json();
        if (body.ok) {
          await deleteOutbox(payload.client_purchase_id);
        }
      } catch (_) {}
    }
  })());
});