import { describe, it, expect, vi } from 'vitest';
import { loadSimRecapSettings } from './settings.js';

const BASE = { API_BASE_URL: 'https://example.test/api/v1', SIM_RECAP_API_KEY: 'k-secret' };

describe('loadSimRecapSettings', () => {
    it('unset and empty SIM_RECAP_PUBLISH_TARGET both disable the publisher', () => {
        const warn = vi.fn();

        expect(loadSimRecapSettings({}, warn)).toEqual({ enabled: false });
        expect(loadSimRecapSettings({ SIM_RECAP_PUBLISH_TARGET: '' }, warn)).toEqual({ enabled: false });
        expect(loadSimRecapSettings({ SIM_RECAP_PUBLISH_TARGET: '   ' }, warn)).toEqual({ enabled: false });
        expect(warn).not.toHaveBeenCalled();
    });

    it('unknown target value disables with a warning', () => {
        const warn = vi.fn();

        const settings = loadSimRecapSettings({ ...BASE, SIM_RECAP_PUBLISH_TARGET: 'staging' }, warn);

        expect(settings).toEqual({ enabled: false });
        expect(warn).toHaveBeenCalledTimes(1);
        expect(String(warn.mock.calls[0]?.[0])).toContain('staging');
    });

    it('testing target without channel id disables', () => {
        const warn = vi.fn();

        const settings = loadSimRecapSettings(
            { ...BASE, SIM_RECAP_PUBLISH_TARGET: 'testing', SIM_RECAP_TEST_CHANNEL_ID: '' },
            warn,
        );

        expect(settings).toEqual({ enabled: false });
        expect(warn).toHaveBeenCalledTimes(1);
    });

    it('testing target resolves the testing guild with mentions stripped', () => {
        const settings = loadSimRecapSettings(
            { ...BASE, SIM_RECAP_PUBLISH_TARGET: 'testing', SIM_RECAP_TEST_CHANNEL_ID: '555', GIPHY_API_KEY: 'g' },
            vi.fn(),
        );

        expect(settings).toMatchObject({
            enabled: true,
            guildId: '399119059771195392',
            liveSimsChannelId: '555',
            injuriesChannelId: '555',
            stripMentions: true,
            giphyKey: 'g',
        });
    });

    it('empty SIM_RECAP_API_KEY disables without printing the key', () => {
        const warn = vi.fn();

        const settings = loadSimRecapSettings(
            { API_BASE_URL: 'https://example.test', SIM_RECAP_PUBLISH_TARGET: 'prod', SIM_RECAP_API_KEY: '  ', GIPHY_API_KEY: 'giphy-secret' },
            warn,
        );

        expect(settings).toEqual({ enabled: false });
        expect(warn).toHaveBeenCalledTimes(1);
        const line = String(warn.mock.calls[0]?.[0]);
        expect(line).toContain('SIM_RECAP_API_KEY');
        expect(line).not.toContain('giphy-secret');
    });

    it('prod target resolves the prod guild and channels', () => {
        const settings = loadSimRecapSettings({ ...BASE, SIM_RECAP_PUBLISH_TARGET: 'prod' }, vi.fn());

        expect(settings).toEqual({
            enabled: true,
            target: 'prod',
            guildId: '666986450889474053',
            liveSimsChannelId: '682990441641279531',
            injuriesChannelId: '684080867936960518',
            stripMentions: false,
            apiBaseUrl: 'https://example.test/api/v1',
            apiKey: 'k-secret',
            giphyKey: '',
        });
    });

    describe('errors webhook', () => {
        const PROD = { SIM_RECAP_PUBLISH_TARGET: 'prod', SIM_RECAP_API_KEY: 'k', API_BASE_URL: 'https://api.test' };

        it('errors webhook unset keeps the publisher enabled with no warning', () => {
            const warn = vi.fn();

            const settings = loadSimRecapSettings({ ...PROD }, warn);

            expect(settings.enabled).toBe(true);
            expect('errorsWebhookUrl' in settings).toBe(false);
            expect(warn).not.toHaveBeenCalled();
        });

        it('errors webhook empty or blank keeps the publisher enabled with no warning', () => {
            for (const value of ['', '   ']) {
                const warn = vi.fn();

                const settings = loadSimRecapSettings({ ...PROD, SIM_RECAP_ERRORS_WEBHOOK_URL: value }, warn);

                expect(settings.enabled).toBe(true);
                expect('errorsWebhookUrl' in settings).toBe(false);
                expect(warn).not.toHaveBeenCalled();
            }
        });

        it('errors webhook without https warns without echoing the value', () => {
            for (const value of ['http://discord.com/api/webhooks/1/abc', 'discord.com/api/webhooks/1/abc']) {
                const warn = vi.fn();

                const settings = loadSimRecapSettings({ ...PROD, SIM_RECAP_ERRORS_WEBHOOK_URL: value }, warn);

                expect(settings.enabled).toBe(true);
                expect('errorsWebhookUrl' in settings).toBe(false);
                expect(warn).toHaveBeenCalledTimes(1);
                expect(String(warn.mock.calls[0]?.[0])).not.toContain('api/webhooks');
            }
        });

        it('errors webhook https value is returned on enabled settings', () => {
            const warn = vi.fn();

            const settings = loadSimRecapSettings(
                { ...PROD, SIM_RECAP_ERRORS_WEBHOOK_URL: 'https://discord.com/api/webhooks/1/abc' },
                warn,
            );

            expect(settings.enabled).toBe(true);
            expect(settings.enabled && settings.errorsWebhookUrl).toBe('https://discord.com/api/webhooks/1/abc');
            expect(warn).not.toHaveBeenCalled();
        });
    });
});
