import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Player, TeamDetail } from '../api/types.js';
import { rosterEmbed } from '../embeds/roster-embed.js';
import { errorEmbed, isUuid } from '../embeds/common.js';
import { teamAutocomplete } from '../autocomplete.js';
import type { Command } from './index.js';

export const roster: Command = {
    data: new SlashCommandBuilder()
        .setName('roster')
        .setDescription('View a team\'s roster')
        .addStringOption(option =>
            option
                .setName('team')
                .setDescription('Team name')
                .setRequired(true)
                .setAutocomplete(true),
        ),

    autocomplete: teamAutocomplete,

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('team', true);

        try {
            if (!isUuid(uuid)) {
                await interaction.editReply({ embeds: [errorEmbed('Please use autocomplete to select a team.')] });
                return;
            }

            const [teamResponse, rosterResponse] = await Promise.all([
                apiGet<TeamDetail>(`teams/${uuid}`, undefined, { resourceType: 'team' }),
                apiGet<Player[]>(`teams/${uuid}/roster`, undefined, { resourceType: 'team' }),
            ]);

            await interaction.editReply({
                embeds: [rosterEmbed(teamResponse.data, rosterResponse.data)],
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
