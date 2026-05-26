@auth
<div
    x-data="browserNotifications"
    x-show="showPrompt"
    x-transition.opacity.duration.300ms
    x-cloak
    style="position: fixed; bottom: 1rem; right: 1rem; z-index: 50; max-width: 24rem;"
>
    <x-filament::section>
        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
            <x-filament::icon
                icon="heroicon-o-bell-alert"
                style="width: 1.25rem; height: 1.25rem; flex-shrink: 0; margin-top: 0.125rem; color: var(--fi-color-primary-500);"
            />
            <div style="flex: 1; min-width: 0;">
                <p style="font-size: 0.875rem; font-weight: 500; margin: 0;">
                    {{ __('filament-browser-notifications::prompt.title') }}
                </p>
                <p style="margin-top: 0.25rem; font-size: 0.75rem; color: var(--fi-color-gray-500); margin-bottom: 0;">
                    {{ __('filament-browser-notifications::prompt.body') }}
                </p>
                <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem;">
                    <x-filament::button size="xs" x-on:click="subscribe()">
                        {{ __('filament-browser-notifications::prompt.accept') }}
                    </x-filament::button>
                    <x-filament::button size="xs" color="gray" x-on:click="dismiss()">
                        {{ __('filament-browser-notifications::prompt.dismiss') }}
                    </x-filament::button>
                </div>
            </div>
            <x-filament::icon-button
                icon="heroicon-m-x-mark"
                color="gray"
                size="sm"
                x-on:click="dismiss()"
                style="flex-shrink: 0;"
            />
        </div>
    </x-filament::section>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('browserNotifications', () => ({
        showPrompt: false,
        _swRegistration: null,
        _vapidKey: null,

        async init() {
            if (this.isIosWithoutPwa()) return;
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
            if (this.isOptedOut()) return;

            // Pre-register SW on page load so click handler has minimal async
            try {
                this._swRegistration = await navigator.serviceWorker.register('/sw.js');
                await navigator.serviceWorker.ready;
                this._vapidKey = this.parseVapidKey();
            } catch (e) {}

            if (Notification.permission === 'granted') {
                // iOS requires user gesture for pushManager.subscribe — skip auto-resubscribe
                if (!this.isIos()) {
                    this.ensureSubscription();
                }
                return;
            }
            if (Notification.permission === 'denied') return;
            if (this.isDismissed()) return;

            setTimeout(() => { this.showPrompt = true; }, {{ $plugin->getPromptDelay() * 1000 }});

            document.addEventListener('livewire:navigated', () => {
                if (Notification.permission === 'default' && !this.showPrompt && !this.isDismissed() && !this.isOptedOut()) {
                    this.showPrompt = true;
                }
            });
        },

        async subscribe() {
            this.showPrompt = false;
            try { localStorage.removeItem('bn_opted_out'); } catch (e) {}

            try {
                var vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
                if (!vapidMeta) return;

                if (this.isIos()) {
                    var subscription = await this._swRegistration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: vapidMeta.content,
                    });
                } else {
                    var permission = await Notification.requestPermission();
                    if (permission !== 'granted') return;

                    var reg = await navigator.serviceWorker.ready;
                    var subscription = await reg.pushManager.getSubscription();
                    if (!subscription) {
                        subscription = await reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: vapidMeta.content,
                        });
                    }
                }

                if (subscription) {
                    await fetch('/webpush/subscribe', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(subscription),
                    });
                }
            } catch (e) {
                console.error('[BrowserNotifications] Subscribe error:', e);
            }
        },

        async ensureSubscription() {
            try {
                if (!this._swRegistration) return;
                var subscription = await this._swRegistration.pushManager.getSubscription();
                if (!subscription && this._vapidKey) {
                    subscription = await this._swRegistration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this._vapidKey,
                    });
                }
                if (subscription) {
                    await fetch('/webpush/subscribe', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(subscription),
                    });
                }
            } catch (e) {}
        },

        parseVapidKey() {
            var meta = document.querySelector('meta[name="vapid-public-key"]');
            if (!meta) return null;
            var padding = '='.repeat((4 - meta.content.length % 4) % 4);
            var base64 = (meta.content + padding).replace(/-/g, '+').replace(/_/g, '/');
            var rawData = window.atob(base64);
            var arr = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i) arr[i] = rawData.charCodeAt(i);
            return arr;
        },

        dismiss() {
            this.showPrompt = false;
            try {
                var expires = Date.now() + ({{ $plugin->getDismissCooldownDays() }} * 86400000);
                localStorage.setItem('bn_dismissed_until', expires);
            } catch (e) {}
        },

        isDismissed() {
            try {
                var until = localStorage.getItem('bn_dismissed_until');
                if (!until) return false;
                if (Date.now() < parseInt(until, 10)) return true;
                localStorage.removeItem('bn_dismissed_until');
            } catch (e) {}
            return false;
        },

        isOptedOut() {
            try { return localStorage.getItem('bn_opted_out') === '1'; } catch (e) { return false; }
        },

        isIos() {
            var ua = navigator.userAgent;
            return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        },

        isIosWithoutPwa() {
            if (!this.isIos()) return false;
            return !(window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches);
        },
    }));
});
</script>
@endauth
