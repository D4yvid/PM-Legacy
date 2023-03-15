if test -f ./bin/php7/bin/php
then
	export PHPRC=""
	export PHP_BINARY=./bin/php7/bin/php
else
	export PHP_BINARY=php
fi

${PHP_BINARY} -c bin/php7 src/pocketmine/PocketMine.php  --data=Server --plugins=Server/plugins $@
