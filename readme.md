# DeepL Translator for laravel

Will translate all files inside a laravel project using the DeepL API and exports it to the requested language

## Installation

Installation can be done through composer

``
composer require alessiodh/deepltranslator
``

Publishing the config file

``
php artisan vendor:publish --provider="Alessiodh\Deepltranslator\DeeplTranslatorServiceProvider" 
``

## Usage

Command:

``
php artisan deepl:translate {from} {to} {--filename}
``

| Option   | Description  |  Required  |
|---|---|---|
| from | The from language where the translations will be taken from  |  Yes  |
| to  |  The language you want to translate to |  Yes  |
| filename  | In case you want to translate a single file this option can be set  |  No  |

The from language files will be retrieved inside `/lang/{from}/`

## Examples

### Translating all files

```php artisan deepl:translate en nl```

This command will translate all php files inside the ``/lang/en`` directory. If the map `nl` is not existing, it will create it and put all translations according to the files retrieved from the `from` language.

### Single file

``php artisan deepl:translate en nl --filename=auth.php``

This will do exactly the same as translating all files but instead will only take 1 file inconsideration.


## Upcoming changes
Currently not all options that Laravel supports are supported inside this package. Following options will be added soon:

- Pluralization inside translation file
- Numeric if statements inside the translation
- Database translations
- JSON files as translation files instead of PHP
