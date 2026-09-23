(() => {
  const config = window.shoppingOfflineConfig;
  if (!config) return;

  const DB_NAME = 'familia-almeida-shopping';
  const DB_VERSION = 1;
  const STATE_STORE = 'states';
  const OUTBOX_STORE = 'outbox';
  const ACTIVE_KEY = 'familia-almeida-active-shopping-state';
  const stateKey = 'market:' + String(config.listId);

  const money = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });

  const rows = Array.from(document.querySelectorAll('[data-shopping-item]'));
  const connectionStatus = document.getElementById('shopping-connection-status');
  const offlineBanner = document.getElementById('shopping-offline-banner');
  const pendingBanner = document.getElementById('shopping-pending-sync-banner');
  const selectedCount = document.getElementById('shopping-selected-count');
  const selectedEstimated = document.getElementById('shopping-selected-estimated');
  const selectedActual = document.getElementById('shopping-selected-actual');
  const footerTotal = document.getElementById('shopping-footer-total');
  const finalizeButton = document.getElementById('shopping-finalize-button');

  let dbPromise;
  let state = {
    key: stateKey,
    listId: config.listId,
    month: config.month,
    updatedAt: Date.now(),
    items: {}
  };

  function openDb() {
    if (dbPromise) return dbPromise;

    dbPromise = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(STATE_STORE)) {
          db.createObjectStore(STATE_STORE, { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains(OUTBOX_STORE)) {
          db.createObjectStore(OUTBOX_STORE, { keyPath: 'client_purchase_id' });
        }
      };

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });

    return dbPromise;
  }

  async function idbGet(storeName, key) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readonly');
      const request = tx.objectStore(storeName).get(key);
      request.onsuccess = () => resolve(request.result || null);
      request.onerror = () => reject(request.error);
    });
  }

  async function idbGetAll(storeName) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readonly');
      const request = tx.objectStore(storeName).getAll();
      request.onsuccess = () => resolve(request.result || []);
      request.onerror = () => reject(request.error);
    });
  }

  async function idbPut(storeName, value) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readwrite');
      tx.objectStore(storeName).put(value);
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }

  async function idbDelete(storeName, key) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readwrite');
      tx.objectStore(storeName).delete(key);
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }

  function serverState() {
    const items = {};

    (config.items || []).forEach((item) => {
      items[String(item.id)] = {
        id: Number(item.id),
        name: String(item.name || ''),
        quantity: Number(item.quantity || 0),
        estimated_price: Number(item.estimated_price || 0),
        selected: false,
        purchased_price: item.purchased_price || '',
        store_name: item.store_name || '',
        purchased: Boolean(item.purchased),
        pending_sync: false,
        pending_purchase_id: null
      };
    });

    return {
      key: stateKey,
      listId: config.listId,
      month: config.month,
      csrfToken: config.csrfToken || '',
      syncUrl: config.syncUrl || '/api/compras/sincronizar',
      updatedAt: Date.now(),
      items
    };
  }

  function mergeWithServer(localState) {
    const server = serverState();

    if (!localState || localState.listId !== config.listId) {
      return server;
    }

    Object.entries(server.items).forEach(([id, serverItem]) => {
      const local = localState.items?.[id];

      if (serverItem.purchased) {
        server.items[id] = serverItem;
        return;
      }

      if (local) {
        server.items[id] = {
          ...serverItem,
          selected: Boolean(local.selected),
          purchased_price: local.purchased_price ?? '',
          store_name: local.store_name ?? '',
          pending_sync: Boolean(local.pending_sync),
          pending_purchase_id: local.pending_purchase_id || null
        };
      }
    });

    server.csrfToken = config.csrfToken || localState.csrfToken || '';
    server.syncUrl = config.syncUrl || localState.syncUrl || '/api/compras/sincronizar';

    return server;
  }

  async function saveState() {
    state.updatedAt = Date.now();
    try {
      localStorage.setItem(ACTIVE_KEY, state.key);
      await idbPut(STATE_STORE, state);
    } catch (_) {}
  }

  function rowState(row) {
    const id = String(row.dataset.itemId);
    if (!state.items[id]) {
      state.items[id] = {
        id: Number(id),
        name: row.querySelector('.shopping-live-product strong')?.textContent?.trim() || '',
        quantity: Number(row.dataset.quantity || 0),
        estimated_price: Number(row.dataset.estimatedPrice || 0),
        selected: false,
        purchased_price: '',
        store_name: '',
        purchased: false,
        pending_sync: false
      };
    }
    return state.items[id];
  }

  function applyRowState(row) {
    const item = rowState(row);
    const checkbox = row.querySelector('[data-field="selected"]');
    const price = row.querySelector('[data-field="purchased_price"]');
    const store = row.querySelector('[data-field="store_name"]');
    const fields = row.querySelector('[data-purchase-fields]');
    const lock = row.querySelector('[data-sync-lock]');

    checkbox.checked = Boolean(item.selected || item.pending_sync);
    price.value = item.purchased_price || '';
    store.value = item.store_name || '';

    fields.hidden = !checkbox.checked;
    lock.hidden = !item.pending_sync;

    checkbox.disabled = Boolean(item.pending_sync);
    price.disabled = Boolean(item.pending_sync);
    store.disabled = Boolean(item.pending_sync);

    row.classList.toggle('selected', checkbox.checked);
    row.classList.toggle('pending-sync', Boolean(item.pending_sync));

    updateLineTotal(row);
  }

  function updateLineTotal(row) {
    const priceInput = row.querySelector('[data-field="purchased_price"]');
    const totalNode = row.querySelector('[data-line-total]');
    const quantity = Number(row.dataset.quantity || 0);
    const price = Number(priceInput.value || 0);
    totalNode.textContent = money.format(quantity * price);
  }

  function updateTotals() {
    let count = 0;
    let estimated = 0;
    let actual = 0;
    let hasPendingSync = false;

    rows.forEach((row) => {
      const item = rowState(row);
      if (!item.selected && !item.pending_sync) return;

      count += 1;
      const quantity = Number(row.dataset.quantity || 0);
      const approximate = Number(row.dataset.estimatedPrice || 0);
      const purchased = Number(item.purchased_price || 0);

      estimated += quantity * approximate;
      actual += quantity * purchased;
      hasPendingSync = hasPendingSync || Boolean(item.pending_sync);
    });

    selectedCount.textContent = String(count);
    selectedEstimated.textContent = money.format(estimated);
    selectedActual.textContent = money.format(actual);
    footerTotal.textContent = money.format(actual);

    finalizeButton.disabled = count === 0 || hasPendingSync;
    pendingBanner.hidden = !hasPendingSync;
  }

  function bindRows() {
    rows.forEach((row) => {
      const checkbox = row.querySelector('[data-field="selected"]');
      const price = row.querySelector('[data-field="purchased_price"]');
      const store = row.querySelector('[data-field="store_name"]');
      const fields = row.querySelector('[data-purchase-fields]');

      checkbox.addEventListener('change', async () => {
        const item = rowState(row);
        item.selected = checkbox.checked;
        fields.hidden = !checkbox.checked;
        row.classList.toggle('selected', checkbox.checked);
        await saveState();
        updateTotals();

        if (checkbox.checked) {
          requestAnimationFrame(() => price.focus());
        }
      });

      price.addEventListener('input', async () => {
        const item = rowState(row);
        item.purchased_price = price.value;
        updateLineTotal(row);
        await saveState();
        updateTotals();
      });

      store.addEventListener('input', async () => {
        const item = rowState(row);
        item.store_name = store.value;
        await saveState();
      });
    });
  }

  function updateConnectionUi() {
    const online = navigator.onLine;
    connectionStatus.classList.toggle('offline', !online);
    connectionStatus.querySelector('strong').textContent = online ? 'Online' : 'Offline';
    offlineBanner.hidden = online;
  }

  function uuid() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return 'purchase-' + Date.now() + '-' + Math.random().toString(16).slice(2);
  }

  function localDate() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
  }

  function currentSelection() {
    const items = [];

    rows.forEach((row) => {
      const item = rowState(row);
      if (!item.selected || item.pending_sync) return;

      items.push({
        id: Number(row.dataset.itemId),
        purchased_price: Number(item.purchased_price || 0),
        store_name: String(item.store_name || '').trim()
      });
    });

    return items;
  }

  function validateSelection(items) {
    if (!items.length) {
      return 'Selecione pelo menos um produto.';
    }

    const missing = items.find((item) => !(item.purchased_price > 0));
    if (missing) {
      const row = rows.find((candidate) => Number(candidate.dataset.itemId) === missing.id);
      row?.classList.add('needs-price');
      row?.querySelector('[data-field="purchased_price"]')?.focus();
      return 'Informe o preço comprado de todos os produtos selecionados.';
    }

    return '';
  }

  async function sendPayload(payload) {
    const response = await fetch(config.syncUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        ...payload,
        csrf_token: config.csrfToken
      })
    });

    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      throw new Error('Não foi possível validar a sessão para sincronizar.');
    }

    const body = await response.json();
    if (!response.ok || !body.ok) {
      throw new Error(body.message || 'Não foi possível sincronizar a compra.');
    }

    return body;
  }

  async function markPayloadPending(payload) {
    payload.items.forEach((queuedItem) => {
      const item = state.items[String(queuedItem.id)];
      if (!item) return;
      item.selected = true;
      item.pending_sync = true;
      item.pending_purchase_id = payload.client_purchase_id;
      item.purchased_price = queuedItem.purchased_price;
      item.store_name = queuedItem.store_name;
    });

    await saveState();
    rows.forEach(applyRowState);
    updateTotals();
  }

  async function queuePurchase(payload) {
    await idbPut(OUTBOX_STORE, payload);
    await markPayloadPending(payload);

    try {
      const registration = await navigator.serviceWorker?.ready;
      if (registration?.sync) {
        await registration.sync.register('familia-shopping-sync');
      }
    } catch (_) {}
  }

  async function finalizePurchase() {
    const items = currentSelection();
    const validation = validateSelection(items);

    if (validation) {
      alert(validation);
      return;
    }

    const total = items.reduce((sum, item) => {
      const row = rows.find((candidate) => Number(candidate.dataset.itemId) === item.id);
      return sum + (Number(row?.dataset.quantity || 0) * item.purchased_price);
    }, 0);

    if (!confirm('Finalizar esta compra em ' + money.format(total) + '? O valor será lançado automaticamente em Gastos.')) {
      return;
    }

    const payload = {
      client_purchase_id: uuid(),
      list_id: config.listId,
      month: config.month,
      purchase_date: localDate(),
      items,
      csrf_token: config.csrfToken,
      created_at: Date.now()
    };

    finalizeButton.disabled = true;
    finalizeButton.textContent = navigator.onLine ? 'Finalizando...' : 'Salvando offline...';

    try {
      if (navigator.onLine) {
        const result = await sendPayload(payload);
        await idbDelete(OUTBOX_STORE, payload.client_purchase_id).catch(() => {});
        alert(result.message || 'Compra finalizada.');
        window.location.reload();
        return;
      }

      await queuePurchase(payload);
      alert('Compra salva neste aparelho. Ela será enviada ao financeiro quando a internet voltar.');
    } catch (error) {
      if (!navigator.onLine) {
        await queuePurchase(payload);
        alert('Compra salva offline. Ela será sincronizada quando a conexão voltar.');
      } else {
        alert(error.message || 'Não foi possível finalizar a compra.');
      }
    } finally {
      finalizeButton.textContent = 'Finalizar compra';
      updateTotals();
    }
  }

  async function syncOutbox() {
    if (!navigator.onLine) return false;

    let queued;
    try {
      queued = await idbGetAll(OUTBOX_STORE);
    } catch (_) {
      return false;
    }

    if (!queued.length) return false;

    let syncedCurrentList = false;

    for (const payload of queued) {
      try {
        await sendPayload(payload);
        await idbDelete(OUTBOX_STORE, payload.client_purchase_id);
        if (Number(payload.list_id) === Number(config.listId)) {
          syncedCurrentList = true;
        }
      } catch (_) {
        // Mantém na fila. A próxima reconexão ou abertura da página tentará novamente.
      }
    }

    return syncedCurrentList;
  }

  async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    try {
      await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      await navigator.serviceWorker.ready;
    } catch (_) {}
  }

  async function init() {
    updateConnectionUi();
    bindRows();

    try {
      const stored = await idbGet(STATE_STORE, stateKey);
      state = mergeWithServer(stored);
      await saveState();
    } catch (_) {
      state = serverState();
    }

    rows.forEach(applyRowState);
    updateTotals();

    await registerServiceWorker();

    if (navigator.onLine) {
      const synced = await syncOutbox();
      if (synced) {
        window.location.reload();
        return;
      }
    }
  }

  finalizeButton?.addEventListener('click', finalizePurchase);

  window.addEventListener('online', async () => {
    updateConnectionUi();
    const hadPending = Object.values(state.items || {}).some((item) => Boolean(item.pending_sync));
    const synced = await syncOutbox();

    if (synced) {
      window.location.reload();
      return;
    }

    if (hadPending) {
      try {
        const remaining = await idbGetAll(OUTBOX_STORE);
        const currentStillQueued = remaining.some((payload) => Number(payload.list_id) === Number(config.listId));
        if (!currentStillQueued) {
          window.location.reload();
        }
      } catch (_) {}
    }
  });

  window.addEventListener('offline', updateConnectionUi);

  init();
})();