import { EmbedBuilder } from 'discord.js';
import type { ButtonInteraction } from 'discord.js';
import { config } from '../config.js';

interface TradeApiResponse {
    status: string;
    data: {
        accepted?: boolean;
        declined?: boolean;
        story?: string;
    };
}

export async function handleTradeButton(interaction: ButtonInteraction): Promise<void> {
    const customId = interaction.customId;

    // Parse: trade_accept_123 or trade_decline_123
    const match = customId.match(/^trade_(accept|decline)_(\d+)$/);
    if (!match) {
        return;
    }

    const action = match[1]; // 'accept' or 'decline'
    const offerId = match[2];

    // Acknowledge immediately to prevent "interaction failed" timeout
    await interaction.deferUpdate();

    const url = `${config.api.baseUrl}/trades/${offerId}/${action}`;
    const discordUserId = interaction.user.id;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': config.api.key,
            },
            body: JSON.stringify({ discord_user_id: discordUserId }),
        });

        if (response.ok) {
            const body = await response.json() as TradeApiResponse;
            const isAccept = action === 'accept';

            const embed = new EmbedBuilder()
                .setTitle(isAccept ? 'Trade Accepted!' : 'Trade Declined')
                .setColor(isAccept ? 0x57f287 : 0xed4245)
                .setTimestamp();

            if (isAccept && body.data?.story) {
                embed.setDescription(body.data.story.replace(/<br>/g, '\n'));
            }

            await interaction.editReply({ embeds: [embed], components: [] });
        } else if (response.status === 403) {
            const embed = new EmbedBuilder()
                .setTitle('Not Authorized')
                .setDescription('You are not authorized to act on this trade.')
                .setColor(0xed4245)
                .setTimestamp();

            await interaction.editReply({ embeds: [embed], components: [] });
        } else if (response.status === 404) {
            const embed = new EmbedBuilder()
                .setTitle('Trade Already Processed')
                .setDescription('This trade has already been accepted, declined, or withdrawn.')
                .setColor(0xfee75c)
                .setTimestamp();

            await interaction.editReply({ embeds: [embed], components: [] });
        } else {
            const embed = new EmbedBuilder()
                .setTitle('Error')
                .setDescription('Something went wrong. Please use the website to act on this trade:\nhttp://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade')
                .setColor(0xed4245)
                .setTimestamp();

            await interaction.editReply({ embeds: [embed], components: [] });
        }
    } catch (error) {
        console.error(`Trade button error (${action} #${offerId}):`, error);

        const embed = new EmbedBuilder()
            .setTitle('Connection Error')
            .setDescription('Could not reach the server. Please use the website to act on this trade:\nhttp://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade')
            .setColor(0xed4245)
            .setTimestamp();

        try {
            await interaction.editReply({ embeds: [embed], components: [] });
        } catch {
            // Interaction may have expired — nothing we can do
        }
    }
}
