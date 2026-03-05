<?php

declare(strict_types=1);

describe('Helper Functions', function () {
    describe('is_php_file', function () {
        it('returns true for .php files', function () {
            expect(is_php_file('messages.php'))->toBeTrue();
            expect(is_php_file('test.php'))->toBeTrue();
        });

        it('returns true for .PHP files (case insensitive)', function () {
            expect(is_php_file('messages.PHP'))->toBeTrue();
            expect(is_php_file('test.Php'))->toBeTrue();
        });

        it('returns false for non-PHP files', function () {
            expect(is_php_file('messages.json'))->toBeFalse();
            expect(is_php_file('test.txt'))->toBeFalse();
            expect(is_php_file('script.js'))->toBeFalse();
        });

        it('returns false for files without extension', function () {
            expect(is_php_file('messages'))->toBeFalse();
            expect(is_php_file(''))->toBeFalse();
        });

        it('handles paths correctly', function () {
            expect(is_php_file('/path/to/file.php'))->toBeTrue();
            expect(is_php_file('path/to/messages.php'))->toBeTrue();
        });
    });

    describe('is_json_file', function () {
        it('returns true for .json files', function () {
            expect(is_json_file('messages.json'))->toBeTrue();
            expect(is_json_file('test.json'))->toBeTrue();
        });

        it('returns true for .JSON files (case insensitive)', function () {
            expect(is_json_file('messages.JSON'))->toBeTrue();
            expect(is_json_file('test.Json'))->toBeTrue();
        });

        it('returns false for non-JSON files', function () {
            expect(is_json_file('messages.php'))->toBeFalse();
            expect(is_json_file('test.txt'))->toBeFalse();
            expect(is_json_file('script.js'))->toBeFalse();
        });

        it('returns false for files without extension', function () {
            expect(is_json_file('messages'))->toBeFalse();
            expect(is_json_file(''))->toBeFalse();
        });

        it('handles paths correctly', function () {
            expect(is_json_file('/path/to/file.json'))->toBeTrue();
            expect(is_json_file('path/to/messages.json'))->toBeTrue();
        });
    });

    describe('array_undot', function () {
        it('expands dotted keys to nested arrays', function () {
            $input = ['level1.level2.key' => 'value'];
            $expected = ['level1' => ['level2' => ['key' => 'value']]];

            expect(array_undot($input))->toBe($expected);
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

            expect(array_undot($input))->toBe($expected);
        });

        it('handles deeply nested keys', function () {
            $input = ['a.b.c.d.e' => 'deep value'];
            $expected = ['a' => ['b' => ['c' => ['d' => ['e' => 'deep value']]]]];

            expect(array_undot($input))->toBe($expected);
        });

        it('preserves non-dotted keys as is', function () {
            $input = ['simple_key' => 'value'];

            expect(array_undot($input))->toBe($input);
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

            expect(array_undot($input))->toBe($expected);
        });

        it('handles empty arrays', function () {
            expect(array_undot([]))->toBeEmpty();
        });

        it('preserves keys with spaces after dots', function () {
            // Keys with ". " (dot followed by space) should not be nested
            $input = ['Mr. Smith' => 'value'];

            expect(array_undot($input))->toBe($input);
        });

        it('handles numeric values correctly', function () {
            $input = ['count.total' => 42];
            $expected = ['count' => ['total' => 42]];

            expect(array_undot($input))->toBe($expected);
        });

        it('handles boolean values correctly', function () {
            $input = ['settings.enabled' => true];
            $expected = ['settings' => ['enabled' => true]];

            expect(array_undot($input))->toBe($expected);
        });

        it('handles null values correctly', function () {
            $input = ['data.value' => null];
            $expected = ['data' => ['value' => null]];

            expect(array_undot($input))->toBe($expected);
        });
    });
});
