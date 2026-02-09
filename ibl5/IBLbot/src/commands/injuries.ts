import {
    SlashCommandBuilder,
    type ChatInputCommandInteraction,
} from 'discord.js';
import { apiGet } from '../api/client.js';
import type { Injury } from '../api/types.js';
import { createBaseEmbed, errorEmbed, playerUrl, teamUrl } from '../embeds/common.js';
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

            const lines = response.data.map(inj => {
                return `**[${inj.player.name}](${playerUrl(inj.player.pid)})** (${inj.player.position}) — [${inj.team.name}](${teamUrl(inj.team.team_id)}) | ${inj.injury.days_remaining} day${inj.injury.days_remaining === 1 ? '' : 's'}`;
            });

            const content = lines.join('\n');

            if (content.length <= 4096) {
                embed.setDescription(content);
            } else {
                const mid = Math.ceil(lines.length / 2);
                embed.addFields(
                    { name: 'Injured Players', value: lines.slice(0, mid).join('\n') },
                    { name: '\u200B', value: lines.slice(mid).join('\n') },
                );
            }

            await interaction.editReply({ embeds: [embed] });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            await interaction.editReply({ embeds: [errorEmbed(message)] });
        }
    },
};
