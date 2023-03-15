set -eux

if test -f ./bin/php7/bin/php
then
	export PHPRC=""
	export PHP_BINARY=./bin/php7/bin/php
else
	export PHP_BINARY=php
fi

export PMMP=src/pocketmine/PocketMine.php

if test -f PocketMine-MP.phar  
then
	export PMMP=PocketMine-MP.phar
fi


${PHP_BINARY} -c bin/php7 ${PMMP} --data=Server --plugins=Server/plugins $@
