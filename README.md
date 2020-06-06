# Magento 2 Site based Cachewarmer / Link checker / Siege Tester

Magento 2 cache warmer / link checker / siege tester.

This is lightweight cache warmer, link checker or siege tester. With option for verbose console and / or logging to file. Scans store URL, products, categories and CMS pages. Configuration for User agent.

![phpcs](https://github.com/DominicWatts/CacheWarmer/workflows/phpcs/badge.svg)

![PHPCompatibility](https://github.com/DominicWatts/CacheWarmer/workflows/PHPCompatibility/badge.svg)

![PHPStan](https://github.com/DominicWatts/CacheWarmer/workflows/PHPStan/badge.svg)

# Install instructions

`composer require dominicwatts/cachewarmer`

`php bin/magento setup:upgrade`

`php bin/magento setup:di:compile`

# Usage instructions

    xigen:cachewarmer:runner [-s|--store STORE] [--] <warm> [<log>]

Run on default store ID 1

    xigen:cachewarmer:runner warm

Run on default store ID 1 with verbose output `[-v|--verbose VERBOSE]`

    xigen:cachewarmer:runner warm -v

Run on default store ID 1 logging to `cachewarmer.log`

    xigen:cachewarmer:runner warm log

Run on store ID 3 logging to `cachewarmer.log`

    xigen:cachewarmer:runner warm log -s 3