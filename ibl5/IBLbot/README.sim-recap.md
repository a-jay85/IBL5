# Sim Recap Publisher

A module inside the prod IBLbot process (`src/sim-recap/`). It posts a finished sim's recap into Discord as a slow drip: a thread in `#live-sims`, the game recaps in the thread, the injuries in `#injuries`, and a closing GIF. It runs in the same process and under the same Discord token as the rest of IBLbot. It writes nothing to MySQL directly. PHP builds the whole script and keeps the progress, and every DB effect goes through the `/api/v1/sim-recap-publish/*` routes. The PHP side is in `ibl5/classes/SimRecap/README.md` under "Discord publisher".

Two directions of I/O:
- **Outbound (IBLbot → PHP):** `claim-next`, `progress`, and `finish`, sent with the service-tier key in `X-API-Key`.
- **Inbound (PHP → IBLbot):** a localhost poke on `POST /simRecapPublish` that starts a sweep early.

## Settings

`loadSimRecapSettings` in `src/sim-recap/settings.ts` reads the variables below. They are optional on purpose. The module lives apart from `src/config.ts`, so a missing new variable never stops IBLbot from starting. The publisher disabled is a legal state. It logs `sim-recap publisher disabled` at startup and the sweeper does nothing.

| Variable | Unset or empty | Set |
|---|---|---|
| `SIM_RECAP_PUBLISH_TARGET` | Publisher disabled | `prod` posts to the prod guild and the fixed `#live-sims` and `#injuries` channels, with mentions live. `testing` posts to the testing guild and strips every mention. Any other value logs a warning and disables the publisher |
| `SIM_RECAP_TEST_CHANNEL_ID` | Ignored for `prod`. With `testing`, logs a warning and disables the publisher | The one channel that receives both the `#live-sims` and `#injuries` posts when the target is `testing` |
| `SIM_RECAP_API_KEY` | Logs a warning and disables the publisher | A service-tier `ibl_api_keys` key (any tier other than `standard`). A standard key gets a 403 from every route |
| `SIM_RECAP_ERRORS_WEBHOOK_URL` | Failure alerts go to the bot log only, and the bot logs one line saying so at startup | The #errors channel webhook. The publisher posts one alert per sim and failure kind: a sweep crash, a step that gave up after its retries, or a sim stuck at the same step for three sweeps. A value that does not start with `https://` logs a warning and is ignored. The bot never logs this URL |
| `GIPHY_API_KEY` | The closing GIF is skipped and the log says `GIPHY_API_KEY unset; skipping GIF`. The drip still finishes | Used for the GIF search. A failed or empty search also skips the GIF |

`API_BASE_URL` is shared with the rest of IBLbot and is the base for the three routes. An empty value disables the publisher. IBLbot's own `src/config.ts` also requires it, so IBLbot does not start without it.

Whitespace around a value is trimmed, so a variable holding only spaces counts as empty.

## Commissioner switch

Two switches must both be on before anything posts:

1. `SIM_RECAP_PUBLISH_TARGET`, the deploy-level setting in IBLbot's environment file.
2. The League Control Panel setting "Sim recap Discord posting" (`ibl_settings` key `Sim Recap Discord Posting`, default Off).

The sweeper calls `POST sim-recap-publish/enabled` before each claim, and the publisher calls it before each step. Any failure to read the answer counts as off: a non-2xx status, a malformed body, or a network error. Switching posting off in the middle of a sim stops the run before the next message, and the message already sent keeps its progress row. Switching it back on resumes at `last_step + 1`.

The server applies the same switch inside `claim-next`, which answers 204 and spends no attempt while the switch is off. The bot-side check covers a PHP deploy that has not shipped the `enabled` route yet: the read gets a 404 and the bot stays silent.

## Sweeper and poke

`createSweeper` in `src/sim-recap/sweeper.ts` runs one sweep at startup and then every 300 seconds (`SWEEP_INTERVAL_MS`). A sweep asks `claim-next` for a sim, posts it, and asks again until the API answers 204. Only one sweep runs at a time. A sweep that overlaps another returns at once.

The prod `simRecapQueue.php notify` verb enqueues a `done` Regular Season sim and then pokes IBLbot. The poke is `POST /simRecapPublish` on the loopback Express server (`127.0.0.1`, route in `src/server/express.ts`). It reads nothing from the request and answers 202. It has no auth because its only effect is an early sweep. A failed poke only delays the drip until the next timer sweep.

Each process makes one UUID at startup and uses it as the claim token. A restarted bot gets a new token, so the old claim goes stale after 300 seconds without a heartbeat and the new process takes the row over.

## Resume and reconcile

`runPublish` in `src/sim-recap/publisher.ts` posts the steps in order. It skips steps up to `lastStep` and reports each posted step to `progress`. A 409 from `progress` means the claim is lost, so the sweep stops with `claim lost`. A rate limit (429) from Discord waits for the retry time and tries up to 3 more times.

A crash between a Discord post and its `progress` call leaves `lastStep` one behind. Before it posts the first pending step, the publisher reads the bot's own latest message in the target channel. When that message matches the step (the same text, or any `https://` link for the GIF step), it counts the step as posted and does not send it again. For the thread-start step it also reuses a thread that already exists on that message. Only the first pending step is checked.

The thread name is cut to 100 characters. Every message goes out with `parse: []`, so only the user ids and the one role id listed on the step can ping.

## Failure alerts

The bot keeps its alert history in memory. A restart clears it, so a failure that is still live alerts once more after a restart. Alerts never mention anyone (ADR-0254). A failed webhook post is logged by error class or HTTP status and never stops publishing. On the PHP side, a failed enqueue in the `notify` verb and a failed poke both log at error level, and the existing #errors handler posts them.

## Dry run

Print what the publisher would post without logging into Discord:

```bash
cd ibl5/IBLbot && npm run build
php ../scripts/simRecapQueue.php publish-script --sim=N > /tmp/sim-N-script.json
npm run sim-recap:dry-run -- --script=/tmp/sim-N-script.json
```

The command takes a script JSON (the `publish-script` output, or a claim that wraps one). For each step it prints the kind, the channel, the delay, the allowed mentions, and the text. It exits 2 on a bad argument and 1 when the file has no steps. For a full run against a test guild, see the testing-guild steps in `ibl5/docs/OPERATIONS_RUNBOOK.md`, section "Sim recap Discord publisher".
