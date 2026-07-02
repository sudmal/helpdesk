const CACHE_NAME = 'helpdesk-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(clients.claim()));

// Push уведомления
self.addEventListener('push', (e) => {
  if (!e.data) return;
  const data = e.data.json();
  e.waitUntil(
    self.registration.showNotification(data.title || 'HelpDesk', {
      body:     data.body    || '',
      icon:     '/icons/icon-192.png',
      badge:    '/icons/icon-192.png',
      data:     data.data    || {},
      vibrate:  [200, 100, 200],
      tag:      data.tag     || 'helpdesk',
      renotify: true,
    })
  );
});

// Клик — открываем нужную страницу
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = e.notification.data?.url || '/';
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const c of list) {
        if (c.url.includes(self.location.origin) && 'focus' in c) {
          c.navigate(url); return c.focus();
        }
      }
      return clients.openWindow(url);
    })
  );
});
