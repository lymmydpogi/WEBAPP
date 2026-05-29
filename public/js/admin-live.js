/**
 * Admin live updates — polling only (no WebSockets / SSE / Firestore).
 * Config: data-* on <body>. Also embedded via admin_live.html.twig for production.
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

    const getOrdersTable = () => document.getElementById('ordersTable');
    const hasDashboard = () => document.querySelector('[id^="live-stat-"]') !== null;

    let lastOrdersRevision = null;
    let lastDashboardRevision = null;
    let ordersPollTimer = null;
    let dashboardPollTimer = null;
    let ordersInFlight = false;
    let dashboardInFlight = false;
    let ordersStopped = false;
    let dashboardStopped = false;
    let ordersAuthFailures = 0;

    const formatRevenue = (value) => {
        const amount = Number(value) || 0;
        return '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const updateDashboardStats = (data) => {
        const setText = (id, text) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = text;
            }
        };

        setText('live-stat-active-services', String(data.activeServices ?? 0));
        setText('live-stat-pending-orders', String(data.pendingOrders ?? 0));
        setText('live-stat-total-users', String(data.totalUsers ?? 0));
        setText('live-stat-total-orders', String(data.totalOrders ?? 0));
        setText('live-stat-monthly-revenue', formatRevenue(data.monthlyRevenue));
        setText('live-stat-total-revenue', formatRevenue(data.totalRevenue));
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

    const fetchJson = async (baseUrl, label) => {
        const sep = baseUrl.includes('?') ? '&' : '?';
        const url = baseUrl + sep + '_=' + Date.now();

        log('request sent', label, url);

        let response;
        try {
            response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
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
        tr.setAttribute('data-fp', rowFingerprint(order));
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

    const syncOrdersTable = (orders) => {
        const ordersTable = getOrdersTable();
        if (!ordersTable) {
            return false;
        }

        const tbody = ordersTable.querySelector('tbody');
        if (!tbody) {
            return false;
        }

        if (!orders.length) {
            if (tbody.querySelector('tr[data-order-id]')) {
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

    const buildRevision = (payload, orders) => {
        if (payload.revision) {
            return String(payload.revision);
        }
        const count = Number(payload.count ?? orders.length);
        const maxId = Number(payload.maxOrderId ?? (orders[0] ? orders[0].id : 0));
        return 'legacy|c' + count + '|m' + maxId;
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
        if (dashboardStopped || !hasDashboard() || !DASHBOARD_URL) {
            return;
        }
        if (dashboardPollTimer) {
            clearTimeout(dashboardPollTimer);
        }
        dashboardPollTimer = setTimeout(runDashboardPoll, POLL_MS);
    };

    const runOrdersPoll = async () => {
        if (ordersStopped) {
            return;
        }

        if (ordersInFlight) {
            scheduleOrdersPoll();
            return;
        }

        if (document.visibilityState === 'hidden') {
            scheduleOrdersPoll();
            return;
        }

        ordersInFlight = true;

        const result = await fetchJson(ORDERS_URL, 'orders');
        ordersInFlight = false;

        if (!result.ok) {
            setLiveStatus(false, result.reason);
            if (result.reason === 'unauthorized' || result.reason === 'not json') {
                ordersAuthFailures += 1;
                if (ordersAuthFailures >= 3) {
                    ordersStopped = true;
                    log('error', 'orders', 'polling stopped:', result.reason);
                }
            }
            scheduleOrdersPoll();
            return;
        }

        ordersAuthFailures = 0;
        setLiveStatus(true, 'Live sync active');

        const payload = result.data;
        if (!payload || payload.success !== true) {
            log('error', 'orders', 'invalid payload');
            scheduleOrdersPoll();
            return;
        }

        const orders = Array.isArray(payload.orders) ? payload.orders : [];
        const revision = buildRevision(payload, orders);

        log('success', '(' + orders.length + ' orders)');

        if (revision !== lastOrdersRevision) {
            lastOrdersRevision = revision;
            if (getOrdersTable()) {
                syncOrdersTable(orders);
            }
        }

        scheduleOrdersPoll();
    };

    const runDashboardPoll = async () => {
        if (dashboardStopped || dashboardInFlight || !hasDashboard() || !DASHBOARD_URL) {
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
            if (result.status === 404) {
                dashboardStopped = true;
                log('error', 'dashboard', 'endpoint not available');
                return;
            }
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
            updateDashboardStats(data);
            lastDashboardRevision = revision;
            log('success', '(dashboard stats updated)');
        }

        scheduleDashboardPoll();
    };

    const start = () => {
        log('started', 'interval=' + POLL_MS + 'ms', 'ordersUrl=' + ORDERS_URL);
        setLiveStatus(true, 'Connecting…');
        void runOrdersPoll();

        if (hasDashboard() && DASHBOARD_URL) {
            void runDashboardPoll();
        }
    };

    const stopAll = () => {
        ordersStopped = true;
        dashboardStopped = true;
        if (ordersPollTimer) clearTimeout(ordersPollTimer);
        if (dashboardPollTimer) clearTimeout(dashboardPollTimer);
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !ordersStopped) {
            ordersAuthFailures = 0;
            void runOrdersPoll();
            if (!dashboardStopped) {
                void runDashboardPoll();
            }
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
