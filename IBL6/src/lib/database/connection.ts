// src/lib/database/connection.ts
import mysql from 'mysql2/promise';
import { dbConfig } from './config.js';

class DatabaseConnection {
	private pool: mysql.Pool | null = null;

	private async getPool(): Promise<mysql.Pool> {
		if (!this.pool) {
			this.pool = mysql.createPool({
				host: dbConfig.mysql.host,
				port: dbConfig.mysql.port,
				database: dbConfig.mysql.database,
				user: dbConfig.mysql.user,
				password: dbConfig.mysql.password,
				waitForConnections: true,
				connectionLimit: 10,
				queueLimit: 0
			});

			// Test connection
			try {
				const connection = await this.pool.getConnection();
				console.log('✅ MariaDB connected successfully to:', dbConfig.mysql.host);
				connection.release();
			} catch (err) {
				console.error('❌ MariaDB connection failed:', err);
				throw err;
			}
		}
		return this.pool;
	}

	async query(text: string, params: unknown[] = []): Promise<mysql.RowDataPacket[]> {
		const pool = await this.getPool();
		const [rows] = await pool.execute(text, params);
		return rows as mysql.RowDataPacket[];
	}

	async queryOne(text: string, params: unknown[] = []): Promise<mysql.RowDataPacket | null> {
		const rows = await this.query(text, params);
		return rows[0] || null;
	}

	async execute(text: string, params: unknown[] = []): Promise<number> {
		const pool = await this.getPool();
		const [result] = await pool.execute(text, params);
		return (result as mysql.ResultSetHeader).affectedRows || 0;
	}

	async insert(text: string, params: unknown[] = []): Promise<number> {
		const pool = await this.getPool();
		const [result] = await pool.execute(text, params);
		return (result as mysql.ResultSetHeader).insertId || 0;
	}

	async close(): Promise<void> {
		if (this.pool) {
			await this.pool.end();
			this.pool = null;
		}
	}
}

// Export singleton instance
export const db = new DatabaseConnection();

// Helper functions for common operations
export async function query(text: string, params: unknown[] = []): Promise<mysql.RowDataPacket[]> {
	return db.query(text, params);
}

export async function queryOne(
	text: string,
	params: unknown[] = []
): Promise<mysql.RowDataPacket | null> {
	return db.queryOne(text, params);
}

export async function execute(text: string, params: unknown[] = []): Promise<number> {
	return db.execute(text, params);
}

export async function insert(text: string, params: unknown[] = []): Promise<number> {
	return db.insert(text, params);
}
