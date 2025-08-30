import { initializeApp } from 'firebase/app';
import { getFirestore } from 'firebase/firestore';
import { getAuth } from 'firebase/auth';

const firebaseConfig = {
	apiKey: 'AIzaSyAbI80ij_6uWcKhBIi9ADKLF1_PRRunolE',
	authDomain: 'iblv-ac012.firebaseapp.com',
	projectId: 'iblv-ac012',
	storageBucket: 'iblv-ac012.firebasestorage.app',
	messagingSenderId: '1044743414896',
	appId: '1:1044743414896:web:b70538088efbef0a0800b5',
	measurementId: 'G-1PSPKYCJ5T'
};

// Initialize Firebase
export const app = initializeApp(firebaseConfig);
export const db = getFirestore(app);
export const auth = getAuth(app);
