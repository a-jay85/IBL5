import express from 'express';
import type { Client } from 'discord.js';
import { config } from '../config.js';

export function startExpressServer(client: Client): void {
    const app = express();
    app.use(express.json());

    app.post('/discordDM', (req, res) => {
        const { receivingUserDiscordID, message } = req.body?.content ?? {};

        if (!receivingUserDiscordID || !message) {
            res.status(400).send('Missing receivingUserDiscordID or message');
            return;
        }

        client.users.send(receivingUserDiscordID, message)
            .then(() => {
                console.log(`DM sent to ${receivingUserDiscordID}`);
                res.send('Discord DM sent!');
            })
            .catch((error: unknown) => {
                console.error('Failed to send DM:', error);
                res.status(500).send('Failed to send DM');
            });
    });

    app.listen(config.express.port, () => {
        console.log(`Express server listening on port ${config.express.port}`);
    });
}
