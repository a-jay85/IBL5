import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import type { Attachment } from 'discord.js';
import * as http from 'node:http';
import type { AddressInfo } from 'node:net';
import * as fsp from 'node:fs/promises';
import * as os from 'node:os';
import * as path from 'node:path';
import {
    validateAndSanitize,
    downloadAttachment,
    cacheDir,
    type SanitizedAttachment,
} from './attachments.js';

// Real loopback fixtures over node:http — no fetch mock. The SSRF/https gate lives in
// validateAndSanitize; downloadAttachment trusts the already-sanitized proxyUrl, so the
// download tests build a SanitizedAttachment directly pointing at 127.0.0.1 (http is fine
// there — validateAndSanitize is exercised separately for the https/host rules).

const MESSAGE_ID = '300000000000000003';
const ATTACHMENT_ID = '700000000000000001';

const openServers: http.Server[] = [];

interface Fixture {
    port: number;
    hits: () => number;
}

function startServer(handler: http.RequestListener): Promise<Fixture> {
    let count = 0;
    const server = http.createServer((req, res) => {
        count += 1;
        handler(req, res);
    });
    openServers.push(server);
    return new Promise((resolve) => {
        server.listen(0, '127.0.0.1', () => {
            resolve({ port: (server.address() as AddressInfo).port, hits: () => count });
        });
    });
}

function makeAttachment(
    over: Partial<{ id: string; url: string; proxyURL: string; name: string; size: number; contentType: string | null }> = {},
): Attachment {
    return {
        id: over.id ?? ATTACHMENT_ID,
        url: over.url ?? 'https://cdn.discordapp.com/attachments/1/2/shot.png',
        proxyURL: over.proxyURL ?? 'https://media.discordapp.net/attachments/1/2/shot.png',
        name: over.name ?? 'shot.png',
        size: over.size ?? 1234,
        contentType: over.contentType === undefined ? 'image/png' : over.contentType,
    } as unknown as Attachment;
}

function sanitizedFor(proxyUrl: string, over: Partial<SanitizedAttachment> = {}): SanitizedAttachment {
    return {
        attachmentId: ATTACHMENT_ID,
        originalUrl: 'https://cdn.discordapp.com/attachments/1/2/shot.png',
        proxyUrl,
        filename: 'shot.png',
        contentType: 'image/png',
        fileSize: 1234,
        ext: 'png',
        isImage: true,
        cachePath: path.join(cacheDir(), `${MESSAGE_ID}-${ATTACHMENT_ID}.png`),
        ...over,
    };
}

let tmpDir: string;

beforeEach(async () => {
    tmpDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'bug-att-'));
    process.env.BUG_PIPELINE_ATTACHMENT_CACHE_DIR = tmpDir;
    process.env.BUG_PIPELINE_ATTACHMENT_HOSTS = 'cdn.discordapp.com,media.discordapp.net,127.0.0.1';
    process.env.BUG_PIPELINE_ATTACHMENT_TIMEOUT_MS = '10000';
});

afterEach(async () => {
    while (openServers.length > 0) {
        const s = openServers.pop()!;
        s.closeAllConnections();
        await new Promise<void>((resolve) => s.close(() => resolve()));
    }
    delete process.env.BUG_PIPELINE_ATTACHMENT_CACHE_DIR;
    delete process.env.BUG_PIPELINE_ATTACHMENT_HOSTS;
    delete process.env.BUG_PIPELINE_ATTACHMENT_TIMEOUT_MS;
    await fsp.rm(tmpDir, { recursive: true, force: true });
});

describe('validateAndSanitize', () => {
    it('accepts a well-formed image attachment and derives cachePath from ids, not att.name', () => {
        const s = validateAndSanitize(MESSAGE_ID, makeAttachment());
        expect(s).not.toBeNull();
        expect(s!.attachmentId).toBe(ATTACHMENT_ID);
        expect(s!.ext).toBe('png');
        expect(s!.isImage).toBe(true);
        expect(s!.cachePath).toBe(path.join(tmpDir, `${MESSAGE_ID}-${ATTACHMENT_ID}.png`));
    });

    it('never forms the path from att.name: a traversal name is kept only as display text', () => {
        const s = validateAndSanitize(MESSAGE_ID, makeAttachment({ name: '../../evil.sh', contentType: 'image/png' }));
        expect(s).not.toBeNull();
        // The path is snowflake+ext; the traversal string survives ONLY as the display filename.
        expect(s!.cachePath).toBe(path.join(tmpDir, `${MESSAGE_ID}-${ATTACHMENT_ID}.png`));
        expect(s!.filename).toBe('../../evil.sh');
    });

    it('strips control characters from the display filename', () => {
        const s = validateAndSanitize(MESSAGE_ID, makeAttachment({ name: "a\x00b\x1fc\x7f.png" }));
        expect(s!.filename).toBe('abc.png');
    });

    // The PHP validator drops the WHOLE entry on an empty or >255-BYTE filename, so a name that
    // strips to nothing or overflows the VARCHAR(255) budget would silently cost a valid record.
    // Neither shape is representable after displayFilename().
    it('falls back to the attachment id when the name strips to empty', () => {
        const s = validateAndSanitize(MESSAGE_ID, makeAttachment({ name: '\x00\x1f\x7f' }));
        expect(s!.filename).toBe(`attachment-${ATTACHMENT_ID}`);
    });

    it('falls back to the attachment id when the name is already empty', () => {
        const s = validateAndSanitize(MESSAGE_ID, makeAttachment({ name: '' }));
        expect(s!.filename).toBe(`attachment-${ATTACHMENT_ID}`);
    });

    it('truncates the display filename to 255 BYTES, not 255 characters', () => {
        // 200 CJK chars = 600 UTF-8 bytes. A .slice(0, 255) would pass a length check and
        // still overflow the column; the byte budget is what the DB actually enforces.
        const s = validateAndSanitize(MESSAGE_ID, makeAttachment({ name: '実'.repeat(200) }));
        expect(Buffer.byteLength(s!.filename, 'utf8')).toBeLessThanOrEqual(255);
        expect(s!.filename.length).toBe(85); // 85 × 3 bytes = 255 exactly
    });

    it('never splits a multibyte sequence at the truncation point', () => {
        // 128 × 2-byte chars = 256 bytes: the cut lands mid-sequence and must back off.
        const s = validateAndSanitize(MESSAGE_ID, makeAttachment({ name: 'é'.repeat(128) }));
        expect(s!.filename).not.toContain('�'); // no replacement char ⇒ no split sequence
        expect(Buffer.byteLength(s!.filename, 'utf8')).toBe(254);
    });

    it('leaves a filename inside the byte budget untouched', () => {
        const name = 'a'.repeat(255);
        const s = validateAndSanitize(MESSAGE_ID, makeAttachment({ name }));
        expect(s!.filename).toBe(name);
    });

    it('drops an attachment whose url host is not on the allowlist (before any fetch)', () => {
        const att = makeAttachment({
            url: 'https://evil.example.com/x.png',
            proxyURL: 'https://evil.example.com/x.png',
        });
        expect(validateAndSanitize(MESSAGE_ID, att)).toBeNull();
    });

    it('drops a non-https url', () => {
        const att = makeAttachment({ url: 'http://cdn.discordapp.com/x.png' });
        expect(validateAndSanitize(MESSAGE_ID, att)).toBeNull();
    });

    it('drops a non-snowflake message id or attachment id', () => {
        expect(validateAndSanitize('not-a-snowflake', makeAttachment())).toBeNull();
        expect(validateAndSanitize(MESSAGE_ID, makeAttachment({ id: '12x' }))).toBeNull();
    });

    it('drops an oversize attachment by its declared size', () => {
        expect(validateAndSanitize(MESSAGE_ID, makeAttachment({ size: 10 * 1024 * 1024 + 1 }))).toBeNull();
    });

    it('drops a malformed or missing content type', () => {
        expect(validateAndSanitize(MESSAGE_ID, makeAttachment({ contentType: null }))).toBeNull();
        expect(validateAndSanitize(MESSAGE_ID, makeAttachment({ contentType: 'not-a-mime' }))).toBeNull();
    });

    it('keeps a non-image as valid metadata but with no ext or cachePath (never downloaded)', () => {
        const s = validateAndSanitize(MESSAGE_ID, makeAttachment({ contentType: 'application/pdf' }));
        expect(s).not.toBeNull();
        expect(s!.isImage).toBe(false);
        expect(s!.ext).toBeNull();
        expect(s!.cachePath).toBeNull();
    });
});

describe('downloadAttachment', () => {
    it('downloads image bytes to the cache and returns the absolute path', async () => {
        const payload = Buffer.from('fake-png-bytes');
        const fx = await startServer((_req, res) => {
            res.writeHead(200, { 'content-type': 'image/png' });
            res.end(payload);
        });

        const s = sanitizedFor(`http://127.0.0.1:${fx.port}/shot.png`);
        const result = await downloadAttachment(s);

        expect(result).toBe(s.cachePath);
        expect(await fsp.readFile(s.cachePath!)).toEqual(payload);
        expect(await fsp.readdir(tmpDir)).toEqual([`${MESSAGE_ID}-${ATTACHMENT_ID}.png`]);
    });

    it('serves a second call from the cache without a second request', async () => {
        const fx = await startServer((_req, res) => {
            res.writeHead(200, { 'content-type': 'image/png' });
            res.end(Buffer.from('fake-png-bytes'));
        });

        const s = sanitizedFor(`http://127.0.0.1:${fx.port}/shot.png`);
        await downloadAttachment(s);
        const second = await downloadAttachment(s);

        expect(second).toBe(s.cachePath);
        expect(fx.hits()).toBe(1);
    });

    it('returns null for a non-image without any request (no fetch, no file)', async () => {
        const fx = await startServer((_req, res) => res.end('should-not-be-hit'));

        const s = sanitizedFor(`http://127.0.0.1:${fx.port}/doc.pdf`, {
            contentType: 'application/pdf',
            ext: null,
            isImage: false,
            cachePath: null,
        });

        expect(await downloadAttachment(s)).toBeNull();
        expect(fx.hits()).toBe(0);
        expect(await fsp.readdir(tmpDir)).toEqual([]);
    });

    it('aborts past the 10 MB cap and leaves no .part behind', async () => {
        const oversize = Buffer.alloc(11 * 1024 * 1024, 0x41);
        const fx = await startServer((_req, res) => {
            res.writeHead(200, { 'content-type': 'image/png' });
            res.end(oversize);
        });

        const s = sanitizedFor(`http://127.0.0.1:${fx.port}/big.png`);
        expect(await downloadAttachment(s)).toBeNull();
        expect(fx.hits()).toBe(1); // the fetch DID happen — null came from the byte cap, not an early return
        // Neither the final file nor a `.part` remnant may survive an aborted download.
        expect(await fsp.readdir(tmpDir)).toEqual([]);
    });

    it('returns null when the server hangs past the timeout', async () => {
        process.env.BUG_PIPELINE_ATTACHMENT_TIMEOUT_MS = '300';
        // Never writes a response — the request stalls until AbortSignal.timeout fires.
        const fx = await startServer(() => {
            /* intentionally no res.end() */
        });

        const s = sanitizedFor(`http://127.0.0.1:${fx.port}/hangs.png`);
        expect(await downloadAttachment(s)).toBeNull();
        expect(fx.hits()).toBe(1); // the fetch DID happen — null came from the timeout, not an early return
        expect(await fsp.readdir(tmpDir)).toEqual([]);
    });

    it('returns null (does not throw) when the connection is refused', async () => {
        // Nothing listening on this port — fetch rejects; downloadAttachment must swallow it.
        const s = sanitizedFor('http://127.0.0.1:1/dead.png');
        await expect(downloadAttachment(s)).resolves.toBeNull();
    });
});
