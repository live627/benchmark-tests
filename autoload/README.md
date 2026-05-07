First, generate the autoloader

```
composer dump-autoload
```

Then run `bench.php`

```
custom_require (sequential)                        120.87 ms
custom_require (random)                            122.78 ms
custom_once (sequential)                           141.79 ms
custom_once (random)                               142.19 ms
classlist (sequential)                             97.98 ms
classlist (random)                                 98.78 ms
classlist-once (sequential)                        117.30 ms
classlist-once (random)                            118.87 ms
classlist-authoritative (sequential)               73.19 ms
classlist-authoritative (random)                   74.91 ms
classlist-authoritative-once (sequential)          93.50 ms
classlist-authoritative-once (random)              94.51 ms
composer (sequential)                              121.81 ms
composer (random)                                  124.55 ms
composer-optimized (sequential)                    102.12 ms
composer-optimized (random)                        101.77 ms
composer-authoritative (sequential)                73.72 ms
composer-authoritative (random)                    73.53 ms
```
