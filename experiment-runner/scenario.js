import { waitForGroundTruth } from './laravel-control.js';

export async function runScenario(page, control, runId, productSlug, observationMs) {
    await page.goto(`${control.baseUrl}/products/${productSlug}`, { waitUntil: 'domcontentloaded' });
    await waitForGroundTruth(control, runId, 'view_item');
    await page.waitForTimeout(observationMs);
    await page.getByTestId('add-to-cart').click();
    await page.waitForURL('**/cart');
    await waitForGroundTruth(control, runId, 'add_to_cart');
    await page.waitForTimeout(observationMs);
    await page.getByTestId('proceed-to-checkout').click();
    await page.waitForURL('**/checkout');
    await waitForGroundTruth(control, runId, 'begin_checkout');
    await page.waitForTimeout(observationMs);
    await page.getByTestId('place-synthetic-order').click();
    await page.waitForURL('**/orders/**');
    await waitForGroundTruth(control, runId, 'purchase');
    await page.waitForTimeout(observationMs);
}
