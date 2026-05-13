import './bootstrap';
import { mount } from 'svelte';
import MobileSpa from './mobile-spa.svelte';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const mobileMessages = window.mobileMessages || {};
const mobileSecurity = window.mobileSecurity || {};
let doubleBackToCloseEnabled = false;
let notificationRefreshTimer = null;
let notificationRefreshInFlight = false;
let notificationRefreshController = null;
const processingTimeouts = new WeakMap();

window.mobileMessages = mobileMessages;
window.mobileSecurity = mobileSecurity;

function getScrollContainer() {
    return document.querySelector('[data-mobile-scroll]');
}

function getScrollStorageKey(path = window.location.pathname) {
    return `mobile-scroll:${path}`;
}

function isPerformanceMode() {
    return mobileSecurity.performanceMode !== false;
}

async function nativeBridgeCall(method, params = {}) {
    const response = await window.fetch('/_native/api/call', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': mobileSecurity.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ method, params }),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Native call failed');
    }

    return response.json();
}

function routePath(url = window.location.href) {
    return new URL(url, window.location.href).pathname.replace(/\/+$/, '') || '/';
}

function shouldEnableDoubleBackToClose(url = window.location.href) {
    return mobileSecurity.doubleBackToClose === true && routePath(url) === '/home';
}

function syncDoubleBackToClose(url = window.location.href) {
    const shouldEnable = shouldEnableDoubleBackToClose(url);

    if (doubleBackToCloseEnabled === shouldEnable) {
        return;
    }

    doubleBackToCloseEnabled = shouldEnable;
    nativeBridgeCall(
        shouldEnable ? 'DoubleBackToClose.Enable' : 'DoubleBackToClose.Disable',
        shouldEnable
            ? {
                message: mobileMessages.doubleBackToClose || 'Press back again to exit',
                timeout: 2000,
            }
            : {},
    ).catch(() => {
        doubleBackToCloseEnabled = null;
    });
}

function replaceObjectContents(target, source) {
    Object.keys(target).forEach((key) => {
        delete target[key];
    });

    Object.assign(target, source || {});
}

function readRuntimeConfig(root = document.querySelector('[data-mobile-spa-root]')) {
    const configElement = root?.querySelector('[data-mobile-runtime-config]');

    if (!(configElement instanceof HTMLScriptElement) || !configElement.textContent) {
        return {};
    }

    try {
        return JSON.parse(configElement.textContent);
    } catch {
        return {};
    }
}

function syncMobileRuntimeConfig(root = document.querySelector('[data-mobile-spa-root]')) {
    const config = readRuntimeConfig(root);

    if (config.messages) {
        replaceObjectContents(mobileMessages, config.messages);
    }

    if (config.security) {
        replaceObjectContents(mobileSecurity, config.security);
    }
}

function restoreScrollPosition({ restoreScroll = true, scrollTop = null } = {}) {
    const scrollContainer = getScrollContainer();

    if (!(scrollContainer instanceof HTMLElement)) {
        return;
    }

    if (Number.isFinite(scrollTop)) {
        scrollContainer.scrollTop = scrollTop;

        window.requestAnimationFrame(() => {
            scrollContainer.scrollTop = scrollTop;
        });

        return;
    }

    if (!restoreScroll) {
        scrollContainer.scrollTop = 0;

        return;
    }

    const storedScroll = Number(window.sessionStorage.getItem(getScrollStorageKey()) || 0);

    if (storedScroll > 0 && !window.location.hash) {
        window.requestAnimationFrame(() => {
            scrollContainer.scrollTop = storedScroll;
        });
    }
}

function initializeStartupForm() {
    const startupForm = document.querySelector('[data-startup-check]');

    if (startupForm instanceof HTMLFormElement && startupForm.dataset.autoSubmitted !== 'true') {
        startupForm.dataset.autoSubmitted = 'true';
        const delay = Number(startupForm.dataset.startupCheckDelay || 120);

        window.setTimeout(() => {
            if (startupForm.isConnected) {
                window.location.replace(startupForm.dataset.startupCheckUrl || startupForm.action);
            }
        }, Math.max(0, delay));
    }
}

function animateIn() {
    if (reduceMotion || isPerformanceMode()) {
        return;
    }

    const content = document.querySelector('[data-page-content]');

    if (!(content instanceof HTMLElement)) {
        return;
    }

    Array.from(content.querySelectorAll('[data-mobile-animate]'))
        .slice(0, 4)
        .forEach((element, index) => {
            if (!(element instanceof HTMLElement)) {
                return;
            }

            element.animate([
                { opacity: 0, transform: 'translateY(8px) scale(.99)' },
                { opacity: 1, transform: 'translateY(0) scale(1)' },
            ], {
                duration: 160,
                delay: index * 16,
                easing: 'cubic-bezier(.2,.8,.2,1)',
                fill: 'backwards',
            });
        });
}

function clearProcessingTimeout(element) {
    const timeout = processingTimeouts.get(element);

    if (!timeout) {
        return;
    }

    window.clearTimeout(timeout);
    processingTimeouts.delete(element);
}

function scheduleProcessingReset(element, reset, delay = 12000) {
    clearProcessingTimeout(element);
    processingTimeouts.set(element, window.setTimeout(() => {
        processingTimeouts.delete(element);
        reset();
    }, delay));
}

function setButtonProcessing(button, processing) {
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    if (processing) {
        button.dataset.processingWasDisabled = button.disabled ? 'true' : 'false';
        button.dataset.processing = 'true';
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;
        scheduleProcessingReset(button, () => setButtonProcessing(button, false));

        return;
    }

    clearProcessingTimeout(button);

    const wasDisabled = button.dataset.processingWasDisabled === 'true';

    delete button.dataset.processing;
    delete button.dataset.processingWasDisabled;
    button.removeAttribute('aria-busy');

    if (!wasDisabled) {
        button.disabled = false;
    }
}

function isFormProcessingButton(button) {
    return button instanceof HTMLButtonElement
        && !button.matches('[data-password-toggle]')
        && (button.type === 'submit' || button.matches('[data-form-processing-button]'));
}

function formProcessingButtons(form, submitter) {
    if (isFormProcessingButton(submitter)) {
        return [submitter];
    }

    return Array.from(form.querySelectorAll('button'))
        .filter(isFormProcessingButton);
}

function setFormProcessing(form, processing, submitter = null) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (processing) {
        form.dataset.processing = 'true';
        scheduleProcessingReset(form, () => setFormProcessing(form, false), 15000);
    } else {
        clearProcessingTimeout(form);
        delete form.dataset.processing;
    }

    const buttons = processing
        ? formProcessingButtons(form, submitter)
        : Array.from(form.querySelectorAll('button[data-processing="true"]'));

    buttons.forEach((button) => {
        setButtonProcessing(button, processing);
    });
}

function resetProcessingStates(root = document) {
    root.querySelectorAll('form[data-processing="true"]').forEach((form) => {
        if (form instanceof HTMLFormElement) {
            setFormProcessing(form, false);
        }
    });

    root.querySelectorAll('button[data-processing="true"]').forEach((button) => {
        if (button instanceof HTMLButtonElement) {
            setButtonProcessing(button, false);
        }
    });
}

function setNotificationBadge(count) {
    const notificationBadge = document.querySelector('[data-notification-badge]');
    const notificationTrigger = document.querySelector('[data-notification-trigger]');
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

    if (hasUnread && !reduceMotion && !isPerformanceMode()) {
        notificationBadge.animate([
            { transform: 'scale(.7)', opacity: 0.3 },
            { transform: 'scale(1.12)', opacity: 1 },
            { transform: 'scale(1)', opacity: 1 },
        ], {
            duration: 320,
            easing: 'cubic-bezier(.2,.8,.2,1)',
        });
    }
}

function stopNotificationRefresh() {
    if (notificationRefreshTimer) {
        window.clearInterval(notificationRefreshTimer);
        notificationRefreshTimer = null;
    }

    if (notificationRefreshController) {
        notificationRefreshController.abort();
        notificationRefreshController = null;
    }

    notificationRefreshInFlight = false;
}

async function refreshNotificationBadge() {
    const notificationHost = document.querySelector('[data-notification-status-url]');

    if (!(notificationHost instanceof HTMLElement)) {
        return;
    }

    const statusUrl = notificationHost.dataset.notificationStatusUrl;

    if (!statusUrl || notificationRefreshInFlight) {
        return;
    }

    notificationRefreshInFlight = true;
    const controller = new AbortController();

    notificationRefreshController = controller;

    const timeout = window.setTimeout(() => {
        controller.abort();
    }, 2500);

    try {
        const response = await fetch(statusUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            setNotificationBadge(0);
            return;
        }

        const payload = await response.json();
        setNotificationBadge(Number(payload.unread_count || 0));
    } catch {
        setNotificationBadge(0);
    } finally {
        window.clearTimeout(timeout);

        if (notificationRefreshController === controller) {
            notificationRefreshController = null;
            notificationRefreshInFlight = false;
        }
    }
}

function initializeNotifications() {
    stopNotificationRefresh();

    const notificationHost = document.querySelector('[data-notification-status-url]');

    if (!(notificationHost instanceof HTMLElement)) {
        return;
    }

    const interval = Math.max(30000, Number(notificationHost.dataset.notificationRefresh || 60000));

    refreshNotificationBadge();
    notificationRefreshTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            refreshNotificationBadge();
        }
    }, interval);
}

function initializeMobileToasts() {
    document.querySelectorAll('[data-mobile-toast]').forEach((toast) => {
        if (!(toast instanceof HTMLElement)) {
            return;
        }

        if (toast.dataset.dismissBound !== 'true') {
            toast.dataset.dismissBound = 'true';
            toast.addEventListener('click', () => dismissMobileToast(toast));
            toast.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    dismissMobileToast(toast);
                }
            });
        }

        if (toast.dataset.dismissScheduled === 'true') {
            return;
        }

        toast.dataset.dismissScheduled = 'true';

        window.setTimeout(() => {
            dismissMobileToast(toast);
        }, reduceMotion ? 3200 : 4100);
    });
}

function dismissMobileToast(toast) {
    if (!(toast instanceof HTMLElement) || toast.hidden || toast.dataset.dismissed === 'true') {
        return;
    }

    toast.dataset.dismissed = 'true';

    if (reduceMotion) {
        toast.hidden = true;

        return;
    }

    toast.style.animation = 'mobile-toast-out 160ms ease-in forwards';
    window.setTimeout(() => {
        toast.hidden = true;
    }, 180);
}

function initializeMobilePage(options = {}) {
    syncMobileRuntimeConfig();
    resetProcessingStates();
    restoreScrollPosition(options);
    initializeStartupForm();
    syncDoubleBackToClose();

    if (options.animate !== false) {
        animateIn();
    }

    initializeNotifications();
    initializeMobileToasts();
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    const scrollContainer = getScrollContainer();

    if (!(form instanceof HTMLFormElement) || !(scrollContainer instanceof HTMLElement)) {
        return;
    }

    if (form.matches('[data-startup-check]')) {
        return;
    }

    window.sessionStorage.setItem(getScrollStorageKey(), String(scrollContainer.scrollTop));
}, { capture: true });

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
        return;
    }

    if (form.dataset.processing === 'true') {
        event.preventDefault();

        return;
    }

    setFormProcessing(form, true, event.submitter);
});

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('button') : null;

    if (!(button instanceof HTMLButtonElement) || button.type === 'submit' || button.matches('[data-copy-value], [data-password-toggle]')) {
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
    resetProcessingStates();
    syncDoubleBackToClose();
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

    const revealing = input.type === 'password';
    const showIcon = button.querySelector('[data-password-icon-show]');
    const hideIcon = button.querySelector('[data-password-icon-hide]');

    input.type = revealing ? 'text' : 'password';
    button.setAttribute('aria-pressed', revealing ? 'true' : 'false');

    if (showIcon instanceof HTMLElement) {
        showIcon.hidden = revealing;
    }

    if (hideIcon instanceof HTMLElement) {
        hideIcon.hidden = !revealing;
    }
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

window.mobileApp = {
    initialize: initializeMobilePage,
    setButtonProcessing,
    setFormProcessing,
    syncDoubleBackToClose,
    syncRuntimeConfig: syncMobileRuntimeConfig,
};

initializeMobilePage();

const spaTarget = document.querySelector('[data-mobile-spa-controller]');

if (spaTarget instanceof HTMLElement) {
    mount(MobileSpa, {
        target: spaTarget,
    });
}
