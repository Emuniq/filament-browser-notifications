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

        init() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
            if (this.isOptedOut()) return;
            if (Notification.permission === 'granted') {
                this.ensureSubscription();
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
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') return;

                await this.ensureSubscription();
            } catch (e) {
                console.error('[BrowserNotifications] Subscribe error:', e);
            }
        },

        async ensureSubscription() {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js');
                await navigator.serviceWorker.ready;

                let subscription = await registration.pushManager.getSubscription();

                if (!subscription) {
                    const vapidKey = document.querySelector('meta[name="vapid-public-key"]');
                    if (!vapidKey) return;

                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.urlBase64ToUint8Array(vapidKey.content),
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
            } catch (e) {
                console.error('[BrowserNotifications] Registration error:', e);
            }
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

        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        },
    }));
});
</script>
@endauth
