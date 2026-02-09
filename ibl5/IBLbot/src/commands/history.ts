import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { PlayerSeasonStats } from '../api/types.js';
import { createBaseEmbed, IBL_BLUE, errorEmbed, formatStat, formatPercentage, playerUrl, teamYearUrl, setDescriptionOrSplit, handleCommandError, requireUuid } from '../embeds/common.js';
import { playerAutocomplete } from '../autocomplete.js';
import type { Command } from './index.js';

export const history: Command = {
    data: new SlashCommandBuilder()
        .setName('history')
        .setDescription('View a player\'s season-by-season stats')
        .addStringOption(option =>
            option
                .setName('name')
                .setDescription('Player name')
                .setRequired(true)
                .setAutocomplete(true),
        ),

    autocomplete: playerAutocomplete,

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('name', true);

        try {
            if (!await requireUuid(interaction, uuid, 'player')) return;

            const response = await apiGet<PlayerSeasonStats[]>(`players/${uuid}/history`, undefined, { resourceType: 'player' });
            const seasons = response.data;

            if (seasons.length === 0) {
                await interaction.editReply({ embeds: [errorEmbed('No season history found for this player.')] });
                return;
            }

            const playerName = seasons[0].player_name;

            const embed = createBaseEmbed()
                .setColor(IBL_BLUE)
                .setTitle(`${playerName} - Season History`)
                .setURL(playerUrl(seasons[0].pid));

            const lines = seasons.map(s => {
                const ppg = formatStat(s.per_game.points);
                const rpg = formatStat(s.per_game.rebounds);
                const apg = formatStat(s.per_game.assists);
                const fg = formatPercentage(s.percentages.fg);
                return `[**${s.year}** ${s.team.name}](${teamYearUrl(s.team.team_id, s.year)}) | ${s.games} GP\nPPG: **${ppg}** | RPG: ${rpg} | APG: ${apg} | FG: ${fg}`;
            });

            setDescriptionOrSplit(embed, lines, 'Seasons');

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
