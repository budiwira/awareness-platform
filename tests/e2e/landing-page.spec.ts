import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Public landing page', () => {
    test('loads at supported widths without horizontal overflow', async ({ page }) => {
        for (const width of [390, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto('/');
            await expect(page).toHaveTitle(/Security Awareness Platform/);
            await expect(page.locator('.landing-hero')).toBeVisible();
            await expect(page.locator('.landing-metric-card')).toBeVisible();
            await expect.poll(() => page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            )).toBe(true);
        }
    });

    test('supports theme, mobile navigation, Escape, and keyboard focus', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');

        const themeButton = page.getByRole('button', { name: /Aktifkan tema/ });
        const initialTheme = await page.locator('html').getAttribute('data-theme');
        await themeButton.click();
        await expect(page.locator('html')).not.toHaveAttribute('data-theme', initialTheme ?? '');

        await page.getByRole('button', { name: 'Buka navigasi' }).click();
        await expect(page.locator('#mobile-navigation')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('#mobile-navigation')).toHaveCount(0);

        await page.keyboard.press('Tab');
        await expect(page.locator(':focus-visible')).toHaveCount(1);
    });

    test('supports navigation and reduced motion', async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto('/');
        await page.getByRole('button', { name: 'Cara Kerja' }).click();
        await expect(page.locator('#how-it-works')).toBeInViewport();
        await expect(page.locator('.landing-hero-orb')).toHaveCount(2);
        await expect(page.locator('.landing-hero-orb').first()).toBeHidden();
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
        await expect(page).toHaveTitle('Security Awareness Platform - Cyber Security Awareness');
    });
});
