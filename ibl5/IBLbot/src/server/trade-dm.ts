import { ActionRowBuilder, ButtonBuilder, ButtonStyle, EmbedBuilder } from 'discord.js';
import type { Client } from 'discord.js';
import type { Request, Response } from 'express';

interface TradeDMPayload {
    receivingUserDiscordID: string;
    type: string;
    tradeOfferId: number;
    offeringTeamName: string;
    tradeText: string;
}

export function handleTradeDM(client: Client) {
    return (req: Request, res: Response): void => {
        const payload = req.body?.content as TradeDMPayload | undefined;

        if (!payload?.receivingUserDiscordID || !payload?.tradeOfferId || !payload?.tradeText) {
            res.status(400).send('Missing required fields: receivingUserDiscordID, tradeOfferId, tradeText');
            return;
        }

        const { receivingUserDiscordID, tradeOfferId, offeringTeamName, tradeText } = payload;

        const embed = new EmbedBuilder()
            .setTitle('New Trade Proposal')
            .setDescription(tradeText)
            .setColor(0x0099ff)
            .setFooter({ text: `From: ${offeringTeamName} · Trade #${tradeOfferId}` })
            .setTimestamp();

        const row = new ActionRowBuilder<ButtonBuilder>().addComponents(
            new ButtonBuilder()
                .setCustomId(`trade_accept_${tradeOfferId}`)
                .setLabel('Accept')
                .setStyle(ButtonStyle.Success),
            new ButtonBuilder()
                .setCustomId(`trade_decline_${tradeOfferId}`)
                .setLabel('Decline')
                .setStyle(ButtonStyle.Danger),
        );

        client.users.send(receivingUserDiscordID, { embeds: [embed], components: [row] })
            .then(() => {
                console.log(`Trade DM sent to ${receivingUserDiscordID} for trade #${tradeOfferId}`);
                res.send('Trade DM sent!');
            })
            .catch((error: unknown) => {
                console.error('Failed to send trade DM:', error);
                res.status(500).send('Failed to send trade DM');
            });
    };
}
