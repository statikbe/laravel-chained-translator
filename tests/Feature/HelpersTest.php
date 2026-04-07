<?php

declare(strict_types=1);

describe('Helper Functions', function () {
    describe('ct_array_undot', function () {
        it('expands dotted keys to nested arrays', function () {
            $input = ['level1.level2.key' => 'value'];
            $expected = ['level1' => ['level2' => ['key' => 'value']]];

            expect(ct_array_undot($input))->toBe($expected);
        });

        it('handles multiple keys at same level', function () {
            $input = [
                'a.b' => 'value1',
                'a.c' => 'value2',
            ];
            $expected = [
                'a' => [
                    'b' => 'value1',
                    'c' => 'value2',
                ],
            ];

            expect(ct_array_undot($input))->toBe($expected);
        });

        it('handles deeply nested keys', function () {
            $input = ['a.b.c.d.e' => 'deep value'];
            $expected = ['a' => ['b' => ['c' => ['d' => ['e' => 'deep value']]]]];

            expect(ct_array_undot($input))->toBe($expected);
        });

        it('preserves non-dotted keys as is', function () {
            $input = ['simple_key' => 'value'];

            expect(ct_array_undot($input))->toBe($input);
        });

        it('handles mix of dotted and non-dotted keys', function () {
            $input = [
                'simple' => 'value1',
                'nested.key' => 'value2',
            ];
            $expected = [
                'simple' => 'value1',
                'nested' => ['key' => 'value2'],
            ];

            expect(ct_array_undot($input))->toBe($expected);
        });

        it('handles empty arrays', function () {
            expect(ct_array_undot([]))->toBeEmpty();
        });

        it('preserves keys with spaces after dots', function () {
            // Keys with ". " (dot followed by space) should not be nested
            $input = ['Mr. Smith' => 'value'];

            expect(ct_array_undot($input))->toBe($input);
        });

        it('handles numeric values correctly', function () {
            $input = ['count.total' => 42];
            $expected = ['count' => ['total' => 42]];

            expect(ct_array_undot($input))->toBe($expected);
        });

        it('handles boolean values correctly', function () {
            $input = ['settings.enabled' => true];
            $expected = ['settings' => ['enabled' => true]];

            expect(ct_array_undot($input))->toBe($expected);
        });

        it('handles null values correctly', function () {
            $input = ['data.value' => null];
            $expected = ['data' => ['value' => null]];

            expect(ct_array_undot($input))->toBe($expected);
        });
    });
});
