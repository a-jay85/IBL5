import { db } from '$lib/firebase/firebase';
import { collection, addDoc } from 'firebase/firestore';
import { createRandomPlayers } from '$lib/faker/faker';
import { json, error } from '@sveltejs/kit';
import { dev } from '$app/environment';

// Rate limiting storage
const lastRequest = new Map<string, number>();
const RATE_LIMIT_MS = 60000; // 1 minute

export async function POST({ url, request, getClientAddress }) {
	// Only allow in development environment
	if (!dev) {
		throw error(403, 'This endpoint is only available in development');
	}

	// Check for API key authentication
	const apiKey = request.headers.get('x-api-key');
	if (apiKey !== process.env.SEED_API_KEY) {
		throw error(401, 'Unauthorized - Invalid API key');
	}

	// Rate limiting by IP address
	const clientIP = getClientAddress();
	const now = Date.now();

	if (lastRequest.has(clientIP)) {
		const timeSinceLastRequest = now - lastRequest.get(clientIP)!;
		if (timeSinceLastRequest < RATE_LIMIT_MS) {
			throw error(429, 'Rate limit exceeded. Try again in 1 minute.');
		}
	}

	lastRequest.set(clientIP, now);

	// Limit and validate count parameter
	const count = parseInt(url.searchParams.get('count') || '10');
	if (isNaN(count) || count < 1) {
		throw error(400, 'Count must be a positive number');
	}
	if (count > 50) {
		throw error(400, 'Maximum 50 players allowed per request');
	}

	try {
		const players = createRandomPlayers(count);

		for (const player of players) {
			await addDoc(collection(db, 'iblPlayers'), player);
		}

		return json({
			success: true,
			message: `Successfully added ${count} players to Firestore`,
			count: count
		});
	} catch (err) {
		console.error('Error seeding Firestore:', err);
		throw error(500, 'Internal server error while seeding database');
	}
}
