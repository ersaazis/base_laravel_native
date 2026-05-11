import './bootstrap';
import * as Native from '#nativephp';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const scrollContainer = document.querySelector('[data-mobile-scroll]');
const scrollStorageKey = `mobile-scroll:${window.location.pathname}`;
const biometricOverlay = document.querySelector('[data-biometric-overlay]');
const biometricCancel = document.querySelector('[data-biometric-cancel]');
const mobileMessages = window.mobileMessages || {};
const mobileSecurity = window.mobileSecurity || {};
let activeBiometricPrompt = null;
let backgroundLockSent = false;

if (scrollContainer instanceof HTMLElement) {
    const storedScroll = Number(window.sessionStorage.getItem(scrollStorageKey) || 0);

    if (storedScroll > 0 && !window.location.hash) {
        window.requestAnimationFrame(() => {
            scrollContainer.scrollTop = storedScroll;
        });
    }
}

const startupForm = document.querySelector('[data-startup-check]');

if (startupForm instanceof HTMLFormElement) {
    window.setTimeout(() => {
        startupForm.requestSubmit();
    }, 650);
}

const autoBiometricForm = document.querySelector('[data-biometric-auto-submit]');

if (autoBiometricForm instanceof HTMLFormElement) {
    window.setTimeout(() => {
        autoBiometricForm.requestSubmit();
    }, 450);
}

function animateIn() {
    if (reduceMotion) {
        return;
    }

    const shell = document.querySelector('[data-page-shell]');
    const content = document.querySelector('[data-page-content]');

    if (shell instanceof HTMLElement) {
        shell.animate([
            { opacity: 0.92, transform: 'translateY(10px)' },
            { opacity: 1, transform: 'translateY(0)' },
        ], {
            duration: 360,
            easing: 'cubic-bezier(.2,.8,.2,1)',
            fill: 'both',
        });
    }

    if (!(content instanceof HTMLElement)) {
        return;
    }

    Array.from(content.querySelectorAll('section, article, form, [data-mobile-animate]'))
        .slice(0, 12)
        .forEach((element, index) => {
            if (!(element instanceof HTMLElement)) {
                return;
            }

            element.animate([
                { opacity: 0, transform: 'translateY(14px) scale(.985)' },
                { opacity: 1, transform: 'translateY(0) scale(1)' },
            ], {
                duration: 320,
                delay: 45 + index * 35,
                easing: 'cubic-bezier(.2,.8,.2,1)',
                fill: 'backwards',
            });
        });
}

function animatePress(element, pressed) {
    if (reduceMotion || !(element instanceof HTMLElement)) {
        return;
    }

    element.animate([
        { transform: pressed ? 'scale(1)' : 'scale(.985)' },
        { transform: pressed ? 'scale(.985)' : 'scale(1)' },
    ], {
        duration: pressed ? 120 : 180,
        easing: 'cubic-bezier(.2,.8,.2,1)',
        fill: 'both',
    });
}

animateIn();

function setButtonProcessing(button, processing) {
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    if (processing) {
        button.dataset.processingWasDisabled = button.disabled ? 'true' : 'false';
        button.dataset.processing = 'true';
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;

        return;
    }

    const wasDisabled = button.dataset.processingWasDisabled === 'true';

    delete button.dataset.processing;
    delete button.dataset.processingWasDisabled;
    button.removeAttribute('aria-busy');

    if (!wasDisabled) {
        button.disabled = false;
    }
}

function setFormProcessing(form, processing) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (processing) {
        form.dataset.processing = 'true';
    } else {
        delete form.dataset.processing;
    }

    form.querySelectorAll('button').forEach((button) => {
        setButtonProcessing(button, processing);
    });
}

function openBiometricOverlay() {
    let rejectCancel = null;
    const cancelPromise = new Promise((_, reject) => {
        rejectCancel = reject;
    });
    const promptState = {
        cancelled: false,
        cancel() {
            promptState.cancelled = true;
            rejectCancel?.(new Error(mobileMessages.biometricsCancelled || ''));
        },
        cancelPromise,
    };

    activeBiometricPrompt = promptState;

    if (biometricOverlay instanceof HTMLElement) {
        biometricOverlay.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    return promptState;
}

function closeBiometricOverlay(promptState) {
    if (activeBiometricPrompt !== promptState) {
        return;
    }

    activeBiometricPrompt = null;

    if (biometricOverlay instanceof HTMLElement) {
        biometricOverlay.hidden = true;
        document.body.style.overflow = '';
    }

    if (biometricCancel instanceof HTMLButtonElement) {
        setButtonProcessing(biometricCancel, false);
    }
}

async function nativeBridgeCall(method, params = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const body = new URLSearchParams();

    body.set('method', method);

    Object.entries(params).forEach(([key, value]) => {
        body.set(`params[${key}]`, String(value));
    });

    const response = await window.fetch('/_native/api/call', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        body,
        credentials: 'same-origin',
    });
    const result = await response.json().catch(() => null);

    if (!response.ok || result?.status === 'error') {
        throw new Error(result?.message || mobileMessages.biometricsFailed || '');
    }

    return result?.data;
}

function biometricAlertMessage(error) {
    const message = error instanceof Error ? error.message : '';

    if (!message || /method parameter is required/i.test(message)) {
        return mobileMessages.biometricsFailed || '';
    }

    return message;
}

async function verifyBiometrics() {
    const biometric = Native.Biometrics || Native.Biometric || Reflect.get(Native, 'biometric');

    if (!biometric?.prompt) {
        throw new Error(mobileMessages.biometricsUnavailable || '');
    }

    const promptState = openBiometricOverlay();

    try {
        await Promise.race([
            nativeBridgeCall('Biometric.Prompt', { id: 'mobile-auth' }),
            promptState.cancelPromise,
        ]);

        if (promptState.cancelled) {
            throw new Error(mobileMessages.biometricsCancelled || '');
        }
    } finally {
        closeBiometricOverlay(promptState);
    }

    return true;
}

if (biometricCancel instanceof HTMLButtonElement) {
    biometricCancel.addEventListener('click', () => {
        activeBiometricPrompt?.cancel();
    });
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !(scrollContainer instanceof HTMLElement)) {
        return;
    }

    if (form.matches('[data-startup-check]')) {
        return;
    }

    window.sessionStorage.setItem(scrollStorageKey, String(scrollContainer.scrollTop));
}, { capture: true });

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-biometric-form]')) {
        return;
    }

    if (form.dataset.processing === 'true') {
        event.preventDefault();

        return;
    }

    const verifiedInput = form.querySelector('[data-biometric-verified]');

    if (!(verifiedInput instanceof HTMLInputElement) || verifiedInput.value === '1') {
        return;
    }

    event.preventDefault();
    setFormProcessing(form, true);

    try {
        await verifyBiometrics();
        verifiedInput.value = '1';
        setFormProcessing(form, false);
        form.requestSubmit();
    } catch (error) {
        const message = biometricAlertMessage(error);

        window.alert(message || mobileMessages.biometricsFailed || '');
        setFormProcessing(form, false);
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
        return;
    }

    if (form.dataset.processing === 'true') {
        event.preventDefault();

        return;
    }

    setFormProcessing(form, true);
});

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('button') : null;

    if (!(button instanceof HTMLButtonElement) || button.type === 'submit' || button.matches('[data-copy-value]')) {
        return;
    }

    if (button.dataset.processing === 'true') {
        event.preventDefault();
        event.stopImmediatePropagation();

        return;
    }

    setButtonProcessing(button, true);

    window.setTimeout(() => {
        setButtonProcessing(button, false);
    }, 650);
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-processing="true"]').forEach((form) => {
        if (form instanceof HTMLFormElement) {
            setFormProcessing(form, false);
        }
    });

    document.querySelectorAll('button[data-processing="true"]').forEach((button) => {
        if (button instanceof HTMLButtonElement) {
            setButtonProcessing(button, false);
        }
    });
});

function lockForBackground() {
    if (!mobileSecurity.shouldLockOnHide || backgroundLockSent || activeBiometricPrompt) {
        return;
    }

    const lockUrl = mobileSecurity.lockUrl;
    const csrfToken = mobileSecurity.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;

    if (!lockUrl || !csrfToken) {
        return;
    }

    backgroundLockSent = true;
    const payload = new FormData();
    payload.append('_token', csrfToken);

    if (navigator.sendBeacon && navigator.sendBeacon(lockUrl, payload)) {
        return;
    }

    window.fetch(lockUrl, {
        method: 'POST',
        body: payload,
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => {});
}

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        lockForBackground();

        return;
    }

    if (document.visibilityState === 'visible' && backgroundLockSent && mobileSecurity.unlockUrl) {
        window.location.href = mobileSecurity.unlockUrl;
    }
});

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-password-toggle]') : null;

    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const inputId = button.dataset.passwordToggle;
    const input = inputId ? document.getElementById(inputId) : null;

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    input.type = input.type === 'password' ? 'text' : 'password';
});

document.addEventListener('click', async (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-copy-value]') : null;

    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    if (button.dataset.processing === 'true') {
        event.preventDefault();
        event.stopImmediatePropagation();

        return;
    }

    const value = button.dataset.copyValue;

    if (!value) {
        return;
    }

    const copySource = button.closest('label, [data-copy-container]')?.querySelector('[data-copy-source]');

    if (copySource instanceof HTMLInputElement || copySource instanceof HTMLTextAreaElement) {
        copySource.focus();
        copySource.select();
        copySource.setSelectionRange(0, copySource.value.length);
    }

    setButtonProcessing(button, true);

    try {
        if (window.navigator.clipboard?.writeText) {
            await window.navigator.clipboard.writeText(value);
        } else {
            document.execCommand('copy');
        }

        const originalLabel = button.dataset.copyLabel || button.textContent || '';
        button.textContent = button.dataset.copiedLabel || originalLabel;

        window.setTimeout(() => {
            button.textContent = originalLabel;
            setButtonProcessing(button, false);
        }, 1600);
    } catch (error) {
        setButtonProcessing(button, false);
        window.alert(mobileMessages.copyFailed || '');
    }
});

document.addEventListener('pointerdown', (event) => {
    const target = event.target instanceof Element ? event.target.closest('button, a[href]') : null;

    animatePress(target, true);
}, { passive: true });

document.addEventListener('pointerup', (event) => {
    const target = event.target instanceof Element ? event.target.closest('button, a[href]') : null;

    animatePress(target, false);
}, { passive: true });

document.addEventListener('pointercancel', (event) => {
    const target = event.target instanceof Element ? event.target.closest('button, a[href]') : null;

    animatePress(target, false);
}, { passive: true });

const notificationHost = document.querySelector('[data-notification-status-url]');
const notificationBadge = document.querySelector('[data-notification-badge]');
const notificationTrigger = document.querySelector('[data-notification-trigger]');

function setNotificationBadge(count) {
    const unreadCount = Number.isFinite(count) ? Math.max(0, Math.floor(count)) : 0;
    const label = unreadCount > 99 ? '99+' : String(unreadCount);
    const hasUnread = unreadCount > 0;

    if (notificationTrigger instanceof HTMLElement) {
        notificationTrigger.classList.toggle('vault-notification-active', hasUnread);
        notificationTrigger.dataset.hasUnread = hasUnread ? 'true' : 'false';

        const baseLabel = notificationTrigger.dataset.notificationLabel || notificationTrigger.getAttribute('aria-label') || '';
        notificationTrigger.setAttribute('aria-label', hasUnread ? `${baseLabel} (${label})` : baseLabel);
    }

    if (!(notificationBadge instanceof HTMLElement)) {
        return;
    }

    notificationBadge.textContent = label;
    notificationBadge.classList.toggle('hidden', !hasUnread);

    if (hasUnread && !reduceMotion) {
        notificationBadge.animate([
            { transform: 'scale(.7)', opacity: 0.3 },
            { transform: 'scale(1.12)', opacity: 1 },
            { transform: 'scale(1)', opacity: 1 },
        ], {
            duration: 420,
            easing: 'cubic-bezier(.2,.8,.2,1)',
        });
    }
}

async function refreshNotificationBadge() {
    if (!(notificationHost instanceof HTMLElement)) {
        return;
    }

    const statusUrl = notificationHost.dataset.notificationStatusUrl;

    if (!statusUrl) {
        return;
    }

    try {
        const response = await fetch(statusUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            setNotificationBadge(0);
            return;
        }

        const payload = await response.json();
        setNotificationBadge(Number(payload.unread_count || 0));
    } catch {
        setNotificationBadge(0);
    }
}

if (notificationHost instanceof HTMLElement) {
    const interval = Number(notificationHost.dataset.notificationRefresh || 10000);

    refreshNotificationBadge();
    window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            refreshNotificationBadge();
        }
    }, interval);
}
