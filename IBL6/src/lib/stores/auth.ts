import { writable } from 'svelte/store';
import { auth } from '$lib/firebase/firebase';
import { onAuthStateChanged, signInWithEmailAndPassword, signOut, type User } from 'firebase/auth';

interface AuthState {
	user: User | null;
	loading: boolean;
	error: string | null;
}

function createAuthStore() {
	const { subscribe, set, update } = writable<AuthState>({
		user: null,
		loading: true,
		error: null
	});

	onAuthStateChanged(auth, async (user) => {
		update((state: AuthState) => ({
			...state,
			user,
			loading: false,
			error: null
		}));
	});

	return {
		subscribe,
		signIn: async (email: string, password: string) => {
			try {
				update((state) => ({ ...state, loading: true, error: null }));
				await signInWithEmailAndPassword(auth, email, password);
			} catch (error: unknown) {
				update((state) => ({
					...state,
					loading: false,
					error: (error as Error).message
				}));
			}
		},
		signOut: async () => {
			try {
				await signOut(auth);
			} catch (error: unknown) {
				update((state) => ({
					...state,
					loading: false,
					error: (error as Error).message
				}));
			}
		},
		clearError: () => update((state) => ({ ...state, error: null }))
	};
}

export const authStore = createAuthStore();
