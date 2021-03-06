const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['setting', 'tax-edit', 'tax', 'edit'],
    before: (browser, done) => {
        global.FixtureService.create('tax').then(() => {
            done();
        });
    },
    'open tax module and look for the tax to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/tax/index', 'Tax')
            .waitForElementVisible('.sw-settings-tax-list-grid');
    },
    'edit tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementPresent(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`)
            .getLocationInView(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`)
            .waitForElementVisible(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`, global.FixtureService.basicFixture.name)
            .clickContextMenuItem('.sw-tax-list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--5`)
            .waitForElementVisible('.sw-settings-tax-detail .sw-card__content')
            .fillField('input[name=sw-field--tax-name]', 'Even higher tax rate', true)
            .waitForElementPresent(page.elements.taxSaveAction)
            .click(page.elements.taxSaveAction)
            .checkNotification('Tax "Even higher tax rate" has been saved successfully.')
            .assert.urlContains('#/sw/settings/tax/detail');
    },
    'verify edited tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`, 'Even higher tax rate');
    },
    after: (browser) => {
        browser.end();
    }
};
