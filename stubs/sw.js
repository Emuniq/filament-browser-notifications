self.addEventListener('push', function (event) {
    var title = 'Notification';
    var options = {
        body: '',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data: {},
        tag: 'default',
        renotify: true,
    };

    if (event.data) {
        try {
            var data = event.data.json();
            title = data.title || title;
            options.body = data.body || '';
            options.icon = data.icon || options.icon;
            options.badge = data.badge || options.badge;
            options.data = data.data || {};
            options.tag = data.tag || options.tag;
        } catch (e) {
            title = event.data.text() || title;
        }
    }

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var data = event.notification.data || {};
    var actionUrl = data.action_url || '/';
    var openInNewTab = !!data.open_in_new_tab;

    if (openInNewTab) {
        event.waitUntil(clients.openWindow(actionUrl));
        return;
    }

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
