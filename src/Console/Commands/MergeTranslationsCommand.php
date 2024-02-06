<?php

    namespace Statikbe\LaravelChainedTranslator\Console\Commands;

    use Illuminate\Console\Command;
    use Illuminate\Support\Facades\Log;
    use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;
    use Statikbe\LaravelChainedTranslator\Exceptions\SaveTranslationFileException;

    class MergeTranslationsCommand extends Command
    {
        /**
         * The name and signature of the console command
         */
        protected $signature = 'chainedtranslator:merge {locale : The locale in 2 char ISO format}';

        /**
         * The console command description.
         *
         * @var string
         */
        protected $description = 'Merges the custom translations back into the translation files of the default Laravel lang directory.';

        /**
         * Create a new command instance.
         *
         * @return void
         */
        public function __construct()
        {
            parent::__construct();
        }

        /**
         * Execute the console command.
         *
         * @param ChainedTranslationManager $chainedTranslationManager
         * @return mixed
         */
        public function handle(ChainedTranslationManager $chainedTranslationManager)
        {
            $locale = $this->argument('locale');

            try {
                $chainedTranslationManager->mergeChainedTranslationsIntoDefaultTranslations($locale);
            }
            catch(SaveTranslationFileException $ex){
                Log::error($ex);
                $this->error($ex->getMessage());
                return false;
            }

            return true;
        }
    }
