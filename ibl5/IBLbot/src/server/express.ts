import express from 'express';
import type { Server } from 'http';
import { MessageFlags, type Client } from 'discord.js';
import { config } from '../config.js';
import { handleTradeDM } from './trade-dm.js';
import { handlePlanReviewDM, handleListDecisions, handleAckDecisions } from './plan-review-dm.js';

/**
 * `dir` is the decision-store test seam (see `decision-store.ts`); production
 * omits it and the store falls back to `DECISIONS_DIR`. The `http.Server` is
 * returned so an integration spec can close the listener — `src/index.ts`
 * ignores it.
 */
export function startExpressServer(client: Client, dir?: string): Server {
    const app = express();
    app.use(express.json());

    app.post('/discordDM', (req, res) => {
        // `suppressEmbeds` is declared by the caller (e.g. the notify-discord action)
        // to suppress Discord's auto link-preview cards in owner DMs. GM-bound rich-embed
        // DMs routed through /discordTradeDM and /discordPlanReviewDM are never affected.
        const { receivingUserDiscordID, message, suppressEmbeds } = req.body?.content ?? {};

        if (!receivingUserDiscordID || !message) {
            res.status(400).send('Missing receivingUserDiscordID or message');
            return;
        }

        const payload = suppressEmbeds === true
            ? { content: message, flags: MessageFlags.SuppressEmbeds }
            : message;

        client.users.send(receivingUserDiscordID, payload)
            .then(() => {
                console.log(`DM sent to ${receivingUserDiscordID}`);
                res.send('Discord DM sent!');
            })
            .catch((error: unknown) => {
                console.error('Failed to send DM:', error);
                res.status(500).send('Failed to send DM');
            });
    });

    app.post('/discordTradeDM', handleTradeDM(client));
    app.post('/discordPlanReviewDM', handlePlanReviewDM(client));
    app.get('/planDecisions', handleListDecisions(dir));
    app.post('/planDecisions/ack', handleAckDecisions(dir));

    app.get('/', (_req, res) => {
        res.send('ok');
    });

    return app.listen(config.express.port, '127.0.0.1', () => {
        console.log(`Express server listening on port ${config.express.port}`);
    });
}
