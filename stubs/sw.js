self.addEventListener('push', function (event) {
    if (!event.data) return;

    var data = event.data.json();
    var title = data.title || 'Notification';
    var options = {
        body: data.body || '',
        icon: data.icon || '/favicon.ico',
        badge: data.badge || '/favicon.ico',
        data: data.data || {},
        tag: data.tag || 'default',
        renotify: true,
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var actionUrl = (event.notification.data && event.notification.data.action_url)
        ? event.notification.data.action_url
        : '/admin';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (new URL(client.url).origin === self.location.origin) {
                    return client.focus().then(function (focused) {
                        if (focused.navigate) {
                            return focused.navigate(actionUrl);
                        }
                    });
                }
            }
            return clients.openWindow(actionUrl);
        })
    );
});
