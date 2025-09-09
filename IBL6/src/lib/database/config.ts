import { dev } from '$app/environment';
import dotenv from 'dotenv';

if (dev) {
	const result = dotenv.config();

	if (result.error) {
		console.warn('Could not load .env file:', result.error.message);
		console.warn('Using system environment variables or defaults');
	}
}

export const dbConfig = {
	mysql: {
		host: process.env.DB_HOST || (dev ? 'localhost' : undefined),
		port: parseInt(process.env.DB_PORT || '3306'),
		database: process.env.DB_NAME || (dev ? 'ibl_dev' : undefined),
		user: process.env.DB_USER || (dev ? 'root' : undefined),
		password: process.env.DB_PASSWORD || (dev ? '' : undefined)
	}
};

// Validate required config in production
if (!dev) {
	const required = ['DB_HOST', 'DB_NAME', 'DB_USER'];
	const missing = required.filter((key) => !process.env[key]);

	if (missing.length > 0) {
		throw new Error(`Missing required environment variables: ${missing.join(', ')}`);
	}
}
