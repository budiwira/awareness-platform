import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Public landing page', () => {
    test('loads at supported widths without horizontal overflow', async ({ page }) => {
        for (const width of [390, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto('/');
            await expect(page).toHaveTitle(/Cybersecurity Awareness & Readiness Platform/);
            await expect(page.locator('.hero-section')).toBeVisible();
            await expect(page.locator('.readiness-surface')).toBeVisible();
            await expect.poll(() => page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            )).toBe(true);
        }
    });

    test('supports theme, mobile navigation, Escape, and keyboard focus', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');

        const themeButton = page.getByRole('button', { name: /Ganti ke tema/ });
        const initialTheme = await page.locator('html').getAttribute('data-theme');
        await themeButton.click();
        await expect(page.locator('html')).not.toHaveAttribute('data-theme', initialTheme ?? '');

        await page.getByRole('button', { name: 'Buka navigasi' }).click();
        await expect(page.locator('.mobile-nav')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('.mobile-nav')).toHaveCount(0);

        await page.keyboard.press('Tab');
        await expect(page.locator(':focus-visible')).toHaveCount(1);
    });

    test('supports navigation and reduced motion', async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto('/');
        const desktopJourney = page.locator('.desktop-nav').getByRole('button', { name: 'Perjalanan' });
        if (await desktopJourney.isVisible()) {
            await desktopJourney.click();
        } else {
            await page.getByRole('button', { name: 'Buka navigasi' }).click();
            await page.locator('.mobile-nav').getByRole('button', { name: 'Perjalanan' }).click();
        }
        await expect(page.locator('#journey')).toBeInViewport();
        await expect(page.locator('.hero-grid')).toBeVisible();
    });

    test('has no critical accessibility violations and serves the Awareness favicon', async ({ page }) => {
        const errors: string[] = [];
        page.on('console', message => {
            if (message.type() === 'error') errors.push(message.text());
        });
        await page.goto('/');
        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa'])
            .analyze();

        expect(results.violations.filter(violation => violation.impact === 'critical')).toEqual([]);
        expect(errors).toEqual([]);
        await expect(page.locator('link[rel="icon"]')).toHaveAttribute('href', /favicon\.svg$/);
        await expect(page).toHaveTitle('Awareness — Cybersecurity Awareness & Readiness Platform - Cyber Security Awareness');
    });
});
