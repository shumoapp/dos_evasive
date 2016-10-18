# dos_evasive

#### Overview
A simple script that provides DOS protection similar to mod_evasive. Unlike mod_evasive, hits from the same ip are not distributed between child processes, hence more accurate control is available. The script will protect only php scripts though, but normally that is where a server is more vulnerable to DOS attacks. 

It will block an ip if more than X hits are sent whithin Y time from that ip address. While the ip is blocked 503 error message will be shown(so search engines will know it's a temporary error, should they be blocked for some reason).  It stores the data in Memcache(d)/APC, but can be easily modified to use other cache. 

#### Quick Start
To use the script, simply include dos_evasive.php in your scripts and uncomment and modify the examples at the top of the script. You can also use the php directive auto_prepend_file to automatically execute the script before any other php script.

Here is description of the parameters: 
* mPageHits - How many hits during the last mTimeout seconds should trigger an ip block.
* mTimeout - How many seconds back in time should the script count the hits.
* mBlockingTime - For how long the ip should be blocked once ip block is triggered. Any hit from the same ip that is done before the mBlockingTime expires will reset the timer. 
* mUriExclusion - Regular expression to exclude certain URIs from monitoring - normally APIs or any other frequently accessed URIs by the same ip. It mathes that against REQUEST_URI environment variable. 

#### Unit tests
To run the unit tests, simply run these commands in the project root:
```bash
/usr/bin/php composer.phar update
/usr/bin/php vendor/bin/phpunit tests
```
For details on how to download composer, visit [https://getcomposer.org/download/](https://getcomposer.org/download/).

## License

The code and the documentation are released under the [MIT License](LICENSE).
