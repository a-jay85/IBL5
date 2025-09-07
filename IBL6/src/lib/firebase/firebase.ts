import { dev } from '$app/environment';
import type { FirebaseApp } from 'firebase/app';
import type { Firestore } from 'firebase/firestore';
import type { Auth } from 'firebase/auth';

let app: FirebaseApp | null = null;
let db: Firestore | null = null;
let auth: Auth | null = null;

if (dev) {
	// Development - hardcoded config
	const { initializeApp } = await import('firebase/app');
	const { getFirestore } = await import('firebase/firestore');
	const { getAuth } = await import('firebase/auth');

	const firebaseConfig = {
		apiKey: 'AIzaSyAbI80ij_6uWcKhBIi9ADKLF1_PRRunolE',
		authDomain: 'iblv-ac012.firebaseapp.com',
		projectId: 'iblv-ac012',
		storageBucket: 'iblv-ac012.firebasestorage.app',
		messagingSenderId: '1044743414896',
		appId: '1:1044743414896:web:b70538088efbef0a0800b5',
		measurementId: 'G-1PSPKYCJ5T'
	};

	app = initializeApp(firebaseConfig);
	db = getFirestore(app);
	auth = getAuth(app);
} else {
	// Production - try to use environment variables
	try {
		const { initializeApp } = await import('firebase/app');
		const { getFirestore } = await import('firebase/firestore');

		if (import.meta.env.VITE_FIREBASE_API_KEY) {
			const firebaseConfig = {
				apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
				authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
				projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
				storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
				messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
				appId: import.meta.env.VITE_FIREBASE_APP_ID,
				measurementId: import.meta.env.VITE_FIREBASE_MEASUREMENT_ID
			};

			app = initializeApp(firebaseConfig);
			db = getFirestore(app);
		}
	} catch (error) {
		console.log('Firebase not available in production');
	}
}

export { app, db, auth };
