import type { Attachment } from 'discord.js';
import * as os from 'node:os';
import * as path from 'node:path';
import * as fs from 'node:fs';
import * as fsp from 'node:fs/promises';

// ── Attachment capture module ───────────────────────────────────────────────
// Every field on a discord.js Attachment is attacker-controlled. This module
// NEVER forms a filesystem path from `att.name`: the on-disk name is derived
// from two snowflakes plus an extension mapped from `contentType` against a
// fixed allowlist. Downloads are capped (10 MB / 10 s) and restricted to an
// https host allowlist. Every path is wrapped — nothing here throws; a failure
// degrades to URL-only (local_path stays null).

const MAX_BYTES = 10 * 1024 * 1024; // 10 MiB — mirrors AttachmentInputValidator::MAX_FILE_SIZE.
const DEFAULT_TIMEOUT_MS = 10_000;
const MAX_CONTENT_TYPE_LENGTH = 100;

// Extension is mapped from contentType, NEVER from att.name. Only these image
// types are byte-cached; anything else keeps its metadata but is not downloaded.
const EXT_BY_CONTENT_TYPE: Record<string, string> = {
    'image/png': 'png',
    'image/jpeg': 'jpg',
    'image/gif': 'gif',
    'image/webp': 'webp',
};

const SNOWFLAKE = /^\d{1,20}$/;
const MIME = /^[\w.+-]+\/[\w.+-]+$/;

export interface SanitizedAttachment {
    attachmentId: string;
    originalUrl: string; // att.url — the durable-but-expiring CDN link stored in the DB
    proxyUrl: string; // att.proxyURL — what we actually fetch bytes from
    filename: string; // display-only, control chars stripped; never used to build a path
    contentType: string;
    fileSize: number | null;
    ext: string | null; // non-null only for the mapped image types
    isImage: boolean;
    cachePath: string | null; // absolute; non-null only when ext is set
}

/**
 * The attachment byte cache root. Env-overridable so tests can point at a
 * scratch dir rather than the real cache. An empty override falls back to the
 * default (an empty path would join to a relative, world-writable location).
 * MUST resolve to the same absolute path as the driver's ATTACHMENT_CACHE_DIR
 * default (bin/bug-pipeline-tick) — see ADR-0098 cross-process invariant.
 */
export function cacheDir(): string {
    const override = process.env.BUG_PIPELINE_ATTACHMENT_CACHE_DIR;
    if (override !== undefined && override.trim() !== '') {
        return override;
    }
    return `${os.homedir()}/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/bug-pipeline/attachments`;
}

/** Download timeout in ms. Env-overridable purely so the hanging-server test need not wait the full 10 s; prod uses the default. */
function timeoutMs(): number {
    const raw = process.env.BUG_PIPELINE_ATTACHMENT_TIMEOUT_MS;
    if (raw !== undefined && /^\d+$/.test(raw.trim())) {
        return Number(raw.trim());
    }
    return DEFAULT_TIMEOUT_MS;
}

/** https-host allowlist. Env-overridable so a loopback fixture server can be reached under the same SSRF defense prod uses. */
function allowedHosts(): string[] {
    const override = process.env.BUG_PIPELINE_ATTACHMENT_HOSTS;
    if (override !== undefined && override.trim() !== '') {
        const hosts = override
            .split(',')
            .map((h) => h.trim().toLowerCase())
            .filter((h) => h !== '');
        if (hosts.length > 0) return hosts;
    }
    return ['cdn.discordapp.com', 'media.discordapp.net'];
}

function stripControlChars(s: string): string {
    // eslint-disable-next-line no-control-regex
    return s.replace(/[\x00-\x1F\x7F]/g, '');
}

function isAllowedHttpsUrl(rawUrl: string): boolean {
    let u: URL;
    try {
        u = new URL(rawUrl);
    } catch {
        return false;
    }
    return u.protocol === 'https:' && allowedHosts().includes(u.hostname.toLowerCase());
}

/**
 * Narrow a live Attachment to a SanitizedAttachment, or drop it (null). Drops
 * unless ALL hold: messageId and att.id are 1–20 digit snowflakes; contentType
 * is a bounded MIME token; att.url and att.proxyURL are https on an allowed
 * host; att.size is within the byte cap. Runs before any network access.
 */
export function validateAndSanitize(messageId: string, att: Attachment): SanitizedAttachment | null {
    if (!SNOWFLAKE.test(messageId) || !SNOWFLAKE.test(att.id)) return null;

    const contentType = att.contentType;
    if (
        typeof contentType !== 'string' ||
        contentType.length === 0 ||
        contentType.length > MAX_CONTENT_TYPE_LENGTH ||
        !MIME.test(contentType)
    ) {
        return null;
    }

    if (typeof att.size === 'number' && att.size > MAX_BYTES) return null;

    if (!isAllowedHttpsUrl(att.url) || !isAllowedHttpsUrl(att.proxyURL)) return null;

    const ext = EXT_BY_CONTENT_TYPE[contentType] ?? null;
    const isImage = contentType.startsWith('image/');
    const cachePath = ext !== null ? path.join(cacheDir(), `${messageId}-${att.id}.${ext}`) : null;

    return {
        attachmentId: att.id,
        originalUrl: att.url,
        proxyUrl: att.proxyURL,
        filename: stripControlChars(att.name ?? ''),
        contentType,
        fileSize: typeof att.size === 'number' ? att.size : null,
        ext,
        isImage,
        cachePath,
    };
}

async function safeUnlink(p: string): Promise<void> {
    try {
        await fsp.unlink(p);
    } catch {
        /* already gone — nothing to clean up */
    }
}

function writeChunk(out: fs.WriteStream, chunk: Buffer): Promise<void> {
    return new Promise((resolve, reject) => {
        // The callback fires once the chunk is flushed, so awaiting it applies backpressure.
        out.write(chunk, (err) => (err ? reject(err) : resolve()));
    });
}

/**
 * Download the bytes to `${messageId}-${att.id}.${ext}` and return the absolute
 * path, or null. Returns null (metadata still valid, degrade to URL-only) when:
 * the type is not a mapped image; the file is already cached (fast path, no
 * fetch); or the fetch times out / exceeds the size cap / redirects / errors.
 * Streams to a `.part` temp file counting bytes (Content-Length is untrusted),
 * then renames into place so no partial file is ever observable as a cache hit.
 * NEVER throws.
 */
export async function downloadAttachment(s: SanitizedAttachment): Promise<string | null> {
    if (!s.isImage || s.ext === null || s.cachePath === null) return null;
    const cachePath = s.cachePath;
    const tmpPath = `${cachePath}.part`;

    try {
        try {
            await fsp.access(cachePath);
            return cachePath; // already cached — thread-reply re-reads stay cheap
        } catch {
            /* not cached yet — fall through to fetch */
        }

        await fsp.mkdir(cacheDir(), { recursive: true });

        const res = await fetch(s.proxyUrl, {
            signal: AbortSignal.timeout(timeoutMs()),
            redirect: 'error',
        });
        if (!res.ok || res.body === null) return null;

        const out = fs.createWriteStream(tmpPath);
        let received = 0;
        try {
            for await (const chunk of res.body as AsyncIterable<Uint8Array>) {
                received += chunk.length;
                if (received > MAX_BYTES) {
                    out.destroy();
                    await safeUnlink(tmpPath);
                    return null; // Content-Length lied or the stream ran long — abort, leave no .part
                }
                await writeChunk(out, Buffer.from(chunk));
            }
            await new Promise<void>((resolve, reject) => {
                out.end((err?: Error | null) => (err ? reject(err) : resolve()));
            });
        } catch (streamErr) {
            out.destroy();
            await safeUnlink(tmpPath);
            throw streamErr;
        }

        await fsp.rename(tmpPath, cachePath);
        return cachePath;
    } catch (err) {
        console.error(`downloadAttachment: failed for ${s.attachmentId} (degrading to URL-only):`, err);
        await safeUnlink(tmpPath);
        return null;
    }
}
