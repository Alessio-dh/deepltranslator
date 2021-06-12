<?php

namespace Alessiodh\Deepltranslator\Traits;

use GuzzleHttp\Client;

trait DeepltranslatorTrait {
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

    function translateString($text, $from, $to){
        if(!$this->checkSettings($from, $to)){
            return false;
        }

        if(!is_array($to)){
            $to = [$to];
        }

        $baseUrl = config('deepltranslator.deepl_url');
        $translated = [];

        foreach ($to as $toLang) {
            $params = [
                'tag_handling' => 'xml',
                'ignore_tags' => 'ignore,ignore-filename,ignore-index',
                'source_lang' => strtoupper($from),
                'target_lang' => strtoupper($toLang),
                'auth_key' => config('deepltranslator.deepl_api_key'),
                'formality' => config('deepltranslator.formality'),
                'preserve_formatting' => config('deepltranslator.preserve_formatting'),
            ];
            $body = http_build_query($params).'&text='.$text;

            $client = new Client();
            $response = $client->post($baseUrl, [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
            ]);

            $result = json_decode($response->getBody()->getContents());
            if(isset($result->translations)){
                if(isset($result->translations[0])){
                    $translated[$toLang] = $result->translations[0]->text;
                }
            }
        }

        return $translated;
    }

    private function checkSettings($from, $to){
        if(!config('deepltranslator.deepl_url')){
            return false;
        }

        if(!config('deepltranslator.deepl_api_key')){
            return false;
        }

        if(!is_array($to)){
            $to = [$to];
        }

        if(!in_array(strtoupper($from),$this->possibleTranslationLanguages)){
            return false;
        }

        if(count($to) !== count(array_unique($to))){
            return false;
        }

        foreach ($to as $toLang) {
            if($from == $toLang){
                return false;
            }

            if(!in_array(strtoupper($toLang),$this->possibleTranslationLanguages)){
                return false;
            }
        }

        return true;
    }
}
