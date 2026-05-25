<div x-data="bnProfileSection">
<x-filament::section icon="heroicon-o-bell">
    <x-slot name="heading">
        {{ __('filament-browser-notifications::profile.title') }}
    </x-slot>
    <x-slot name="description">
        {{ __('filament-browser-notifications::profile.subtitle') }}
    </x-slot>

    <div>
        <template x-if="status === 'granted'">
            <div>
                <div style="margin-bottom: 0.75rem;">
                    <x-filament::badge color="success" icon="heroicon-m-check-circle">
                        {{ __('filament-browser-notifications::profile.status_active') }}
                    </x-filament::badge>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-m-bell-slash"
                        x-on:click="unsubscribe()"
                    >
                        {{ __('filament-browser-notifications::profile.disable') }}
                    </x-filament::button>
                    <span
                        x-show="devices > 0"
                        style="font-size: 0.8125rem; color: var(--fi-color-gray-500);"
                    >
                        <span x-text="devices"></span> {{ __('filament-browser-notifications::profile.devices_registered') }}
                    </span>
                </div>
            </div>
        </template>

        <template x-if="status === 'default'">
            <div>
                <x-filament::button
                    color="primary"
                    size="sm"
                    icon="heroicon-m-bell-alert"
                    x-on:click="subscribe()"
                >
                    {{ __('filament-browser-notifications::profile.enable') }}
                </x-filament::button>
            </div>
        </template>

        <template x-if="status === 'denied'">
            <div>
                <div style="margin-bottom: 0.75rem;">
                    <x-filament::badge color="danger" icon="heroicon-m-x-circle">
                        {{ __('filament-browser-notifications::profile.status_denied') }}
                    </x-filament::badge>
                </div>
                <p style="font-size: 0.8125rem; color: var(--fi-color-gray-500); margin: 0;">
                    {{ __('filament-browser-notifications::profile.denied_help') }}
                </p>
            </div>
        </template>

        <template x-if="status === 'unsupported'">
            <div>
                <x-filament::badge color="gray" icon="heroicon-m-exclamation-triangle">
                    {{ __('filament-browser-notifications::profile.status_unsupported') }}
                </x-filament::badge>
            </div>
        </template>
    </div>
</x-filament::section>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('bnProfileSection', () => ({
        status: 'default',
        devices: {{ auth()->user()?->pushSubscriptions()->count() ?? 0 }},

        async init() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                this.status = 'unsupported';
                return;
            }
            try {
                if (localStorage.getItem('bn_opted_out') === '1') {
                    this.status = 'default';
                    return;
                }
            } catch (e) {}
            this.status = Notification.permission;
        },

        async subscribe() {
            try { localStorage.removeItem('bn_opted_out'); } catch (e) {}
            try {
                var permission = await Notification.requestPermission();
                this.status = permission;
                if (permission !== 'granted') return;

                var registration = await navigator.serviceWorker.register('/sw.js');
                await navigator.serviceWorker.ready;

                var subscription = await registration.pushManager.getSubscription();
                if (!subscription) {
                    var vapidKey = document.querySelector('meta[name="vapid-public-key"]');
                    if (!vapidKey) return;

                    var padding = '='.repeat((4 - vapidKey.content.length % 4) % 4);
                    var base64 = (vapidKey.content + padding).replace(/-/g, '+').replace(/_/g, '/');
                    var rawData = window.atob(base64);
                    var outputArray = new Uint8Array(rawData.length);
                    for (var i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);

                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: outputArray,
                    });
                }

                await fetch('/webpush/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(subscription),
                });
                this.devices++;
            } catch (e) {
                console.error('[BrowserNotifications] Subscribe error:', e);
            }
        },

        async unsubscribe() {
            try {
                var registration = await navigator.serviceWorker.ready;
                var subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    await fetch('/webpush/unsubscribe', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ endpoint: subscription.endpoint }),
                    });
                    await subscription.unsubscribe();
                }
                try { localStorage.setItem('bn_opted_out', '1'); } catch (e) {}
                this.status = 'default';
                this.devices = Math.max(0, this.devices - 1);
            } catch (e) {
                console.error('[BrowserNotifications] Unsubscribe error:', e);
            }
        },
    }));
});
</script>
