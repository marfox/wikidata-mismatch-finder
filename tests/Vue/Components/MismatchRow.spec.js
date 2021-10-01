import { mount } from '@vue/test-utils';
import { Dropdown } from '@wmde/wikit-vue-components';

import { ReviewDecision } from '@/types/Mismatch.ts';

import MismatchRow from '@/Components/MismatchRow.vue';

describe('MismatchesRow.vue', () => {
    it('accepts a mismatch property', () => {
        const mismatch = {
            property_label: 'Hey hey',
            wikidata_value: 'Some value',
            external_value: 'Another Value',
            review_status: 'pending',
            import_meta: {
                user: {
                    username: 'some_user_name'
                },
                created_at: '2021-09-23'
            },
        };

        const wrapper = mount(MismatchRow, {
            propsData: { mismatch },
            mocks: {
                // Mock the banana-i18n plugin dependency
                $i18n: key => key
            }
        });
        const rowText = wrapper.find( 'tr' ).text();

        expect( wrapper.props().mismatch ).toBe( mismatch );

        [
            mismatch.property_label,
            mismatch.wikidata_value,
            mismatch.external_value,
            `review-status-${mismatch.review_status}`,
            mismatch.import_meta.user.username,
            mismatch.import_meta.created_at
        ].forEach(value => expect(rowText).toContain(value));
    });

    it('shows wikidata label over value when available', () => {
        const mismatch = {
            wikidata_value: 'Some value',
            value_label: 'Some label',
            import_meta: {
                user: {
                    username: 'some_user_name'
                },
                created_at: '2021-09-23'
            },
        };

        const wrapper = mount(MismatchRow, {
            propsData: { mismatch },
            mocks: {
                // Mock the banana-i18n plugin dependency
                $i18n: key => key
            }
        });

        expect( wrapper.props().mismatch ).toBe( mismatch );
        expect( wrapper.find( 'tr' ).text() ).toContain(mismatch.value_label);
    });

    test.each(Object.values(ReviewDecision))
    ('shows a dropdown with the %s option', (currentStatus) => {
        const mismatch = {
            property_label: 'Hey hey',
            wikidata_value: 'Some value',
            external_value: 'Another Value',
            review_status: currentStatus,
            import_meta: {
                user: {
                    username: 'some_user_name'
                },
                created_at: '2021-09-23'
            },
        };

        const wrapper = mount(MismatchRow, {
            propsData: { mismatch },
            mocks: {
                // Mock the banana-i18n plugin dependency
                $i18n: key => `${key}`
            }
        });

        const dropdown = wrapper.findComponent(Dropdown);

        expect(dropdown.props().value).toMatchObject({
            label: `review-status-${currentStatus}`, // Match the i18n key, as the i18n plugin is mocked
            value: currentStatus
        });
    });

    it('emits an update:decision event on dropdown input', async () => {
        const mismatch = {
            property_label: 'Hey hey',
            wikidata_value: 'Some value',
            external_value: 'Another Value',
            review_status: 'pending',
            import_meta: {
                user: {
                    username: 'some_user_name'
                },
                created_at: '2021-09-23'
            },
        };

        const wrapper = mount(MismatchRow, {
            propsData: { mismatch },
            mocks: {
                // Mock the banana-i18n plugin dependency
                $i18n: key => `${key}`
            }
        });

        const dropdown = wrapper.findComponent(Dropdown);

        dropdown.vm.$emit('input', {
            value: ReviewDecision.Wikidata
        });

        // Wait for the event queue to be processed.
        await wrapper.vm.$nextTick();

        const emittedDecisions = wrapper.emitted()['update:decision'];

        // assert event has been emitted
        expect(emittedDecisions).toBeTruthy();

        // assert only one decision was emitted
        expect(emittedDecisions.length).toBe(1);

        // assert correct payload
        expect(emittedDecisions[0]).toEqual([ReviewDecision.Wikidata]);
    });
})
