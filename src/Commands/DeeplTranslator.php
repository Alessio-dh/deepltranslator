<?php

namespace Alessiodh\Deepltranslator\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeeplTranslator extends Command
{
    private $possibleTranslationLanguages = [
        "BG",
        "CS",
        "DA",
        "DE",
        "EL",
        "EN-GB",
        "EN-US",
        "EN",
        "ES",
        "ET",
        "FI",
        "FR",
        "HU",
        "IT",
        "JA",
        "LT",
        "LV",
        "NL",
        "PL",
        "PT-PT",
        "PT-BR",
        "PT",
        "RO",
        "RU",
        "SK",
        "SL",
        "SV",
        "ZH",
    ];

    protected $signature = 'deepl:translate {from} {to} {--filename=}';

    protected $description = 'Will translate all language files from a language to other language of choice using deepl API';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $baseUrl = 'https://api-free.deepl.com/v2/translate';

        if(!config('deepltranslator.deepl_api_key')){
            $this->error('API key is not set');
            return true;
        }

        $fromPath = resource_path('lang/' . $this->argument('from'));
        $toPath = resource_path('lang/' . $this->argument('to'));

        // From language path does not exist
        if (!file_exists($fromPath)) {
            $this->error('The from language is not found in your resource folder');
            return true;
        }

        //Not the same language
        if($this->argument('from') == $this->argument('to')){
            $this->error('You can not enter the from and to languages as the same language');
            return true;
        }

        // Deepl api cant handle this language
        if(!in_array(strtoupper($this->argument('to')),$this->possibleTranslationLanguages)){
            $this->error('Language is not allowed');
            return true;
        }


        // Already translated some files , overwrite question
        if (file_exists($toPath)) {
            $confirmed = $this->confirm('We see that the language you are wanting to translate already exists, do you wish to translate the remaining untranslated strings? [y/N]');

            if(!$confirmed){
                $this->line('Ok, we stopped the command');
                return true;
            }
        }

        // Specific file requested to translate
        if($this->option('filename')){
            if(!file_exists($fromPath.'/'.$this->option('filename'))){
                $this->error('File does not exist');
                return;
            }else{
                $filesInDirectory = [$this->option('filename')];
            }
        }else{
            $filesInDirectory = array_diff(scandir($fromPath), array('..', '.'));
        }

        $translations = [];
        $this->currentlyTranslated = [];

        foreach ($filesInDirectory as $translationFile) {
            if (file_exists($toPath.'/'.$translationFile)) {
                $this->currentlyTranslated[$translationFile] = include $toPath.'/'.$translationFile;
            }
            $translations[$translationFile] = include $fromPath.'/'.$translationFile;
        }

        $allFiles = [];
        foreach ($translations as $filename => $translation){
            $transformed = $this->transformTranslation($translation);
            $notTranslatedAllready = [];

            foreach($transformed as $key => $string) {
                if ($this->currentlyTranslated[$filename][$key] ?? null) continue;
                $notTranslatedAllready[$key] = $string;
            }

            if (count($notTranslatedAllready) === 0) continue;

            $allFiles[] = [
                'translations' => $notTranslatedAllready,
                'filename' => $filename
            ];
        }

        $allFiles = collect($allFiles);
        $client = new Client();

        if ($allFiles->count() === 0) {
            $this->error('Nothing to translate');
            return;
        }

        $requests = function() use ($allFiles,$client,$baseUrl){
            foreach ($allFiles as $key => $file) {
                $allTranslations = collect($file['translations']);
                $allTranslations = $allTranslations->chunk(30);
                foreach ($allTranslations as $chunkedTranslations) {
                    $params = [
                        'tag_handling' => 'xml',
                        'ignore_tags' => 'ignore,ignore-filename,ignore-index',
                        'source_lang' => strtoupper($this->argument('from')),
                        'target_lang' => strtoupper($this->argument('to')),
                        'auth_key' => config('deepltranslator.deepl_api_key')
                    ];

                    $texts = $this->addIgnoreToTextForDeepL($chunkedTranslations,$file['filename']);

                    $text = '';
                    foreach ($texts as $item) {
                        $text .= $item;
                    }

                    $body = http_build_query($params).'&'.substr_replace($text ,"", -1);
                    yield function() use ($client,$body,$baseUrl) {
                        return $client->postAsync($baseUrl,[
                            'body' => $body,
                            'headers' => [
                                'Content-Type' => 'application/x-www-form-urlencoded',
                            ]
                        ]);
                    };
                }
            }
        };

        $pool = new Pool($client,$requests(),[
            'concurrency' => 5,
            'fulfilled' => function (Response $response, $index) {
                $test = $response->getBody()->getContents();
                $this->handleResponse($test);
            },
            'rejected' => function (RequestException $reason, $index) {
                Log::debug($reason);
                $this->error('Something went wrong when receiving answer from DeepL API');
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();
    }

    private function addIgnoreToTextForDeepL($translations,$filename ,$texts = [], $indexes = []){
        foreach ($translations as $index => $chunkedTranslation) {
            if(is_array($chunkedTranslation)){
                $indexes[] = $index;
                $result = collect($this->addIgnoreToTextForDeepL($chunkedTranslation,$filename,[], $indexes))->flatten()->toArray();
                foreach ($result as $item) {
                    $texts[] = $item;
                }
            }else{
                if(count($indexes) > 0){
                    $t = 'text=<ignore-filename>'.$filename.'</ignore-filename>';
                    foreach ($indexes as $indexesToAdd) {
                        $t .= '<ignore-index>'.$indexesToAdd.'</ignore-index>';
                    }
                    $t .= $chunkedTranslation.'&';
                    $texts[] = $t;
                }else{
                    $texts[] = 'text=<ignore-filename>'.$filename.'</ignore-filename><ignore-index>'.$index.'</ignore-index>'. $chunkedTranslation.'&';
                }
            }
        }

        return $texts;
    }

    private function transformTranslation($translations,$transformed = []){
        foreach ($translations as $index => $toTranslate){
            if(is_array($toTranslate)){
                $transformed[$index] = $this->transformTranslation($toTranslate,[]);
            }else{
                $transformed[$index] = $this->addIgnoreTagToVariables($toTranslate);
            }
        }
        return $transformed;
    }

    private function addIgnoreTagToVariables($text){
        $newtext = $text;
        $regex = '~(:\w+)~';
        if (preg_match_all($regex, $text, $matches, PREG_PATTERN_ORDER)) {
            foreach ($matches[1] as $word) {
                $newtext = str_replace($word,'<ignore>'. $word .'</ignore>',$newtext);
            }
            return $newtext;
        }else{
            return $text;
        }
    }

    private function handleResponse($result){
        try{
            $decoded = json_decode($result);
        }catch (\Exception $ex){
            Log::error('Something went wrong decoding the response from DeepL');
        }

        if(!isset($decoded->translations)){
            $this->error('No translations have been returned by DeepL');
            return;
        }

        $resultArray = $this->handleTranslatedTexts($decoded->translations);

        if (!is_dir(resource_path('lang/'.$this->argument('to')))) {
            if (!mkdir($concurrentDirectory = resource_path('lang/' . $this->argument('to'))) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        foreach ($resultArray as $filename => $newTranslations) {
            $newTranslations = array_merge($newTranslations, $this->currentlyTranslated[$filename] ?? []);

            file_put_contents(resource_path('lang/'.$this->argument('to').'/'.$filename),'<?php return '. var_export($newTranslations,true).';');
        }
    }

    private function handleTranslatedTexts($translations){
        $resultArray = [];
        foreach ($translations as $translation) {
            $filename = $this->everything_in_tags($translation->text,'ignore-filename')[0];
            $index2 = $this->everything_in_tags($translation->text,'ignore-index');
            $index = $this->everything_in_tags($translation->text,'ignore-index')[0];

            $toReplace = [
                $filename,
                $index,
                '<ignore-index>',
                '</ignore-index>',
                '<ignore-filename>',
                '<ignore-dex>',
                '<ignore-name>',
                '</ignore-name>',
                '</ignore-filename>',
                '<\\/ignore>',
                '</ignore-dex>',
                '<ignore>',
                '</ignore>'
            ];
            $newText = str_replace($toReplace,'',$translation->text);

            $a = $newText;
            if(count($index2) > 1){
                foreach (array_reverse($index2) as $valueAsKey) $a = [$valueAsKey => $a];
                $resultArray = array_merge($resultArray,$a);
            }else{
                $resultArray[$index] = trim($newText);
            }
        }

        return [$filename => $resultArray];
    }

    private function everything_in_tags($string, $tagname)
    {
        $pattern = "#<\s*?$tagname\b[^>]*>(.*?)</$tagname\b[^>]*>#s";
        preg_match_all($pattern, $string, $matches);
        return $matches[1];
    }
}
