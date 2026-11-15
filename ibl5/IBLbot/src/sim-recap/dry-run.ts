// Offline dry run: prints what the publisher would post for a script JSON (the output of
// `simRecapQueue.php publish-script`, or a claim wrapping one). It never logs into Discord.
import fs from 'fs';
import type { PublishStep } from './publisher.js';

const USAGE = 'usage: node dist/sim-recap/dry-run.js --script=<path>';
const CHANNEL_NAMES: Record<PublishStep['channel'], string> = {
    live_sims: '#live-sims',
    thread: '(thread)',
    injuries: '#injuries',
};

function parseScriptPath(argv: string[]): string | null {
    if (argv.length !== 1) return null;
    const arg = argv[0] ?? '';
    if (!arg.startsWith('--script=')) return null;
    const path = arg.slice('--script='.length);
    return path === '' ? null : path;
}

function main(argv: string[]): number {
    const path = parseScriptPath(argv);
    if (path === null) {
        process.stderr.write(`${USAGE}\n`);
        return 2;
    }

    const parsed = JSON.parse(fs.readFileSync(path, 'utf8')) as {
        steps?: PublishStep[];
        script?: { steps?: PublishStep[] };
    };
    const steps = parsed.steps ?? parsed.script?.steps;
    if (!Array.isArray(steps)) {
        process.stderr.write('dry-run: no steps found in script\n');
        return 1;
    }

    for (const step of steps) {
        const allowedMentions = { parse: [], users: step.mentionUsers, roles: step.mentionRoles, repliedUser: false };
        process.stdout.write(
            [
                `--- step ${step.index} ---`,
                `kind: ${step.kind}`,
                `channel: ${CHANNEL_NAMES[step.channel]}`,
                `delayBeforeMs: ${step.delayBeforeMs}`,
                `allowedMentions: ${JSON.stringify(allowedMentions)}`,
                `content:\n${step.kind === 'gif' ? `(GIF search: ${step.gifQuery ?? ''})` : step.content}`,
                '',
            ].join('\n') + '\n',
        );
    }
    return 0;
}

process.exitCode = main(process.argv.slice(2));
