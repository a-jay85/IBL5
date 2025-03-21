// Require the necessary discord.js classes
const { Client, Events, GatewayIntentBits } = require('discord.js');
const { token } = require('./config.json');

// Create a new client instance
const client = new Client({ intents: [GatewayIntentBits.Guilds] });

// When the client is ready, run this code (only once)
// We use 'c' for the event parameter to keep it separate from the already defined 'client'
client.once(Events.ClientReady, c => {
	console.log(`Ready! Logged in as ${c.user.tag}`);
});

// Log in to Discord with your client's token
client.login(token);

const express = require('express')
const app = express()
const port = 50000

app.use(express.json())

app.post('/discordDM', function(req, res) {
    client.users.send(req.body.content.receivingUserDiscordID, req.body.content.message);
    console.log(req.body.content);
    res.send('Discord DM sent!');
})

app.listen (port, () => {
    console.log(`Example app listening on port ${port}`)
})