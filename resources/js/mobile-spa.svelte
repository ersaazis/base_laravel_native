<script>
    import { onMount } from 'svelte';

    const rootSelector = '[data-mobile-spa-root]';
    const overlaySelector = '[data-mobile-spa-overlay]';
    const contentSelector = '[data-page-content]';
    const bottomNavSelector = '[data-mobile-bottom-nav]';

    let abortController = null;
    let navigating = false;
    let navigationVersion = 0;

    function currentScrollTop() {
        const scrollContainer = document.querySelector('[data-mobile-scroll]');

        if (scrollContainer instanceof HTMLElement) {
            return scrollContainer.scrollTop;
        }

        return window.scrollY;
    }

    function rememberCurrentScroll() {
        const state = {
            ...(history.state || {}),
            mobileSpa: true,
            scrollTop: currentScrollTop(),
        };

        history.replaceState(state, '', window.location.href);
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function isSameOrigin(url) {
        return url.origin === window.location.origin;
    }

    function isHtmlResponse(response) {
        return (response.headers.get('content-type') || '').includes('text/html');
    }

    function nextNavigationVersion() {
        navigationVersion += 1;

        return navigationVersion;
    }

    function isCurrentNavigation(version) {
        return version === navigationVersion;
    }

    function shouldHandleAnchor(event) {
        if (
            event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
        ) {
            return null;
        }

        const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;

        if (!(anchor instanceof HTMLAnchorElement)) {
            return null;
        }

        if (
            anchor.target
            || anchor.hasAttribute('download')
            || anchor.closest('[data-no-spa]')
            || anchor.dataset.spa === 'false'
        ) {
            return null;
        }

        const url = new URL(anchor.href, window.location.href);

        if (!isSameOrigin(url)) {
            return null;
        }

        const sameDocument = url.pathname === window.location.pathname && url.search === window.location.search;

        if (sameDocument && url.hash) {
            return null;
        }

        return url;
    }

    function formTargetUrl(form) {
        return new URL(form.action || window.location.href, window.location.href);
    }

    function samePage(left, right) {
        return left.origin === right.origin && left.pathname === right.pathname && left.search === right.search;
    }

    function routeSection(url) {
        const path = url.pathname.replace(/\/+$/, '') || '/';

        if (path === '/home') {
            return 'home';
        }

        if (
            path === '/profile'
            || path.startsWith('/profile/')
            || path === '/settings'
            || path.startsWith('/settings/')
            || path === '/security'
            || path.startsWith('/security/')
        ) {
            return 'profile';
        }

        return null;
    }

    function updateBottomNavigation(url) {
        const section = routeSection(url);
        const nav = document.querySelector(bottomNavSelector);

        if (!(nav instanceof HTMLElement) || !section) {
            return;
        }

        nav.querySelectorAll('[data-mobile-nav-item]').forEach((item) => {
            if (!(item instanceof HTMLAnchorElement)) {
                return;
            }

            const active = item.dataset.mobileNavItem === section;

            item.classList.toggle('is-active', active);

            if (active) {
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });
    }

    function skeletonBlock(className, width = '') {
        const block = document.createElement('div');

        block.className = `mobile-skeleton-block ${className}`;

        if (width) {
            block.style.width = width;
        }

        return block;
    }

    function skeletonCard(lines = ['62%', '44%', '76%']) {
        const card = document.createElement('section');

        card.className = 'mobile-skeleton-card';

        lines.forEach((width, index) => {
            card.appendChild(skeletonBlock(index === 0 ? 'mobile-skeleton-title' : 'mobile-skeleton-line', width));
        });

        return card;
    }

    function skeletonProfileHeader() {
        const card = document.createElement('section');
        const row = document.createElement('div');
        const copy = document.createElement('div');

        card.className = 'mobile-skeleton-card';
        row.className = 'mobile-skeleton-row';
        copy.className = 'grid flex-1 gap-3';

        row.appendChild(skeletonBlock('mobile-skeleton-avatar'));
        copy.appendChild(skeletonBlock('mobile-skeleton-title', '68%'));
        copy.appendChild(skeletonBlock('mobile-skeleton-line', '86%'));
        copy.appendChild(skeletonBlock('mobile-skeleton-line', '42%'));
        row.appendChild(copy);
        card.appendChild(row);

        return card;
    }

    function skeletonTabs() {
        const tabs = document.createElement('div');

        tabs.className = 'mobile-skeleton-tabs';
        tabs.appendChild(skeletonBlock('mobile-skeleton-button'));
        tabs.appendChild(skeletonBlock('mobile-skeleton-button'));

        return tabs;
    }

    function routeSkeleton(url) {
        const section = routeSection(url);
        const skeleton = document.createElement('div');

        skeleton.className = 'mobile-route-skeleton';
        skeleton.setAttribute('aria-hidden', 'true');

        if (section === 'home') {
            skeleton.appendChild(skeletonCard(['44%', '76%', '34%']));

            return skeleton;
        }

        if (section === 'profile') {
            skeleton.appendChild(skeletonProfileHeader());
            skeleton.appendChild(skeletonTabs());
            skeleton.appendChild(skeletonCard(['54%', '88%', '80%', '100%']));

            return skeleton;
        }

        skeleton.appendChild(skeletonCard(['58%', '74%', '86%']));
        skeleton.appendChild(skeletonCard(['46%', '92%', '64%']));

        return skeleton;
    }

    function showRouteSkeleton(url) {
        const content = document.querySelector(contentSelector);
        const shell = document.querySelector('[data-authenticated-shell]');

        if (!(content instanceof HTMLElement) || !(shell instanceof HTMLElement)) {
            return;
        }

        content.dataset.routeLoading = routeSection(url) || 'page';
        content.setAttribute('aria-busy', 'true');
        content.replaceChildren(routeSkeleton(url));
        content.scrollTop = 0;
    }

    function shouldPreserveFormScroll(form, method, beforeUrl, finalUrl) {
        if (method === 'GET') {
            return false;
        }

        if (form.hasAttribute('data-spa-preserve-scroll')) {
            return true;
        }

        return samePage(beforeUrl, finalUrl);
    }

    function shouldHandleForm(form) {
        if (
            !(form instanceof HTMLFormElement)
            || form.target
            || form.closest('[data-no-spa]')
            || form.dataset.spa === 'false'
            || requiresNativeFormSubmission(form)
        ) {
            return false;
        }

        return isSameOrigin(formTargetUrl(form));
    }

    function requiresNativeFormSubmission(form) {
        const encoding = (form.enctype || '').toLowerCase();

        if (encoding && encoding !== 'application/x-www-form-urlencoded') {
            return true;
        }

        return Array.from(form.elements).some((element) => (
            element instanceof HTMLInputElement
            && element.type === 'file'
        ));
    }

    function formDataForSubmit(form, submitter = null) {
        if (!(submitter instanceof HTMLElement) || !submitter.hasAttribute('name')) {
            return new FormData(form);
        }

        try {
            return new FormData(form, submitter);
        } catch {
            return new FormData(form);
        }
    }

    function urlEncodedFormBody(formData) {
        const body = new URLSearchParams();

        formData.forEach((value, key) => {
            if (value instanceof File) {
                return;
            }

            body.append(key, value);
        });

        return body.toString();
    }

    function updateHistory(mode, url, scrollTop) {
        const state = {
            mobileSpa: true,
            scrollTop,
        };

        if (mode === 'push') {
            if (url === window.location.href) {
                history.replaceState(state, '', url);
            } else {
                history.pushState(state, '', url);
            }

            return;
        }

        if (mode === 'replace') {
            history.replaceState(state, '', url);
        }
    }

    function hardNavigate(url) {
        window.location.href = String(url);
    }

    function syncDocumentMetadata(nextDocument) {
        const nextCsrf = nextDocument.querySelector('meta[name="csrf-token"]');
        const currentCsrf = document.querySelector('meta[name="csrf-token"]');

        if (nextCsrf instanceof HTMLMetaElement && currentCsrf instanceof HTMLMetaElement) {
            currentCsrf.content = nextCsrf.content;
        }

        if (nextDocument.documentElement.lang) {
            document.documentElement.lang = nextDocument.documentElement.lang;
        }

        document.body.className = nextDocument.body.className;
    }

    function applyDocument(html, finalUrl, { animate = true, historyMode = 'push', scrollTop = 0, navigationId = navigationVersion } = {}) {
        if (!isCurrentNavigation(navigationId)) {
            return;
        }

        const parser = new DOMParser();
        const nextDocument = parser.parseFromString(html, 'text/html');
        const nextRoot = nextDocument.querySelector(rootSelector);
        const currentRoot = document.querySelector(rootSelector);

        if (!(nextRoot instanceof HTMLElement) || !(currentRoot instanceof HTMLElement)) {
            hardNavigate(finalUrl);

            return;
        }

        const nextOverlay = nextDocument.querySelector(overlaySelector);
        const currentOverlay = document.querySelector(overlaySelector);

        if (nextDocument.title) {
            document.title = nextDocument.title;
        }

        syncDocumentMetadata(nextDocument);

        if (nextOverlay instanceof HTMLElement && currentOverlay instanceof HTMLElement) {
            currentOverlay.replaceWith(nextOverlay);
        }

        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }

        if (!isCurrentNavigation(navigationId)) {
            return;
        }

        currentRoot.replaceWith(nextRoot);
        updateHistory(historyMode, finalUrl, scrollTop);

        window.mobileApp?.syncRuntimeConfig?.(nextRoot);
        window.mobileApp?.initialize?.({
            animate,
            restoreScroll: false,
            scrollTop,
        });

        window.dispatchEvent(new CustomEvent('mobile:spa:navigated', {
            detail: {
                url: finalUrl,
                root: nextRoot,
            },
        }));
    }

    async function fetchDocument(url, options = {}) {
        if (abortController) {
            abortController.abort();
        }

        const navigationId = nextNavigationVersion();
        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            controller.abort();
        }, Number(window.mobileSecurity?.spaTimeout || 10000));

        abortController = controller;
        navigating = true;

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                redirect: 'follow',
                signal: controller.signal,
                cache: 'no-store',
                ...options,
                headers: {
                    Accept: 'text/html, application/xhtml+xml',
                    'Cache-Control': 'no-cache',
                    Pragma: 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Mobile-SPA': 'true',
                    ...(csrfToken() ? { 'X-CSRF-TOKEN': csrfToken() } : {}),
                    ...(options.headers || {}),
                },
            });

            if (!isCurrentNavigation(navigationId)) {
                return null;
            }

            if (!isHtmlResponse(response)) {
                hardNavigate(response.url || url);

                return null;
            }

            const html = await response.text();

            if (!isCurrentNavigation(navigationId)) {
                return null;
            }

            return {
                html,
                url: response.url || String(url),
                navigationId,
            };
        } finally {
            window.clearTimeout(timeout);

            if (abortController === controller) {
                navigating = false;
                abortController = null;
            }
        }
    }

    async function visit(url, { historyMode = 'push', scrollTop = 0, rememberScroll = true } = {}) {
        if (rememberScroll) {
            rememberCurrentScroll();
        }

        const targetUrl = new URL(url, window.location.href);

        updateBottomNavigation(targetUrl);
        window.mobileApp?.syncDoubleBackToClose?.(targetUrl);
        showRouteSkeleton(targetUrl);

        const result = await fetchDocument(targetUrl);

        if (!result) {
            return;
        }

        applyDocument(result.html, result.url, {
            historyMode,
            scrollTop,
            navigationId: result.navigationId,
        });
    }

    async function submitForm(form, submitter = null) {
        rememberCurrentScroll();

        const method = (form.method || 'GET').toUpperCase();
        const beforeUrl = new URL(window.location.href);
        const beforeScrollTop = currentScrollTop();
        const actionUrl = formTargetUrl(form);
        const formData = formDataForSubmit(form, submitter);
        let requestUrl = actionUrl;
        const requestOptions = {
            method,
        };

        if (method === 'GET') {
            const query = new URLSearchParams(formData);

            requestUrl = new URL(actionUrl.toString());
            requestUrl.search = query.toString();
        } else {
            requestOptions.body = urlEncodedFormBody(formData);
            requestOptions.headers = {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            };
        }

        try {
            const result = await fetchDocument(requestUrl, requestOptions);

            if (!result) {
                return;
            }

            const finalUrl = new URL(result.url, window.location.href);
            const shouldKeepScroll = shouldPreserveFormScroll(form, method, beforeUrl, finalUrl);

            applyDocument(result.html, result.url, {
                animate: false,
                historyMode: method === 'GET' ? 'push' : 'replace',
                scrollTop: shouldKeepScroll ? beforeScrollTop : 0,
                navigationId: result.navigationId,
            });
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                window.mobileApp?.setFormProcessing?.(form, false);

                return;
            }

            window.mobileApp?.setFormProcessing?.(form, false);
            throw error;
        }
    }

    function onClick(event) {
        const url = shouldHandleAnchor(event);

        if (!url) {
            return;
        }

        if (samePage(new URL(window.location.href), url) && !url.hash) {
            event.preventDefault();
            visit(url, {
                historyMode: 'replace',
                scrollTop: currentScrollTop(),
                rememberScroll: false,
            }).catch((error) => {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                hardNavigate(url);
            });

            return;
        }

        event.preventDefault();

        visit(url).catch((error) => {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            hardNavigate(url);
        });
    }

    function onSubmit(event) {
        const form = event.target;

        if (event.defaultPrevented || !shouldHandleForm(form)) {
            return;
        }

        if (navigating) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.mobileApp?.setFormProcessing?.(form, false);

            return;
        }

        event.preventDefault();
        submitForm(form, event.submitter).catch(() => {
            form.submit();
        });
    }

    function onPopState(event) {
        const scrollTop = Number(event.state?.scrollTop || 0);

        visit(window.location.href, {
            historyMode: 'none',
            scrollTop,
            rememberScroll: false,
        }).catch(() => {
            hardNavigate(window.location.href);
        });
    }

    onMount(() => {
        if (!history.state?.mobileSpa) {
            history.replaceState({
                ...(history.state || {}),
                mobileSpa: true,
                scrollTop: currentScrollTop(),
            }, '', window.location.href);
        }

        document.addEventListener('click', onClick);
        document.addEventListener('submit', onSubmit);
        window.addEventListener('popstate', onPopState);

        return () => {
            if (abortController) {
                abortController.abort();
            }

            document.removeEventListener('click', onClick);
            document.removeEventListener('submit', onSubmit);
            window.removeEventListener('popstate', onPopState);
        };
    });
</script>
