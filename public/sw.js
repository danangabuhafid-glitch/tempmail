/**
 * Service worker minimal untuk installability PWA.
 * Strategi network-first tanpa cache konten email (data privat + selalu berubah);
 * hanya ikon & manifest yang di-cache untuk tampilan offline dasar.
 */
const CACHE = 'tempmail-shell-v1';
// Path dihitung relatif terhadap lokasi sw.js — tetap benar walau aplikasi
// dipasang di subdirektori (mis. /tempmail/public/)
const SHELL = ['manifest.json', 'icons/icon-192.png', 'icons/icon-512.png']
    .map((p) => new URL(p, self.location.href).pathname);

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    if (e.request.method !== 'GET') return;
    const url = new URL(e.request.url);
    if (!SHELL.includes(url.pathname)) return; // konten dinamis: selalu ke jaringan

    e.respondWith(caches.match(e.request).then((hit) => hit || fetch(e.request)));
});

// Klik notifikasi (jalur Android Chrome) → buka email yang bersangkutan
self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const target = e.notification.data?.url;
    if (!target) return;

    e.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const c of list) {
                if ('focus' in c) { c.navigate(target); return c.focus(); }
            }
            return self.clients.openWindow(target);
        })
    );
});
