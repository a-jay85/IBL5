import { json, error } from '@sveltejs/kit';
import { query } from '$lib/database/connection.js';

export async function GET({ params }) {
	try {
		const teamId = parseInt(params.teamId);

		if (!teamId || isNaN(teamId)) {
			throw error(400, 'Invalid team ID');
		}

		const teams = await query(
			`
            SELECT 
                teamid,
                team_city as city,
                team_name as name,
                color1,
                color2,
                arena,
                owner_name as owner
            FROM ibl_team_info
            WHERE teamid = ?
        `,
			[teamId]
		);

		if (teams.length === 0) {
			throw error(404, 'Team not found');
		}

		return json({
			success: true,
			team: teams[0],
			timestamp: new Date().toISOString()
		});
	} catch (err: unknown) {
		console.error('Failed to fetch team:', err);

		const message = err instanceof Error ? err.message : 'Unknown error';
		const status = err != null && typeof err === 'object' && 'status' in err ? (err as { status: number }).status : 500;

		return json(
			{
				success: false,
				error: message
			},
			{ status }
		);
	}
}
