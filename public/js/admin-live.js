/**
 * Admin live updates — polling only (no WebSockets / SSE / Firestore).
 * Config: data-* attributes on <body> in ADMIN/base.html.twig.
 */
(function () {
    'use strict';

    const LOG = '[polling]';
    const body = document.body;

    if (!body || body.getAttribute('data-admin-live') !== '1') {
        return;
    }

    const ORDERS_URL = body.getAttribute('data-live-orders-url');
    const DASHBOARD_URL = body.getAttribute('data-live-dashboard-url');
    const ORDERS_PAGE_URL = body.getAttribute('data-live-orders-page-url') || '/order';
    const POLL_MS = Math.min(2000, Math.max(500, Number(body.getAttribute('data-live-poll-ms') || '800')));

    if (!ORDERS_URL) {
        console.warn(LOG, 'error (missing data-live-orders-url)');
        return;
    }

    const ordersTable = document.getElementById('ordersTable');
    const hasDashboard = Boolean(document.getElementById('live-stat-pending-orders'));

    let lastOrdersRevision = null;
    let lastDashboardRevision = null;
    let ordersPollTimer = null;
    let dashboardPollTimer = null;
    let ordersInFlight = false;
    let dashboardInFlight = false;
    let ordersStopped = false;
    let dashboardStopped = false;
    let consecutiveErrors = 0;
    const MAX_ERRORS_BEFORE_STOP = 5;

    const formatRevenue = (value) => {
        const amount = Number(value) || 0;
        return '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const log = (message, ...args) => {
        console.log(LOG, message, ...args);
    };

    const setLiveStatus = (ok, title) => {
        let badge = document.getElementById('admin-live-status');
        if (!badge) {
            badge = document.createElement('div');
            badge.id = 'admin-live-status';
            badge.setAttribute('aria-hidden', 'true');
            document.body.appendChild(badge);
        }
        badge.className = ok
            ? 'admin-live-status admin-live-status--on'
            : 'admin-live-status admin-live-status--off';
        badge.title = title || (ok ? 'Live sync active' : 'Live sync stopped');
    };

    /**
     * @returns {Promise<{ok: true, data: object}|{ok: false, reason: string, status?: number}>}
     */
    const fetchJson = async (baseUrl, label) => {
        const sep = baseUrl.includes('?') ? '&' : '?';
        const url = baseUrl + sep + '_=' + Date.now();

        log('request sent', label, url);

        let response;
        try {
            response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                redirect: 'manual',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });
        } catch (err) {
            const reason = err instanceof Error ? err.message : 'network error';
            log('error', label, reason);
            return { ok: false, reason };
        }

        if (response.status === 401 || response.status === 403) {
            log('error', label, 'unauthorized', response.status);
            return { ok: false, reason: 'unauthorized', status: response.status };
        }

        if (response.status >= 300 && response.status < 400) {
            log('error', label, 'redirect (session expired?)', response.status);
            return { ok: false, reason: 'redirect', status: response.status };
        }

        if (!response.ok) {
            log('error', label, 'http', response.status);
            return { ok: false, reason: 'http ' + response.status, status: response.status };
        }

        const type = (response.headers.get('content-type') || '').toLowerCase();
        if (!type.includes('json')) {
            log('error', label, 'not JSON (login page?)', type);
            return { ok: false, reason: 'not json' };
        }

        try {
            const data = await response.json();
            return { ok: true, data };
        } catch {
            log('error', label, 'invalid JSON body');
            return { ok: false, reason: 'parse error' };
        }
    };

    const rowFingerprint = (order) =>
        [
            order.status || '',
            order.deliveryDate || '',
            order.clientName || '',
            order.clientEmail || '',
            order.serviceName || '',
            order.actionsHtml || '',
        ].join('\u0001');

    const updateRowCells = (tr, order) => {
        const values = [
            String(order.id),
            order.clientName || 'N/A',
            order.clientEmail || 'N/A',
            order.serviceName || 'N/A',
            order.status || 'N/A',
            order.deliveryDate || 'N/A',
        ];
        const cells = tr.querySelectorAll('td');
        values.forEach((text, i) => {
            if (cells[i]) {
                cells[i].textContent = text;
            }
        });
        const actionsCell = cells[6];
        if (actionsCell) {
            const html = order.actionsHtml || '';
            if (actionsCell.innerHTML !== html) {
                actionsCell.innerHTML = html;
            }
        }
    };

    const createOrderRow = (order) => {
        const tr = document.createElement('tr');
        tr.id = 'order-row-' + order.id;
        tr.setAttribute('data-order-id', String(order.id));

        [
            String(order.id),
            order.clientName || 'N/A',
            order.clientEmail || 'N/A',
            order.serviceName || 'N/A',
            order.status || 'N/A',
            order.deliveryDate || 'N/A',
        ].forEach((value) => {
            const td = document.createElement('td');
            td.textContent = value;
            tr.appendChild(td);
        });

        const actions = document.createElement('td');
        actions.innerHTML = order.actionsHtml || '';
        tr.appendChild(actions);

        tr.setAttribute('data-fp', rowFingerprint(order));
        return tr;
    };

    /**
     * Diff-sync tbody: add/update/remove rows only when needed.
     */
    const syncOrdersTable = (orders) => {
        if (!ordersTable) {
            return false;
        }

        const tbody = ordersTable.querySelector('tbody');
        if (!tbody) {
            return false;
        }

        if (!orders.length) {
            const hasDataRows = tbody.querySelector('tr[data-order-id]');
            if (hasDataRows || !tbody.querySelector('.empty-state')) {
                tbody.innerHTML =
                    '<tr><td colspan="7" class="empty-state text-center py-4">No orders found.</td></tr>';
                ordersTable.classList.add('admin-live-flash');
                setTimeout(() => ordersTable.classList.remove('admin-live-flash'), 700);
                return true;
            }
            return false;
        }

        const serverIds = new Set(orders.map((o) => String(o.id)));
        let changed = false;

        tbody.querySelectorAll('tr[data-order-id]').forEach((tr) => {
            if (!serverIds.has(tr.getAttribute('data-order-id'))) {
                tr.remove();
                changed = true;
            }
        });

        const emptyRow = tbody.querySelector('.empty-state');
        if (emptyRow) {
            emptyRow.closest('tr')?.remove();
            changed = true;
        }

        orders.forEach((order, index) => {
            const id = String(order.id);
            const fp = rowFingerprint(order);
            let tr = tbody.querySelector('tr[data-order-id="' + id + '"]');

            if (tr) {
                if (tr.getAttribute('data-fp') !== fp) {
                    updateRowCells(tr, order);
                    tr.setAttribute('data-fp', fp);
                    changed = true;
                }
            } else {
                tr = createOrderRow(order);
                changed = true;
            }

            const anchor = tbody.children[index] || null;
            if (tr !== anchor) {
                tbody.insertBefore(tr, anchor);
                changed = true;
            }
        });

        if (changed) {
            ordersTable.classList.add('admin-live-flash');
            setTimeout(() => ordersTable.classList.remove('admin-live-flash'), 700);
        }

        return changed;
    };

    const showToast = (message) => {
        let el = document.getElementById('admin-live-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'admin-live-toast';
            el.style.cssText =
                'position:fixed;right:16px;bottom:16px;z-index:9999;background:#0f172a;color:#e2e8f0;' +
                'padding:12px 16px;border-radius:10px;border:1px solid rgba(56,189,248,0.5);font-size:14px;' +
                'box-shadow:0 8px 24px rgba(0,0,0,0.35);max-width:300px;';
            document.body.appendChild(el);
        }
        el.innerHTML = message;
        el.style.display = 'block';
        setTimeout(() => {
            el.style.display = 'none';
        }, 4500);
    };

    const handlePollFailure = (reason, stopLabel) => {
        consecutiveErrors += 1;
        setLiveStatus(false, reason);

        if (
            reason === 'unauthorized' ||
            reason === 'redirect' ||
            reason === 'not json' ||
            consecutiveErrors >= MAX_ERRORS_BEFORE_STOP
        ) {
            ordersStopped = true;
            dashboardStopped = true;
            log('error', stopLabel || 'orders', 'polling stopped:', reason);
        }
    };

    const scheduleOrdersPoll = () => {
        if (ordersStopped) {
            return;
        }
        if (ordersPollTimer) {
            clearTimeout(ordersPollTimer);
        }
        ordersPollTimer = setTimeout(runOrdersPoll, POLL_MS);
    };

    const scheduleDashboardPoll = () => {
        if (dashboardStopped || !hasDashboard || !DASHBOARD_URL) {
            return;
        }
        if (dashboardPollTimer) {
            clearTimeout(dashboardPollTimer);
        }
        dashboardPollTimer = setTimeout(runDashboardPoll, POLL_MS);
    };

    const runOrdersPoll = async () => {
        if (ordersStopped || ordersInFlight || document.visibilityState === 'hidden') {
            scheduleOrdersPoll();
            return;
        }

        ordersInFlight = true;

        const result = await fetchJson(ORDERS_URL, 'orders');
        ordersInFlight = false;

        if (!result.ok) {
            handlePollFailure(result.reason, 'orders');
            scheduleOrdersPoll();
            return;
        }

        const payload = result.data;
        if (!payload || payload.success !== true) {
            handlePollFailure('invalid payload', 'orders');
            scheduleOrdersPoll();
            return;
        }

        consecutiveErrors = 0;
        setLiveStatus(true, 'Live sync active');

        const orders = Array.isArray(payload.orders) ? payload.orders : [];
        const revision = String(payload.revision || '');

        log('success', '(' + orders.length + ' orders)');

        if (revision === lastOrdersRevision) {
            scheduleOrdersPoll();
            return;
        }

        lastOrdersRevision = revision;

        if (ordersTable) {
            syncOrdersTable(orders);
        } else if (orders.length > 0) {
            showToast(
                'Orders updated (' +
                    orders.length +
                    '). <a href="' +
                    ORDERS_PAGE_URL +
                    '" style="color:#38bdf8;margin-left:6px;">View orders</a>',
            );
        }

        scheduleOrdersPoll();
    };

    const runDashboardPoll = async () => {
        if (dashboardStopped || dashboardInFlight || !hasDashboard || !DASHBOARD_URL) {
            scheduleDashboardPoll();
            return;
        }

        if (document.visibilityState === 'hidden') {
            scheduleDashboardPoll();
            return;
        }

        dashboardInFlight = true;

        const result = await fetchJson(DASHBOARD_URL, 'dashboard');
        dashboardInFlight = false;

        if (!result.ok) {
            handlePollFailure(result.reason, 'dashboard');
            scheduleDashboardPoll();
            return;
        }

        const data = result.data;
        if (!data || data.success !== true) {
            scheduleDashboardPoll();
            return;
        }

        const revision = String(data.revision || '');
        if (revision !== lastDashboardRevision) {
            const activeEl = document.getElementById('live-stat-active-services');
            const pendingEl = document.getElementById('live-stat-pending-orders');
            const usersEl = document.getElementById('live-stat-total-users');
            const revenueEl = document.getElementById('live-stat-monthly-revenue');

            if (activeEl) activeEl.textContent = String(data.activeServices ?? 0);
            if (pendingEl) pendingEl.textContent = String(data.pendingOrders ?? 0);
            if (usersEl) usersEl.textContent = String(data.totalUsers ?? 0);
            if (revenueEl) revenueEl.textContent = formatRevenue(data.monthlyRevenue);

            lastDashboardRevision = revision;
            log('success', '(dashboard stats updated)');
        }

        scheduleDashboardPoll();
    };

    const start = () => {
        log('started', 'interval=' + POLL_MS + 'ms');
        setLiveStatus(true, 'Connecting…');
        void runOrdersPoll();

        if (hasDashboard && DASHBOARD_URL) {
            void runDashboardPoll();
        }
    };

    const stopAll = () => {
        ordersStopped = true;
        dashboardStopped = true;
        if (ordersPollTimer) clearTimeout(ordersPollTimer);
        if (dashboardPollTimer) clearTimeout(dashboardPollTimer);
        ordersPollTimer = null;
        dashboardPollTimer = null;
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !ordersStopped) {
            consecutiveErrors = 0;
            void runOrdersPoll();
            void runDashboardPoll();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    window.addEventListener('beforeunload', stopAll);
    window.AdminLivePoll = { stop: stopAll, intervalMs: POLL_MS };
})();
