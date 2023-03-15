<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/ 
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_| 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http:// www.pocketmine.net/
 * 
 *
 */

namespace {
	function safe_var_dump()
	{
		static $cnt = 0;
		foreach (func_get_args() as $var) {
			switch (true) {
				case is_array($var):
					echo str_repeat("  ", $cnt) . "array(" . count($var) . ") {" . PHP_EOL;
					foreach ($var as $key => $value) {
						echo str_repeat("  ", $cnt + 1) . "[" . (is_integer($key) ? $key : '"' . $key . '"') . "]=>" . PHP_EOL;
						++$cnt;
						safe_var_dump($value);
						--$cnt;
					}
					echo str_repeat("  ", $cnt) . "}" . PHP_EOL;
					break;
				case is_int($var):
					echo str_repeat("  ", $cnt) . "int(" . $var . ")" . PHP_EOL;
					break;
				case is_float($var):
					echo str_repeat("  ", $cnt) . "float(" . $var . ")" . PHP_EOL;
					break;
				case is_bool($var):
					echo str_repeat("  ", $cnt) . "bool(" . ($var === true ? "true" : "false") . ")" . PHP_EOL;
					break;
				case is_string($var):
					echo str_repeat("  ", $cnt) . "string(" . strlen($var) . ") \"$var\"" . PHP_EOL;
					break;
				case is_resource($var):
					echo str_repeat("  ", $cnt) . "resource() of type (" . get_resource_type($var) . ")" . PHP_EOL;
					break;
				case is_object($var):
					echo str_repeat("  ", $cnt) . "object(" . get_class($var) . ")" . PHP_EOL;
					break;
				case is_null($var):
					echo str_repeat("  ", $cnt) . "NULL" . PHP_EOL;
					break;
			}
		}
	}

	define("DIR_SEP", DIRECTORY_SEPARATOR);
}

namespace pocketmine {

	use Exception;
	use Phar;
	use pocketmine\utils\Binary;
	use pocketmine\utils\MainLogger;
	use pocketmine\utils\Terminal;
	use pocketmine\utils\Utils;
	use pocketmine\wizard\Installer;

	const VERSION = "1.6dev";
	const API_VERSION = "2.0.0";
	const CODENAME = "Unleashed";
	const MINECRAFT_VERSION = "v0.15.10.0 alpha";
	const MINECRAFT_VERSION_NETWORK = "0.15.10.0";

	function help()
	{
		echo "PocketMine-MP v0.0.1-dev (API 2.0.0)" . PHP_EOL;
		echo "" . PHP_EOL;
		echo "Available arguments:" . PHP_EOL;
		echo "" . PHP_EOL;
		echo "\t--help, --?           \t\t Show this help" . PHP_EOL;
		echo "" . PHP_EOL;
		echo "\t--memory-limit=<limit>\t\t Set the max memory limit used by the server in MiB." . PHP_EOL;
		echo "" . PHP_EOL;
		echo "\t--data=<path>         \t\t Set the server data path to the specified directory." . PHP_EOL;
		echo "" . PHP_EOL;
		echo "\t--plugins=<path>      \t\t Set the plugin path, where the server will lookup" . PHP_EOL;
		echo "\t                      \t\t for plugins to the server." . PHP_EOL;
		echo "" . PHP_EOL;
		echo "\t--no-wizard           \t\t Skip the server wizard." . PHP_EOL;
		echo "" . PHP_EOL;
		echo "\t--disable-ansi        \t\t Disable all terminal colors." . PHP_EOL;
		echo "" . PHP_EOL;
	}

	function checkPHPBinary()
	{
		$errors = 0;
		$missingExtensions = [];

		if (php_sapi_name() !== "cli") {
			echo "You must run PocketMine-MP using the CLI (Terminal) enviroment!.";

			exit(1);
		}

		if (!extension_loaded("sockets")) {
			$missingExtensions[] = "sockets";
			++$errors;
		}

		$pthreads_version = phpversion("pthreads");

		if (substr_count($pthreads_version, ".") < 2) {
			$pthreads_version = "0.$pthreads_version";
		}

		if (version_compare($pthreads_version, "3.1.5") < 0) {
			$missingExtensions[] = "pthreads >= 3.1.5 is required, while you have $pthreads_version.";
			++$errors;
		}

		if (extension_loaded("pocketmine") && (version_compare(phpversion("pocketmine"), "0.0.1") < 0) || version_compare(phpversion("pocketmine"), "0.0.4") > 0) {
			$missingExtensions[] = "pocketmine >= 0.0.1 && pocketine <= 0.0.4";

			++$errors;
		}

		if (!extension_loaded("curl")) {
			$missingExtensions[] = "curl";

			++$errors;
		}

		if (!extension_loaded("yaml")) {
			$missingExtensions[] = "yaml";

			++$errors;
		}

		if (!extension_loaded("sqlite3")) {
			$missingExtensions[] = "sqlite3";

			++$errors;
		}

		if (!extension_loaded("zlib")) {
			$missingExtensions[] = "zlib";

			++$errors;
		}

		if ($errors > 0) {
			echo "Your PHP binary does not meet the requirements to run PocketMine-MP!" . PHP_EOL;
			echo "Your PHP binary need these extensions: " . PHP_EOL;

			foreach ($missingExtensions as $extension) {
				echo " - $extension" . PHP_EOL;
			}

			echo "Please recompile your PHP binary with these extensions!" . PHP_EOL;
			exit(1);
		}
	}

	/*
	 * Startup code. Do not look at it, it may harm you.
	 * Most of them are hacks to fix date-related bugs, or basic functions used after this
	 * This is the only non-class based file on this project.
	 * Enjoy it as much as I did writing it. I don't want to do it again.
	 */


	checkPHPBinary();

	if (Phar::running(true) !== "") {
		@define('pocketmine\PATH', Phar::running(true) . "/");
	} else {
		@define('pocketmine\PATH', getcwd() . DIR_SEP);
	}

	if (!class_exists("ClassLoader", false)) {
		require_once(PATH . "src/spl/ClassLoader.php");
		require_once(PATH . "src/spl/BaseClassLoader.php");
		require_once(PATH . "src/pocketmine/CompatibleClassLoader.php");
	}

	$autoloader = new CompatibleClassLoader();
	$autoloader->addPath(PATH . "src");
	$autoloader->addPath(PATH . "src" . DIR_SEP . "spl");
	$autoloader->register(true);

	Terminal::init();

	$opts = getopt("", ["help", "?", "memory-limit:", "data:", "plugins:", "no-wizard", "disable-ansi"]);

	if (isset($opts["help"]) || isset($opts["?"])) {
		help();
		exit(0);
	}

	define('pocketmine\ANSI', Terminal::hasFormattingCodes() && !isset($opts["disable-ansi"]));
	define('pocketmine\PLUGIN_PATH', isset($opts["plugins"]) ? $opts["plugins"] . DIR_SEP : getcwd() . DIR_SEP . "plugins" . DIR_SEP);
	define('pocketmine\DATA', isset($opts["data"]) ? $opts["data"] . DIR_SEP : getcwd() . DIR_SEP);
	define('pocketmine\START_TIME', microtime(true));

	$logger = new MainLogger(DATA . "server.log", ANSI);

	if (version_compare("7.0", PHP_VERSION) > 0) {
		$logger->critical("You must use PHP >= 7.0");
		$logger->critical("Please use the installer provided on the homepage.");
		exit(1);
	}

	if (!extension_loaded("pthreads")) {
		$logger->critical("Unable to find the pthreads extension in the PHP.");
		$logger->critical("Please use the installer provided on the homepage.");
		exit(1);
	}

	$memLimit = isset($opts["memory_limit"]) ? $opts["memory_limit"] : -1;

	set_time_limit(0);

	gc_enable();
	error_reporting(-1);
	ini_set("allow_url_fopen", 1);
	ini_set("display_errors", 1);
	ini_set("display_startup_errors", 1);
	ini_set("default_charset", "utf-8");
	ini_set("memory_limit", $memLimit);

	if ($memLimit == -1) {
		$logger->warning("The server will be run with all memory available! Please specify a memory limit to disable this warning.");
	}

	if (!file_exists(DATA)) {
		mkdir(DATA, 0777, true);
	}

	date_default_timezone_set("UTC");

	$timezone = "UTC";

	if (!ini_get("date.timezone")) {
		if (($timezone = detect_system_timezone()) and date_default_timezone_set($timezone)) {
			ini_set("date.timezone", $timezone);
		} else {
			// If system timezone detection fails or timezone is an invalid value.
			if (
				$response = Utils::getURL("http://ip-api.com/json")
				and $ip_geolocation_data = json_decode($response, true)
				and $ip_geolocation_data['status'] !== 'fail'
				and date_default_timezone_set($ip_geolocation_data['timezone'])
			) {
				$timezone = $ip_geolocation_data['timezone'];
				ini_set("date.timezone", $timezone);
			} else {
				$logger->warning("Timezone could not be automatically determined. An incorrect timezone will result in incorrect timestamps on console logs. It has been set to \"UTC\" by default. You can change it on the php.ini file.");

				ini_set("date.timezone", "UTC");
				date_default_timezone_set("UTC");
			}
		}
	} else {
		$timezone = ini_get("date.timezone");

		if (strpos($timezone, "/") === false) {
			$default_timezone = timezone_name_from_abbr($timezone);

			ini_set("date.timezone", $default_timezone);
			date_default_timezone_set($default_timezone);

			$timezone = $default_timezone;
		} else {
			date_default_timezone_set($timezone);
		}
	}

	$logger->info("Using timezone $timezone.");

	function detect_system_timezone()
	{
		switch (Utils::getOS()) {
			case 'win':
				$regex = '/(UTC)(\+*\-*\d*\d*\:*\d*\d*)/';

				/*
				 * wmic timezone get Caption
				 * Get the timezone offset
				 *
				 * Sample Output var_dump
				 * array(3) {
				 *	  [0] =>
				 *	  string(7) "Caption"
				 *	  [1] =>
				 *	  string(20) "(UTC+09:30) Adelaide"
				 *	  [2] =>
				 *	  string(0) ""
				 *	}
				 */
				exec("wmic timezone get Caption", $output);

				$string = trim(implode("\n", $output));

				// Detect the Time Zone string
				preg_match($regex, $string, $matches);

				if (!isset($matches[2])) {
					return false;
				}

				$offset = $matches[2];

				if ($offset == "") {
					return "UTC";
				}

				return parse_offset($offset);
			case 'linux':
				// Ubuntu / Debian.
				if (file_exists('/etc/timezone')) {
					$data = file_get_contents('/etc/timezone');
					if ($data) {
						return trim($data);
					}
				}

				// RHEL / CentOS
				if (file_exists('/etc/sysconfig/clock')) {
					$data = parse_ini_file('/etc/sysconfig/clock');
					if (!empty($data['ZONE'])) {
						return trim($data['ZONE']);
					}
				}

				// Portable method for incompatible linux distributions.

				$offset = trim(exec('date +%:z'));

				if ($offset == "+00:00") {
					return "UTC";
				}

				return parse_offset($offset);
			case 'mac':
				if (is_link('/etc/localtime')) {
					$filename = readlink('/etc/localtime');
					if (strpos($filename, '/usr/share/zoneinfo/') === 0) {
						$timezone = substr($filename, 20);
						return trim($timezone);
					}
				}

				return false;
			default:
				return false;
		}
	}

	/**
	 * @param string $offset In the format of +09:00, +02:00, -04:00 etc.
	 *
	 * @return string
	 */
	function parse_offset($offset)
	{
		// Make signed offsets unsigned for date_parse
		if (strpos($offset, '-') !== false) {
			$negative_offset = true;
			$offset = str_replace('-', '', $offset);
		} else {
			if (strpos($offset, '+') !== false) {
				$negative_offset = false;
				$offset = str_replace('+', '', $offset);
			} else {
				return false;
			}
		}

		$parsed = date_parse($offset);
		$offset = $parsed['hour'] * 3600 + $parsed['minute'] * 60 + $parsed['second'];

		// After date_parse is done, put the sign back
		if ($negative_offset == true) {
			$offset = -abs($offset);
		}

		// And then, look the offset up.
		// timezone_name_from_abbr is not used because it returns false on some(most) offsets because it's mapping function is weird.
		// That's been a bug in PHP since 2008!
		foreach (timezone_abbreviations_list() as $zones) {
			foreach ($zones as $timezone) {
				if ($timezone['offset'] == $offset) {
					return $timezone['timezone_id'];
				}
			}
		}

		return false;
	}

	function kill($pid)
	{
		switch (Utils::getOS()) {
			case "win":
				exec("taskkill.exe /F /PID " . ((int)$pid) . " > NUL");
				break;

			default:
				exec("kill -s KILL " . ((int)$pid) . " > /dev/null 2>&1");
		}
	}

	/**
	 * @param object $value
	 * @param bool $includeCurrent
	 *
	 * @return int
	 */
	function getReferenceCount($value, $includeCurrent = true)
	{
		ob_start();
		debug_zval_dump($value);
		$ret = explode("\n", ob_get_contents());
		ob_end_clean();

		if (count($ret) >= 1 and preg_match('/^.* refcount\\(([0-9]+)\\)\\{$/', trim($ret[0]), $m) > 0) {
			return ((int)$m[1]) - ($includeCurrent ? 3 : 4); //$value + zval call + extra call
		}
		return -1;
	}

	function getTrace($start = 1, $trace = null)
	{
		if ($trace === null) {
			$e = new Exception();
			$trace = $e->getTrace();
		}

		$messages = [];
		$j = 0;
		for ($i = (int)$start; isset($trace[$i]); ++$i, ++$j) {
			$params = "";
			if (isset($trace[$i]["args"]) or isset($trace[$i]["params"])) {
				if (isset($trace[$i]["args"])) {
					$args = $trace[$i]["args"];
				} else {
					$args = $trace[$i]["params"];
				}
				foreach ($args as $_ => $value) {
					$_;
					$params .= (is_object($value) ? get_class($value) . " " . (method_exists($value, "__toString") ? $value->__toString() : "object") : gettype($value) . " " . (is_array($value) ? "Array()" : Utils::printable(@strval($value)))) . ", ";
				}
			}
			$messages[] = "#$j " . (isset($trace[$i]["file"]) ? cleanPath($trace[$i]["file"]) : "") . "(" . (isset($trace[$i]["line"]) ? $trace[$i]["line"] : "") . "): " . (isset($trace[$i]["class"]) ? $trace[$i]["class"] . (($trace[$i]["type"] === "dynamic" or $trace[$i]["type"] === "->") ? "->" : "::") : "") . $trace[$i]["function"] . "(" . Utils::printable(substr($params, 0, -2)) . ")";
		}

		return $messages;
	}

	function cleanPath($path)
	{
		return rtrim(str_replace(["\\", ".php", "phar://", rtrim(str_replace(["\\", "phar://"], ["/", ""], PATH), "/"), rtrim(str_replace(["\\", "phar://"], ["/", ""], PLUGIN_PATH), "/")], ["/", "", "", "", ""], $path), "/");
	}

	if (file_exists(PATH . ".git/refs/heads/master")) { // Found Git information!
		define('pocketmine\GIT_COMMIT', strtolower(trim(file_get_contents(PATH . ".git/refs/heads/master"))));
	} else { // Unknown :(
		define('pocketmine\GIT_COMMIT', str_repeat("00", 20));
	}

	@define("ENDIANNESS", (pack("d", 1) === "\77\360\0\0\0\0\0\0" ? Binary::BIG_ENDIAN : Binary::LITTLE_ENDIAN));
	@define("INT32_MASK", is_int(0xffffffff) ? 0xffffffff : -1);
	@ini_set("opcache.mmap_base", bin2hex(random_bytes(8))); // Fix OPCache address errors

	if (!file_exists(DATA . "server.properties") and !isset($opts["no-wizard"])) {
		new Installer();
	}

	if (Phar::running(true) === "") {
		$logger->warning("Non-packaged PocketMine-MP installation detected, do not use on production.");
	}

	ThreadManager::init();
	$server = new Server($autoloader, $logger, PATH, DATA, PLUGIN_PATH);

	$logger->info("Stopping other threads");

	foreach (ThreadManager::getInstance()->getAll() as $id => $thread) {
		$logger->debug("Stopping " . $thread->getThreadName() . " thread");

		if ($thread->shutdown())
			$thread->quit();
	}

	$logger->shutdown();
	$logger->join();

	echo Terminal::$FORMAT_RESET . "Press enter to exit if it didn't exited.\n";

	fflush(STDIN);
	fflush(STDOUT);
	fflush(STDERR);

	exit(0);
}