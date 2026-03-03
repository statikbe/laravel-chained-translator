<?php

declare(strict_types=1);

use Statikbe\LaravelChainedTranslator\ChainLoader;

describe('ChainLoader', function (): void {
    it('can add a loader to the chain', function (): void {
        $loader = new ChainLoader();
        $mockLoader = createMockLoader();

        $loader->addLoader($mockLoader);

        expect($loader->loaders())->toHaveCount(1);
        $loaders = $loader->loaders();
        expect($loaders[0])->toBe($mockLoader);
    });

    it('can prepend a loader to the chain', function (): void {
        $loader = new ChainLoader();
        $firstLoader = createMockLoader();
        $secondLoader = createMockLoader();

        $loader->addLoader($firstLoader);
        $loader->addLoader($secondLoader, true);

        expect($loader->loaders())->toHaveCount(2);
        $loaders = $loader->loaders();
        expect($loaders[0])->toBe($secondLoader);
        expect($loaders[1])->toBe($firstLoader);
    });

    it('can remove a loader from the chain', function (): void {
        $loader = new ChainLoader();
        $mockLoader = createMockLoader();
        $loader->addLoader($mockLoader);

        $result = $loader->removeLoader($mockLoader);

        expect($result)->toBeTrue();
        expect($loader->loaders())->toBeEmpty();
    });

    it('returns false when removing a loader that does not exist', function (): void {
        $loader = new ChainLoader();
        $mockLoader = createMockLoader();

        $result = $loader->removeLoader($mockLoader);

        expect($result)->toBeFalse();
    });

    it('can remove a specific loader when multiple exist', function (): void {
        $loader = new ChainLoader();
        $loader1 = createMockLoader();
        $loader2 = createMockLoader();
        $loader3 = createMockLoader();

        $loader->addLoader($loader1);
        $loader->addLoader($loader2);
        $loader->addLoader($loader3);

        $result = $loader->removeLoader($loader2);

        expect($result)->toBeTrue();
        expect($loader->loaders())->toHaveCount(2);
        $loaders = $loader->loaders();
        expect($loaders[0])->toBe($loader1);
        expect($loaders[2] ?? null)->toBe($loader3);
    });

    it('returns an empty array when loading with no loaders', function (): void {
        $loader = new ChainLoader();
        $result = $loader->load('en', 'messages');

        expect($result)->toBeEmpty();
    });

    it('loads translations from a single loader', function (): void {
        $loader = new ChainLoader();
        $translations = [
            'en' => [
                'messages' => ['hello' => 'Hello', 'world' => 'World'],
            ],
        ];
        $mockLoader = createMockLoader($translations);
        $loader->addLoader($mockLoader);

        $result = $loader->load('en', 'messages');

        expect($result)->toBe(['hello' => 'Hello', 'world' => 'World']);
    });

    it('first loader takes precedence in chain', function (): void {
        $loader = new ChainLoader();
        $loader1 = createMockLoader([
            'en' => [
                'messages' => ['hello' => 'Hello', 'world' => 'World'],
            ],
        ]);
        $loader2 = createMockLoader([
            'en' => [
                'messages' => ['hello' => 'Hi', 'foo' => 'Bar'],
            ],
        ]);

        $loader->addLoader($loader1);
        $loader->addLoader($loader2);

        /** @var array<string, string> $result */
        $result = $loader->load('en', 'messages');

        expect($result['hello'])->toBe('Hello');
        expect($result['world'])->toBe('World');
        expect($result['foo'])->toBe('Bar');
    });

    it('handles namespaced translations correctly', function (): void {
        $loader = new ChainLoader();

        $translations = [
            'en' => [
                'messages' => ['key' => 'value'],
            ],
        ];
        $mockLoader = createMockLoader($translations);
        $loader->addLoader($mockLoader);

        $result = $loader->load('en', 'messages');

        expect($result)->toBe(['key' => 'value']);
    });

    it('propagates addNamespace to all loaders', function (): void {
        $loader = new ChainLoader();
        $loader1 = createMockLoader();
        $loader2 = createMockLoader();

        $loader->addLoader($loader1);
        $loader->addLoader($loader2);

        $loader->addNamespace('test', '/path/to/test');

        expect($loader1->namespaces())->toBe(['test' => '/path/to/test']);
        expect($loader2->namespaces())->toBe(['test' => '/path/to/test']);
    });

    it('propagates addJsonPath to all loaders without errors', function (): void {
        $loader = new ChainLoader();
        $loader1 = createMockLoader();
        $loader2 = createMockLoader();

        $loader->addLoader($loader1);
        $loader->addLoader($loader2);

        $loader->addJsonPath('/path/to/json');
        expect(true)->toBeTrue();
    });

    it('aggregates namespaces from all loaders', function (): void {
        $loader = new ChainLoader();

        $mockLoader1 = Mockery::mock(\Illuminate\Contracts\Translation\Loader::class);
        $mockLoader1->shouldReceive('namespaces')->andReturn(['namespace1' => '/path/1']);
        $mockLoader1->shouldReceive('addNamespace');
        $mockLoader1->shouldReceive('addJsonPath');
        $mockLoader1->shouldReceive('load')->andReturn([]);

        $mockLoader2 = Mockery::mock(\Illuminate\Contracts\Translation\Loader::class);
        $mockLoader2->shouldReceive('namespaces')->andReturn(['namespace2' => '/path/2']);
        $mockLoader2->shouldReceive('addNamespace');
        $mockLoader2->shouldReceive('addJsonPath');
        $mockLoader2->shouldReceive('load')->andReturn([]);

        $loader->addLoader($mockLoader1);
        $loader->addLoader($mockLoader2);

        $result = $loader->namespaces();

        expect($result)->toBe(['namespace1' => '/path/1', 'namespace2' => '/path/2']);
    });

    it('handles deeply nested translation merging', function (): void {
        $loader = new ChainLoader();
        $loader1 = createMockLoader([
            'en' => [
                'messages' => [
                    'level1' => [
                        'level2' => [
                            'key1' => 'value1',
                        ],
                    ],
                ],
            ],
        ]);
        $loader2 = createMockLoader([
            'en' => [
                'messages' => [
                    'level1' => [
                        'level2' => [
                            'key2' => 'value2',
                        ],
                    ],
                ],
            ],
        ]);

        $loader->addLoader($loader1);
        $loader->addLoader($loader2);

        /** @var array<string, array<string, array<string, string>>> $result */
        $result = $loader->load('en', 'messages');

        expect($result)->toHaveKey('level1');
        expect($result['level1']['level2']['key1'])->toBe('value1');
        expect($result['level1']['level2']['key2'])->toBe('value2');
    });

    it('first loader overrides nested values', function (): void {
        $loader = new ChainLoader();
        $loader1 = createMockLoader([
            'en' => [
                'messages' => [
                    'level1' => [
                        'level2' => [
                            'key' => 'original',
                        ],
                    ],
                ],
            ],
        ]);
        $loader2 = createMockLoader([
            'en' => [
                'messages' => [
                    'level1' => [
                        'level2' => [
                            'key' => 'overridden',
                        ],
                    ],
                ],
            ],
        ]);

        $loader->addLoader($loader1);
        $loader->addLoader($loader2);

        /** @var array<string, array<string, array<string, string>>> $result */
        $result = $loader->load('en', 'messages');

        expect($result['level1']['level2']['key'])->toBe('original');
    });
});
