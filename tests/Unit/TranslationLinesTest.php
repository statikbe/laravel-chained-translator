<?php

declare(strict_types=1);

use Statikbe\LaravelChainedTranslator\TranslationLines;

describe('TranslationLines::merge', function () {
    it('places a dotted override inside the nested lines', function () {
        $lines = ['attributes' => ['email' => 'App e-mail', 'name' => 'App name']];

        expect(TranslationLines::merge($lines, ['attributes.email' => 'Custom e-mail']))->toBe([
            'attributes' => ['email' => 'Custom e-mail', 'name' => 'App name'],
        ]);
    });

    it('places a dotted override in the longest existing key', function () {
        $lines = ['custom' => ['items.*.name' => ['required' => 'App required', 'max' => 'App max']]];

        expect(TranslationLines::merge($lines, ['custom.items.*.name.required' => 'Custom required']))->toBe([
            'custom' => ['items.*.name' => ['required' => 'Custom required', 'max' => 'App max']],
        ]);
    });

    it('places a nested override in an existing key with dots', function () {
        $lines = ['custom' => ['items.*.name' => ['required' => 'App required']]];
        $overrides = ['custom' => ['items' => ['*' => ['name' => ['required' => 'Custom required']]]]];

        expect(TranslationLines::merge($lines, $overrides))->toBe([
            'custom' => ['items.*.name' => ['required' => 'Custom required']],
        ]);
    });

    it('keeps keys with dots in nested lines when there is nothing to merge them into', function () {
        $overrides = ['custom' => ['items.*.name' => ['required' => 'Required']]];

        expect(TranslationLines::merge([], $overrides))->toBe($overrides);
    });

    it('nests a dotted override when nothing exists yet', function () {
        expect(TranslationLines::merge(['other' => 'Other'], ['greeting.hello' => 'Hello']))->toBe([
            'other' => 'Other',
            'greeting' => ['hello' => 'Hello'],
        ]);
    });

    it('keeps a dotted override flat when a parent is a translation line', function () {
        expect(TranslationLines::merge(['between' => 'Between'], ['between.numeric' => 'Numeric']))->toBe([
            'between' => 'Between',
            'between.numeric' => 'Numeric',
        ]);
    });

    it('overrides lists by index, like array_replace_recursive', function () {
        expect(TranslationLines::merge(['options' => ['A', 'B']], ['options' => ['X']]))->toBe([
            'options' => ['X', 'B'],
        ]);
    });
});

describe('TranslationLines::undot', function () {
    it('lets a dotted key override the nested line it points to', function () {
        // Laravel looks up the dotted key first, so it is the line in use.
        $lines = ['greeting.hello' => 'Dotted hello', 'greeting' => ['hello' => 'Nested hello', 'bye' => 'Bye']];

        expect(TranslationLines::undot($lines))->toBe([
            'greeting' => ['hello' => 'Dotted hello', 'bye' => 'Bye'],
        ]);
    });
});
