<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
use Statikbe\LaravelChainedTranslator\Console\Commands\MergeTranslationsCommand;
use Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException;

describe('MergeTranslationsCommand', function () {
    it('has the correct signature', function () {
        $command = new MergeTranslationsCommand();

        expect($command->getName())->toBe('chainedtranslator:merge');
    });

    it('has a description', function () {
        $command = new MergeTranslationsCommand();

        expect($command->getDescription())->not->toBeEmpty();
        expect($command->getDescription())->toContain('custom');
        expect($command->getDescription())->toContain('default');
    });

    it('requires a locale argument', function () {
        $command = new MergeTranslationsCommand();
        $definition = $command->getDefinition();

        expect($definition->hasArgument('locale'))->toBeTrue();
        expect($definition->getArgument('locale')->isRequired())->toBeTrue();
    });

    it('validates locale is a string', function () {
        $manager = Mockery::mock(ChainedTranslationManager::class);
        $command = Mockery::mock(MergeTranslationsCommand::class)->makePartial();
        $command->shouldAllowMockingProtectedMethods();

        $command->shouldReceive('argument')->with('locale')->andReturn(['not', 'a', 'string']);
        $command->shouldReceive('error')->with('The locale argument must be a string.');

        $result = $command->handle($manager);

        expect($result)->toBe(Command::FAILURE);
    });

    it('successfully merges translations for valid locale', function () {
        $manager = Mockery::mock(ChainedTranslationManager::class);
        $manager->shouldReceive('mergeChainedTranslationsIntoDefaultTranslations')->with('en')->once();

        $command = Mockery::mock(MergeTranslationsCommand::class)->makePartial();
        $command->shouldAllowMockingProtectedMethods();
        $command->shouldReceive('argument')->with('locale')->andReturn('en');

        $result = $command->handle($manager);

        expect($result)->toBe(Command::SUCCESS);
    });

    it('handles save exception gracefully', function () {
        $manager = Mockery::mock(ChainedTranslationManager::class);
        $manager
            ->shouldReceive('mergeChainedTranslationsIntoDefaultTranslations')
            ->with('en')
            ->andThrow(new SaveTranslationFileException('Save failed'));

        $command = Mockery::mock(MergeTranslationsCommand::class)->makePartial();
        $command->shouldAllowMockingProtectedMethods();
        $command->shouldReceive('argument')->with('locale')->andReturn('en');
        $command->shouldReceive('error')->with('Save failed');

        $result = $command->handle($manager);

        expect($result)->toBe(Command::FAILURE);
    });

    it('exits with 0 when the merge succeeds', function () {
        $manager = Mockery::mock(ChainedTranslationManager::class);
        $manager->shouldReceive('mergeChainedTranslationsIntoDefaultTranslations')->with('en')->once();
        $this->app->instance(ChainedTranslationManager::class, $manager);

        $this->artisan('chainedtranslator:merge', ['locale' => 'en'])->assertExitCode(0);
    });

    it('exits with 1 and shows the error when the merge fails', function () {
        $manager = Mockery::mock(ChainedTranslationManager::class);
        $manager
            ->shouldReceive('mergeChainedTranslationsIntoDefaultTranslations')
            ->andThrow(new SaveTranslationFileException('Save failed'));
        $this->app->instance(ChainedTranslationManager::class, $manager);

        $this
            ->artisan('chainedtranslator:merge', ['locale' => 'en'])
            ->expectsOutputToContain('Save failed')
            ->assertExitCode(1);
    });

    it('exits with 1 and shows the error for an invalid locale', function () {
        $this
            ->artisan('chainedtranslator:merge', ['locale' => '../config'])
            ->expectsOutputToContain('The locale "../config" is not a valid locale.')
            ->assertExitCode(1);
    });
});
