// src/routes/api/test/teams/+server.ts
import { json } from '@sveltejs/kit';
import { query } from '$lib/database/connection.js';

export async function GET() {
	try {
		console.log('🏀 Fetching all teams from IBL database');

		const teams = await query(`
            SELECT 
                teamid,
                team_city as city,
                team_name as name,
                color1,
                color2,
                arena,
                owner_name as owner,
                owner_email as ownerEmail,
                formerly_known_as as fka,
                Contract_Wins as contractWins,
                Contract_Losses as contractLosses
            FROM ibl_team_info
            ORDER BY team_name
        `);

		console.log(`✅ Retrieved ${teams.length} teams`);

		return json({
			success: true,
			count: teams.length,
			teams: teams,
			timestamp: new Date().toISOString()
		});
	} catch (err) {
		console.error('❌ Failed to fetch teams:', err);

		return json(
			{
				success: false,
				error: err.message,
				timestamp: new Date().toISOString()
			},
			{ status: 500 }
		);
	}
}
