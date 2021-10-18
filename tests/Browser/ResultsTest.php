<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Browser\Pages\ResultsPage;
use App\Models\Mismatch;
use App\Models\ImportMeta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Tests\Browser\Components\DecisionDropdown;

class ResultsTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_shows_item_ids()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new ResultsPage('Q1|Q2'))
                ->assertSee('Q1')
                ->assertSee('Q2');
        });
    }

    public function test_shows_message_for_non_existing_item_ids()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new ResultsPage('Q1|Q2'))
                ->assertSee('No mismatches have been found for')
                ->assertSeeLink('Q1')
                ->assertSeeLink('Q2');
        });
    }

    public function test_shows_table_for_existing_item_and_message_for_non_existing_item()
    {
        // add only one mismatch for Q1
        Mismatch::factory()
            ->for(ImportMeta::factory()->for(User::factory()->uploader()))
            ->state(['statement_guid' => 'Q1$a2b48f1f-426d-91b3-1e0e-1d3c7b236bd0'])
            ->create();

        $this->browse(function (Browser $browser) {
            // check results for Q1 and Q2
            $browser->visit(new ResultsPage('Q1|Q2'));

            $browser->assertSeeIn('#results', 'Q1');
            $browser->assertSeeIn('.wikit-Message--notice', 'Q2');
        });
    }

    public function test_shows_tables_for_existing_items()
    {
        $import = ImportMeta::factory()
        ->for(User::factory()->uploader())
        ->create();

        Mismatch::factory(2)
            ->for($import)
            ->state(new Sequence(
                [
                    'statement_guid' => 'Q2$a2b48f1f-426d-91b3-1e0e-1d3c7b236bd0',
                    'property_id' => 'P610',
                    'wikidata_value' => 'Q513'
                ],
                [
                    'statement_guid' => 'q111$fc6ebc3f-4ea3-3cc8-0f09-a5608474754c',
                    'property_id' => 'P571',
                    'wikidata_value' => '4540 million years BCE'
                ]
            ))
            ->create();

        $expected = [
            [
                'item_label' => 'Earth (Q2)',
                'property_label' => 'highest point',
                'wikidata_value' => 'Mount Everest'
            ],
            [
                'item_label' => 'Mars (Q111)',
                'property_label' => 'inception',
                'wikidata_value' => '4540 million years BCE'
            ]
        ];

        $mismatches = Mismatch::all();

        $this->browse(function (Browser $browser) use ($mismatches, $expected) {
            $idsQuery = $mismatches->implode('item_id', '|');
            $browser->visit(new ResultsPage($idsQuery));

            foreach ($mismatches as $i => $mismatch) {
                $browser->assertSeeLink($expected[$i]['item_label'])
                    ->assertSeeLink($expected[$i]['property_label'])
                    ->assertSeeLink($expected[$i]['wikidata_value'])
                    ->assertSee($mismatch->external_value)
                    ->assertSeeLink($mismatch->importMeta->user->username)
                    ->assertSee($mismatch->importMeta->created_at->toDateString());
            }
        });
    }

    public function test_shows_disabled_decision_forms_for_guests()
    {
        $import = ImportMeta::factory()
            ->for(User::factory()->uploader())
            ->create();

        $mismatch = Mismatch::factory()
            ->for($import)
            ->state([
                'statement_guid' => 'Q2$a2b48f1f-426d-91b3-1e0e-1d3c7b236bd0',
                'property_id' => 'P610',
                'wikidata_value' => 'Q513',
                'review_status' => 'wikidata'
            ])
            ->create();

        $this->browse(function (Browser $browser) use ($mismatch) {
            $dropdownComponent = new DecisionDropdown($mismatch->id);

            $browser->visit(new ResultsPage($mismatch->item_id))
                ->assertGuest()
                ->assertSee('Please log in to be able to make any changes.')
                ->within($dropdownComponent, function ($dropdown) {
                    $dropdown->assertDropdownDisabled();
                })
                ->within("#item-mismatches-$mismatch->item_id", function ($section) {
                    $section->assertButtonDisabled('Apply changes');
                });
        });
    }

    public function test_apply_changes_button_submits_new_review_status()
    {
        $import = ImportMeta::factory()
        ->for(User::factory()->uploader())
        ->create();

        $mismatch = Mismatch::factory()
            ->for($import)
            ->state([
                'statement_guid' => 'Q2$a2b48f1f-426d-91b3-1e0e-1d3c7b236bd0',
                'property_id' => 'P610',
                'wikidata_value' => 'Q513',
                'review_status' => 'wikidata'
            ])
            ->create();

        $this->browse(function (Browser $browser) use ($mismatch) {
            $dropdownComponent = new DecisionDropdown($mismatch->id);

            $browser->loginAs(User::factory()->create())
                ->visit(new ResultsPage($mismatch->item_id))
                ->within($dropdownComponent, function ($dropdown) {
                    // make sure first value is displayed as it should
                    $dropdown->assertOption('Mismatch on Wikidata')
                        // select and assert option
                        ->selectPosition(2, 'Mismatch on external data source');
                })
                // ensure the correct apply button is pressed
                ->within("#item-mismatches-$mismatch->item_id", function ($section) {
                    $section->press('Apply changes');
                })
                //load the page again
                ->refresh()
                ->within($dropdownComponent, function ($dropdown) {
                    $dropdown->assertOption('Mismatch on external data source');
                });
        });
    }
}
