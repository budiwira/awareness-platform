import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

/**
 * E2E fixture metadata verification (non-destructive).
 *
 * All DB setup and repeatability checks run in global-setup.ts
 * BEFORE browser authentication. This spec only reads the
 * generated metadata file and asserts its shape.
 */

test.describe('Fixture metadata', () => {
  const metadata = JSON.parse(
    readFileSync(resolve(__dirname, '.fixtures/ttx.json'), 'utf-8'),
  );

  test('metadata contains all required session IDs', () => {
    expect(metadata).toHaveProperty('readySessionId');
    expect(metadata).toHaveProperty('conflictSessionId');
    expect(metadata).toHaveProperty('dirtySessionId');
    expect(typeof metadata.readySessionId).toBe('number');
    expect(typeof metadata.conflictSessionId).toBe('number');
    expect(typeof metadata.dirtySessionId).toBe('number');
    expect(metadata.readySessionId).toBeGreaterThan(0);
    expect(metadata.conflictSessionId).toBeGreaterThan(0);
    expect(metadata.dirtySessionId).toBeGreaterThan(0);
  });

  test('metadata contains inject IDs', () => {
    expect(metadata).toHaveProperty('conflictActiveInjectId');
    expect(metadata).toHaveProperty('dirtyActiveInjectId');
    expect(typeof metadata.conflictActiveInjectId).toBe('number');
    expect(typeof metadata.dirtyActiveInjectId).toBe('number');
  });

  test('metadata contains tenant slug', () => {
    expect(metadata).toHaveProperty('tenantSlug');
    expect(metadata.tenantSlug).toBe('e2e');
  });

  test('fixture IDs are distinct (no shared mutable state)', () => {
    expect(metadata.readySessionId).not.toBe(metadata.conflictSessionId);
    expect(metadata.readySessionId).not.toBe(metadata.dirtySessionId);
    expect(metadata.conflictSessionId).not.toBe(metadata.dirtySessionId);
  });

  test('metadata contains no credentials or secrets', () => {
    const keys = Object.keys(metadata);
    const forbidden = ['password', 'secret', 'token', 'hash', 'key', 'credential'];
    for (const key of keys) {
      expect(
        forbidden.some((f) => key.toLowerCase().includes(f)),
        `metadata key "${key}" looks like a secret`,
      ).toBe(false);
    }
    // Explicit check for previously-removed fields
    expect(metadata).not.toHaveProperty('facilitatorPassword');
    expect(metadata).not.toHaveProperty('facilitatorEmail');
  });
});
