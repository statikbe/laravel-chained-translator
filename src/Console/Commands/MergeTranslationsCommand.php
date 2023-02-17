<?php

    namespace Statikbe\LaravelChainedTranslator\Console\Commands;

    use Illuminate\Console\Command;
    use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;

    class MergeTranslationsCommand extends Command
    {
        /**
         * The name and signature of the console command.
         *
         * @var string
         */
        protected $signature = 'chainedtranslator:merge {locale : The locale in 2 char ISO format}';

        /**
         * The console command description.
         *
         * @var string
         */
        protected $description = 'Merges the custom translations back into the translation files of the default Laravel lang directory.';

        /**
         * Execute the console command.
         *
         * @param ChainedTranslationManager $chainedTranslationManager
         * @return mixed
         */
        public function handle(ChainedTranslationManager $chainedTranslationManager): mixed
        {
            $locale = $this->argument('locale');
            $chainedTranslationManager->mergeChainedTranslationsIntoDefaultTranslations($locale);
            return true;
        }
    }
