import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { PlayerDetail } from '../api/types.js';
import { playerDetailEmbed } from '../embeds/player-embed.js';
import { handleCommandError, requireUuid } from '../embeds/common.js';
import { playerAutocomplete } from '../autocomplete.js';
import type { Command } from './index.js';

export const player: Command = {
    data: new SlashCommandBuilder()
        .setName('player')
        .setDescription('Look up a player by name')
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

            const response = await apiGet<PlayerDetail>(`players/${uuid}`, undefined, { resourceType: 'player' });
            await interaction.editReply({ embeds: [playerDetailEmbed(response.data)] });
        } catch (error) {
            await handleCommandError(interaction, error);
        }
    },
};
