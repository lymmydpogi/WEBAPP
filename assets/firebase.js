import { initializeApp } from 'firebase/app';
import { getFirestore } from 'firebase/firestore';

let firebaseApp = null;
let firestoreDb = null;

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

export function getFirestoreDb() {
    const app = getFirebaseApp();
    if (!app) {
        return null;
    }
    if (!firestoreDb) {
        firestoreDb = getFirestore(app);
    }
    return firestoreDb;
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
