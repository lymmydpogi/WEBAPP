import { initializeApp } from 'firebase/app';

let firebaseApp = null;

/**
 * Firebase Web SDK (project config from Twig / window.__FIREBASE_CONFIG__).
 */
export function getFirebaseApp() {
    if (typeof window === 'undefined') {
        return null;
    }
    const cfg = window.__FIREBASE_CONFIG__;
    if (!cfg || typeof cfg.apiKey !== 'string' || cfg.apiKey === '') {
        if (cfg !== undefined && cfg !== null) {
            console.warn('[firebase] Missing or invalid apiKey in __FIREBASE_CONFIG__.');
        }
        return null;
    }
    if (!firebaseApp) {
        firebaseApp = initializeApp(cfg);
    }
    return firebaseApp;
}

async function initAnalytics(app) {
    const { getAnalytics, isSupported } = await import('firebase/analytics');
    if (await isSupported()) {
        getAnalytics(app);
    }
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        const app = getFirebaseApp();
        if (!app || !window.__FIREBASE_CONFIG__?.measurementId) {
            return;
        }
        initAnalytics(app).catch(() => {});
    });
}
