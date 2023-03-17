<?php

namespace pocketmine\event;

use Attribute;

#[Attribute]
class EventHandler
{

	public function __construct(
		public EventPriority $priority = EventPriority::NORMAL,
		public bool $ignoreCancelled = false
	)
	{}

}