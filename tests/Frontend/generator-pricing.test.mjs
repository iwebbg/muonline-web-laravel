import assert from 'node:assert/strict';
import { test } from 'node:test';
import { itemGrantManager } from '../../resources/js/muonline.js';

/**
 * Builds the same generator state used by the shared purchase form.
 *
 * @param {object} definition Version-owned item and ancient pricing metadata.
 * @param {number} ancientOption Selected encoded ancient set variant.
 * @param {number} markupPercentage Operator markup applied after the full quote.
 * @returns {object} Generator preview state.
 */
function preview(definition, ancientOption, markupPercentage = 0) {
    return itemGrantManager({
        items: { '0:32': definition },
        initialItemCode: '0:32',
        initialAncientOption: ancientOption,
        initialExcellentMask: 0,
        priceModifiers: { ancientFive: 13, ancientTen: 20 },
        markupPercentage,
    });
}

test('native ancient set variants use their explicit +5 and +10 surcharges', () => {
    const definition = { basePrice: 52, ancientPrices: { 5: 13, 6: 13, 9: 20, 10: 20 } };
    assert.equal(preview(definition, 6).calculatedPrice, 65);
    assert.equal(preview(definition, 9).calculatedPrice, 72);
    assert.equal(preview(definition, 0).calculatedPrice, 52);
});

test('operator markup follows the complete native price with the same upward rounding', () => {
    assert.equal(preview({ basePrice: 52, ancientPrices: { 6: 13 } }, 6, 10).calculatedPrice, 72);
});

test('legacy generators retain their established ancient option prices', () => {
    assert.equal(preview({ basePrice: 52 }, 5).calculatedPrice, 65);
    assert.equal(preview({ basePrice: 52 }, 10).calculatedPrice, 72);
});
