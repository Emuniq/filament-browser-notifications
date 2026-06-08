<div x-data="bnProfileSection" style="margin-top: 1.5rem;">
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
                <p style="font-size: 0.8125rem; color: var(--fi-color-gray-500); margin: 0 0 0.75rem 0;">
                    {{ __('filament-browser-notifications::profile.denied_help') }}
                </p>
                <x-filament::button
                    color="gray"
                    size="sm"
                    icon="heroicon-m-arrow-path"
                    x-on:click="window.location.reload()"
                >
                    {{ __('filament-browser-notifications::profile.reload') }}
                </x-filament::button>
            </div>
        </template>

        <template x-if="status === 'ios'">
            <div>
                <div style="margin-bottom: 0.75rem;">
                    <x-filament::badge color="warning" icon="heroicon-m-device-phone-mobile">
                        {{ __('filament-browser-notifications::profile.status_ios') }}
                    </x-filament::badge>
                </div>
                <p style="font-size: 0.8125rem; color: var(--fi-color-gray-500); margin: 0;">
                    {{ __('filament-browser-notifications::profile.ios_help') }}
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
        _reg: null,

        async init() {
            if (this.isIosWithoutPwa()) {
                this.status = 'ios';
                return;
            }
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                this.status = 'unsupported';
                return;
            }

            // Pre-register SW on page load
            try {
                this._reg = await navigator.serviceWorker.register('/sw.js');
                await navigator.serviceWorker.ready;
            } catch (e) {}

            try {
                if (localStorage.getItem('bn_opted_out') === '1') {
                    this.status = 'default';
                    return;
                }
            } catch (e) {}

            if (this.devices > 0) {
                this.status = 'granted';
            } else if (Notification.permission === 'denied') {
                this.status = 'denied';
            } else {
                this.status = 'default';
            }
        },

        isIos() {
            return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                   (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        },

        isIosWithoutPwa() {
            if (!this.isIos()) return false;
            return !(window.navigator.standalone === true ||
                     window.matchMedia('(display-mode: standalone)').matches);
        },

        async subscribe() {
            try { localStorage.removeItem('bn_opted_out'); } catch (e) {}
            try {
                // Step 1: Request permission (required on both iOS and desktop)
                var permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.status = 'denied';
                    return;
                }

                // Step 2: Get registration (use pre-registered from init)
                var reg = this._reg || await navigator.serviceWorker.ready;

                // Step 3: Subscribe to push
                var subscription = await reg.pushManager.getSubscription();
                if (!subscription) {
                    var vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
                    if (!vapidMeta) return;
                    subscription = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.urlBase64ToUint8Array(vapidMeta.content),
                    });
                }

                // Step 4: Send to server
                await fetch('/webpush/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(subscription),
                });
                this.devices++;
                this.status = 'granted';
            } catch (e) {
                console.error('[BrowserNotifications] Subscribe error:', e);
            }
        },

        async unsubscribe() {
            try {
                var reg = await navigator.serviceWorker.ready;
                var subscription = await reg.pushManager.getSubscription();
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

        urlBase64ToUint8Array(base64String) {
            var padding = '='.repeat((4 - base64String.length % 4) % 4);
            var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            var rawData = window.atob(base64);
            var outputArray = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
            return outputArray;
        },
    }));
});
</script>
