import assert from 'node:assert/strict';
import { test } from 'node:test';
import { itemBrowser } from '../../resources/js/item-browser.js';

/**
 * Supplies independent item definitions and the same live catalog boundary used by Blade.
 *
 * @param {number} count Number of distinct items required by the scenario.
 * @returns {object} Browser state ready for searching without a real dialog.
 */
function catalogBrowser(count = 100) {
    const items = Array.from({ length: count }, (_, index) => ({
        code: `12:${index}`,
        name: `Wing ${index}`,
    }));
    const browser = itemBrowser({ catalog: () => items, showPrices: false });
    browser.browserItems = items;

    return browser;
}

test('search matches names and codes without altering the host catalog', () => {
    const browser = catalogBrowser();
    browser.browserQuery = '  WING 99 ';
    assert.deepEqual(browser.browserResults.map((item) => item.code), ['12:99']);
    browser.browserQuery = '12:42';
    assert.deepEqual(browser.browserResults.map((item) => item.name), ['Wing 42']);
    assert.equal(browser.browserItems.length, 100);
});

test('large catalogs remain reachable through bounded pages with an empty result state', () => {
    const browser = catalogBrowser();
    assert.equal(browser.browserPageCount, 3);
    assert.equal(browser.browserPageItems.length, 48);
    browser.browserPage = 3;
    assert.deepEqual(browser.browserPageItems.map((item) => item.code), ['12:96', '12:97', '12:98', '12:99']);
    browser.browserPage = 1;
    browser.browserQuery = 'No matching item';
    assert.equal(browser.browserPageCount, 1);
    assert.deepEqual(browser.browserPageItems, []);
});

test('selection returns the original definition to the originating field before closing', () => {
    const browser = catalogBrowser(1);
    const selected = browser.browserItems[0];
    const events = [];
    browser.$refs = { browserTrigger: { dispatchEvent: (event) => events.push(event) } };
    let closed = false;
    browser.closeBrowser = () => { closed = true; };

    browser.chooseBrowserItem(selected);

    assert.equal(events.length, 1);
    assert.equal(events[0].type, 'item-browser-selected');
    assert.equal(events[0].bubbles, true);
    assert.equal(events[0].detail.item, selected);
    assert.equal(closed, true);
});
