import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Injury } from '../api/types.js';
import { createBaseEmbed, pad, errorEmbed } from '../embeds/common.js';
import type { Command } from './index.js';

export const injuries: Command = {
    data: new SlashCommandBuilder()
        .setName('injuries')
        .setDescription('View all injured players'),

    async execute(interaction: ChatInputCommandInteraction) {
        await interaction.deferReply();

        try {
            const response = await apiGet<Injury[]>('injuries');

            const embed = createBaseEmbed()
                .setColor(0xFF6B6B)
                .setTitle('Injury Report');

            if (response.data.length === 0) {
                embed.setDescription('No injured players.');
                await interaction.editReply({ embeds: [embed] });
                return;
            }

            const header = `${pad('Player', 20)} ${pad('Pos', 3)} ${pad('Team', 14)} ${pad('Days', 4, 'right')}`;
            const lines = response.data.map(inj => {
                return `${pad(inj.player.name, 20)} ${pad(inj.player.position, 3)} ${pad(inj.team.name, 14)} ${pad(String(inj.injury.days_remaining), 4, 'right')}`;
            });

            const table = '```\n' + header + '\n' + lines.join('\n') + '\n```';

            if (table.length <= 4096) {
                embed.setDescription(table);
            } else {
                // Split into multiple fields
                const mid = Math.ceil(lines.length / 2);
                embed.addFields(
                    { name: 'Injured Players', value: '```\n' + header + '\n' + lines.slice(0, mid).join('\n') + '\n```' },
                    { name: '\u200B', value: '```\n' + lines.slice(mid).join('\n') + '\n```' },
                );
            }

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
