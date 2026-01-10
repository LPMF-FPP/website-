<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page as BasePage;

class SettingsPage extends BasePage
{
    public function url(): string
    {
        return '/settings';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url())
            ->assertSee('Settings');
    }

    public function elements(): array
    {
        return [
            '@branding-tab' => 'a[href="#branding"]',
            '@numbering-tab' => 'a[href="#numbering"]',
            '@localization-tab' => 'a[href="#localization"]',
            '@save-button' => 'button[type="submit"]',
            '@preview-button' => 'button:contains("Preview PDF")',
        ];
    }

    public function switchToBrandingTab(Browser $browser): void
    {
        $browser->click('@branding-tab')
            ->waitFor('#branding');
    }

    public function switchToNumberingTab(Browser $browser): void
    {
        $browser->click('@numbering-tab')
            ->waitFor('#numbering');
    }

    public function switchToLocalizationTab(Browser $browser): void
    {
        $browser->click('@localization-tab')
            ->waitFor('#localization');
    }

    public function saveBrandingSettings(
        Browser $browser,
        string $orgName,
        string $labCode,
        string $primaryColor
    ): void {
        $browser->type('settings[branding][org_name]', $orgName)
            ->type('settings[branding][lab_code]', $labCode)
            ->type('settings[branding][primary_color]', $primaryColor)
            ->click('@save-button')
            ->waitForText('Settings saved');
    }

    public function saveNumberingSettings(
        Browser $browser,
        string $prefix,
        string $separator,
        string $yearFormat
    ): void {
        $this->switchToNumberingTab($browser);

        $browser->type('settings[numbering][lhu][prefix]', $prefix)
            ->type('settings[numbering][lhu][separator]', $separator)
            ->type('settings[numbering][lhu][year_format]', $yearFormat)
            ->click('@save-button')
            ->waitForText('Settings saved');
    }

    public function saveLocalizationSettings(
        Browser $browser,
        string $timezone,
        string $dateFormat,
        string $language
    ): void {
        $this->switchToLocalizationTab($browser);

        $browser->select('settings[localization][timezone]', $timezone)
            ->select('settings[localization][date_format]', $dateFormat)
            ->select('settings[localization][language]', $language)
            ->click('@save-button')
            ->waitForText('Settings saved');
    }

    public function previewPDF(Browser $browser): void
    {
        $browser->click('@preview-button')
            ->waitForText('Preview');
    }
}
