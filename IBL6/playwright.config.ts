import { defineConfig } from '@playwright/test';

export default defineConfig({
	webServer: process.env.BASE_URL
		? undefined
		: {
				command: 'npm run build && npm run preview',
				port: 4173
			},
	testDir: 'e2e',
	testIgnore: /\.visual\.spec\.ts/,
	use: {
		baseURL: process.env.BASE_URL || 'http://localhost:4173'
	}
});
