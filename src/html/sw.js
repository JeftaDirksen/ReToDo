const badgeEndpoint = 'badge.php';

async function updateBadge() {
    try {
        if (!('setAppBadge' in navigator)) {
            return;
        }

        const response = await fetch(badgeEndpoint, {
            cache: 'no-store',
            credentials: 'include'
        });

        if (!response.ok) {
            if ('clearAppBadge' in navigator) {
                await navigator.clearAppBadge();
            }
            return;
        }

        const data = await response.json();
        if (data.count > 0) {
            await navigator.setAppBadge(data.count);
        } else if ('clearAppBadge' in navigator) {
            await navigator.clearAppBadge();
        }
    } catch (error) {
        // Badge updates are best effort and must not interrupt app requests.
    }
}

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim().then(updateBadge));
});

self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'update-badge') {
        event.waitUntil(updateBadge());
    }
});

self.addEventListener('fetch', function (event) {
    if (new URL(event.request.url).pathname.endsWith('/action.php')) {
        event.respondWith((async function () {
            const response = await fetch(event.request);
            await updateBadge();
            return response;
        })());
    }
});