import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Player, PlayerDetail } from '../api/types.js';
import { playerDetailEmbed } from '../embeds/player-embed.js';
import { errorEmbed, isUuid } from '../embeds/common.js';
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

    async autocomplete(interaction: AutocompleteInteraction) {
        const focused = interaction.options.getFocused();
        if (focused.length < 2) {
            await interaction.respond([]);
            return;
        }

        try {
            const response = await apiGet<Player[]>('players', {
                search: focused,
                per_page: 10,
            });
            const choices = response.data.map(p => ({
                name: `${p.name} (${p.position} - ${p.team?.full_name ?? 'FA'})`,
                value: p.uuid,
            }));
            await interaction.respond(choices);
        } catch {
            await interaction.respond([]);
        }
    },

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        const uuid = interaction.options.getString('name', true);

        try {
            if (!isUuid(uuid)) {
                await interaction.editReply({ embeds: [errorEmbed('Please use autocomplete to select a player.')] });
                return;
            }

            const response = await apiGet<PlayerDetail>(`players/${uuid}`, undefined, { resourceType: 'player' });
            await interaction.editReply({ embeds: [playerDetailEmbed(response.data)] });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
