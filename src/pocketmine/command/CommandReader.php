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
 * @link http://www.pocketmine.net/
 *
 *
 */

namespace pocketmine\command;

use pocketmine\Thread;
use Threaded;

class CommandReader extends Thread
{
	/** @var Threaded */
	protected $buffer;
	private $shutdown = false;

	public function __construct()
	{
		$this->buffer = new Threaded;
		$this->start();
	}

	public function shutdown()
	{
		$this->shutdown = true;
	}

	/**
	 * Reads a line from console, if available. Returns null if not available
	 *
	 * @return string|null
	 */
	public function getLine()
	{
		if ($this->buffer->count() !== 0) {
			return $this->buffer->shift();
		}

		return null;
	}

	public function run()
	{
		global $stdin;

		$stdin = fopen("php://stdin", "r");
		stream_set_blocking($stdin, 0);

		while (!$this->shutdown) {
			if (($line = $this->readLine()) !== "") {
				$this->buffer[] = preg_replace("#\\x1b\\x5b([^\\x1b]*\\x7e|[\\x40-\\x50])#", "", $line);
			}
		}
	}

	private function readLine()
	{
		global $stdin;

		if (!is_resource($stdin)) {
			return "";
		}

		return trim(fgets($stdin));
	}

	public function getThreadName()
	{
		return "Console";
	}
}