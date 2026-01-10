<?php

namespace Tests\Browser\Concerns;

use Laravel\Dusk\Browser;

trait InteractsWithSettings
{
    protected function visitSettings(Browser $browser): void
    {
        $browser->visit('/settings')
            ->waitForText('Settings');
    }

    protected function updateBrandingSettings(
        Browser $browser,
        string $orgName,
        string $labCode,
        string $primaryColor
    ): void {
        $browser->type('settings[branding][org_name]', $orgName)
            ->type('settings[branding][lab_code]', $labCode)
            ->type('settings[branding][primary_color]', $primaryColor)
            ->press('Save Settings')
            ->waitForText('Settings saved');
    }

    protected function updateNumberingSettings(
        Browser $browser,
        string $prefix,
        string $separator,
        string $yearFormat
    ): void {
        $browser->click('a[href="#numbering"]')
            ->waitFor('#numbering')
            ->type('settings[numbering][lhu][prefix]', $prefix)
            ->type('settings[numbering][lhu][separator]', $separator)
            ->type('settings[numbering][lhu][year_format]', $yearFormat)
            ->press('Save Settings')
            ->waitForText('Settings saved');
    }

    protected function updateLocalizationSettings(
        Browser $browser,
        string $timezone,
        string $dateFormat,
        string $language
    ): void {
        $browser->click('a[href="#localization"]')
            ->waitFor('#localization')
            ->select('settings[localization][timezone]', $timezone)
            ->select('settings[localization][date_format]', $dateFormat)
            ->select('settings[localization][language]', $language)
            ->press('Save Settings')
            ->waitForText('Settings saved');
    }

    protected function previewPDF(Browser $browser): void
    {
        $browser->press('Preview PDF')
            ->waitForText('Preview');
    }

    protected function assertSettingEquals(string $key, $expected): void
    {
        settings_forget_cache();
        $actual = settings($key);
        $this->assertEquals($expected, $actual);
    }

    protected function resetSettingsCache(): void
    {
        settings_forget_cache();
    }

    protected function navigateToTab(Browser $browser, string $tab): void
    {
        $browser->click("a[href=\"#{$tab}\"]")
            ->waitFor("#{$tab}");
    }
}
