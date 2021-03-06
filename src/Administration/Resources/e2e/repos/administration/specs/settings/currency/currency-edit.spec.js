const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['setting', 'currency-edit', 'currency', 'edit'],
    before: (browser, done) => {
        global.FixtureService.create('currency').then(() => {
            done();
        });
    },
    'open currency module and look for currency to be edited': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/currency/index', 'Currencies')
            .waitForElementVisible('.sw-settings-currency-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`, global.FixtureService.basicFixture.name);
    },
    'edit currency': (browser) => {
        const page = settingsPage(browser);

        browser
            .assert.containsText(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`, global.FixtureService.basicFixture.name)
            .clickContextMenuItem('.sw-currency-list__edit-action', '.sw-context-button__button', `${page.elements.gridRow}--3`)
            .waitForElementVisible('.sw-settings-currency-detail .sw-card__content')
            .clearValue('input[name=sw-field--currency-name]')
            .fillField('input[name=sw-field--currency-name]', 'Yen but true', true)
            .waitForElementPresent(page.elements.currencySaveAction)
            .click(page.elements.currencySaveAction)
            .checkNotification('Currency "Yen but true" has been saved successfully.')
            .assert.urlContains('#/sw/settings/currency/detail');
    },
    'verify edited currency': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-currency-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`, 'Yen but true');
    },
    after: (browser) => {
        browser.end();
    }
};
