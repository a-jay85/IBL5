// src/routes/api/test/db/+server.ts
import { json, error } from '@sveltejs/kit';
import { query, queryOne } from '$lib/database/connection.js';

export async function GET() {
	try {
		// Test basic connection
		const result = await query('SELECT 1 as test_value, NOW() as current_time');

		// Test database exists
		const dbInfo = await query(`
            SELECT 
                DATABASE() as current_db,
                VERSION() as db_version,
                USER() as current_user
        `);

		// Test if tables exist
		const tables = await query(`
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
        `);

		// Test connection pool stats (if available)
		const processlist = await query('SHOW PROCESSLIST');

		return json({
			success: true,
			connection: 'OK',
			test_query: result[0],
			database_info: dbInfo[0],
			tables: tables.map((t) => t.TABLE_NAME),
			active_connections: processlist.length,
			timestamp: new Date().toISOString()
		});
	} catch (err) {
		console.error('Database test failed:', err);

		return json(
			{
				success: false,
				error: err.message,
				code: err.code,
				sqlState: err.sqlState,
				timestamp: new Date().toISOString()
			},
			{ status: 500 }
		);
	}
}
