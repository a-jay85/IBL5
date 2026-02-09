import {
    Collection,
    type ChatInputCommandInteraction,
    type AutocompleteInteraction,
    type SlashCommandBuilder,
    type SlashCommandOptionsOnlyBuilder,
} from 'discord.js';

import { player } from './player.js';
import { standings } from './standings.js';
import { schedule } from './schedule.js';
import { scores } from './scores.js';
import { leaders } from './leaders.js';
import { roster } from './roster.js';
import { career } from './career.js';
import { boxscore } from './boxscore.js';
import { injuries } from './injuries.js';
import { compare } from './compare.js';
import { team } from './team.js';
import { history } from './history.js';

export interface Command {
    data: SlashCommandBuilder | SlashCommandOptionsOnlyBuilder;
    execute: (interaction: ChatInputCommandInteraction) => Promise<void>;
    autocomplete?: (interaction: AutocompleteInteraction) => Promise<void>;
}

const commandList: Command[] = [
    player,
    standings,
    schedule,
    scores,
    leaders,
    roster,
    career,
    boxscore,
    injuries,
    compare,
    team,
    history,
];

export const commands = new Collection<string, Command>();

for (const command of commandList) {
    commands.set(command.data.name, command);
}
