<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page as BasePage;

class RequestCreatePage extends BasePage
{
    public function url(): string
    {
        return '/requests/create';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url())
            ->assertSee('Create New Request');
    }

    public function elements(): array
    {
        return [
            '@investigator' => 'select[name="investigator_id"]',
            '@request-letter-number' => 'input[name="request_letter_number"]',
            '@request-letter-date' => 'input[name="request_letter_date"]',
            '@case-title' => 'input[name="case_title"]',
            '@submit' => 'button[type="submit"]',
        ];
    }

    public function fillBasicInfo(
        Browser $browser,
        int $investigatorId,
        string $letterNumber,
        string $letterDate,
        string $caseTitle
    ): void {
        $browser->select('@investigator', $investigatorId)
            ->type('@request-letter-number', $letterNumber)
            ->type('@request-letter-date', $letterDate)
            ->type('@case-title', $caseTitle);
    }

    public function addSample(
        Browser $browser,
        int $index,
        string $name,
        string $description,
        string $quantity,
        string $unit
    ): void {
        $browser->type("samples[{$index}][name]", $name)
            ->type("samples[{$index}][description]", $description)
            ->type("samples[{$index}][quantity]", $quantity)
            ->type("samples[{$index}][unit]", $unit);
    }

    public function submitRequest(Browser $browser): void
    {
        $browser->click('@submit')
            ->waitForText('Request created successfully');
    }

    public function createRequest(
        Browser $browser,
        int $investigatorId,
        string $letterNumber,
        string $letterDate,
        string $caseTitle,
        array $samples = []
    ): void {
        $this->fillBasicInfo($browser, $investigatorId, $letterNumber, $letterDate, $caseTitle);

        foreach ($samples as $index => $sample) {
            $this->addSample(
                $browser,
                $index,
                $sample['name'],
                $sample['description'],
                $sample['quantity'],
                $sample['unit']
            );
        }

        $this->submitRequest($browser);
    }
}
