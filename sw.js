const CACHE_NAME = 'familia-almeida-shopping-v5';
const OFFLINE_SHOPPING_PAGE = '/compras-offline.html';
const STATIC_PATHS = new Set([
  '/compras-offline.html',
  '/assets/style.css',
  '/assets/shopping.js',
  '/assets/shopping-offline.js',
  '/manifest.webmanifest'
]);

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(Array.from(STATIC_PATHS)))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith('familia-almeida-shopping-') && key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

async function networkFirst(request, fallbackRequest = request) {
  try {
    const response = await fetch(request, { cache: 'no-store' });

    if (response && response.ok) {
      const cache = await caches.open(CACHE_NAME);
      await cache.put(fallbackRequest, response.clone());
    }

    return response;
  } catch (_) {
    const cached = await caches.match(fallbackRequest);
    if (cached) return cached;
    throw _;
  }
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin) return;

  // A página offline continua pública e sem autenticação.
  // Com internet, busca a versão nova primeiro; sem internet, usa o cache.
  if (request.mode === 'navigate' && (url.pathname === '/compras/offline' || url.pathname === '/compras-offline.html')) {
    event.respondWith(
      networkFirst(new Request(OFFLINE_SHOPPING_PAGE, { credentials: 'same-origin' }), OFFLINE_SHOPPING_PAGE)
        .catch(() => new Response(
          '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;padding:32px"><h2>Lista offline indisponível</h2><p>Abra a lista com internet uma vez para preparar este aparelho.</p></body></html>',
          { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        ))
    );
    return;
  }

  // A tela autenticada atualiza o snapshot. Se a conexão cair,
  // abre a página offline pública.
  if (request.mode === 'navigate' && url.pathname === '/compras/mercado') {
    event.respondWith(
      fetch(request, { cache: 'no-store' })
        .catch(() => caches.match(OFFLINE_SHOPPING_PAGE))
        .then((response) => response || caches.match(OFFLINE_SHOPPING_PAGE))
    );
    return;
  }

  // Arquivos do modo compra usam network-first para evitar JS/CSS antigo
  // preso no celular após uma atualização.
  if (STATIC_PATHS.has(url.pathname)) {
    event.respondWith(
      networkFirst(request, request)
        .catch(() => caches.match(request))
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
      } catch (_) {
        // Mantém a compra na fila para a próxima oportunidade de sincronização.
      }
    }
  })());
});