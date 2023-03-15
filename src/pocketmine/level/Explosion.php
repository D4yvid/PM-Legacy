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

namespace pocketmine\level;

use InvalidArgumentCountException;
use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\protocol\ExplodePacket;
use pocketmine\utils\Random;
use SplFixedArray;

/**
 * Summary of Explosion
 */
class Explosion
{
	/** @var SplFixedArray */
	private $affectedBlocks;

	/** @var Entity|Block */
	private $what;

	private $level;

	private $source;

	private $size;

	private $yield;

	private $kb;

	public function __construct(Position $center, $size, $what = null, $kb = 8)
	{
		if ($size > 255 || $size < 0)
			throw new InvalidArgumentCountException("The size needs to be <= 255 && >= 0, $size given!");

		$this->affectedBlocks = new SplFixedArray(0);

		$this->level = $center->getLevel();
		$this->source = $center;
		$this->size = max($size, 0);
		$this->what = $what;
		$this->yield = (1 / $size) * 100;
		$this->kb = $kb;
	}

	/**
	 * @return bool
	 */
	public function explodeA()
	{
		if ($this->size < 0.1) {
			return false;
		}

		if ($this->what instanceof Entity && $this->level->getBlock($this->what)->getId() == BlockIds::WATER)
			return false;

		for ($x = -$this->size; $x < $this->size; $x++) {
			for ($y = -$this->size; $y < $this->size; $y++) {
				for ($z = -$this->size; $z < $this->size; $z++) {
					$block = $this->level->getBlockFromXYZ($this->source->x + $x, $this->source->y + $y, $this->source->z + $z);

					if ($block->getId() == 0 ||
						$block->distance($this->source) > 2 && mt_rand(0, 10) > 5)
						continue;

					/* Store all coordinate data relative to the center inside of a 32-bit integer */
					$negData = (((int)($x < 0)) << 2) | (((int)($y < 0)) << 1) | ((int)$z < 0);

					$data = ((abs($x) & 0xFF) << 19) | ((abs($y) & 0xFF) << 11) | ((abs($z) & 0xFF) << 3) | $negData;
					$size = $this->affectedBlocks->getSize();

					/* Allocate a value inside the array */
					$this->affectedBlocks->setSize($size + 1);

					/* Put the new item in the array */
					$this->affectedBlocks[$size] = $data;
				}
			}
		}

		return true;
	}

	/**
	 * Summary of explodeB
	 * @return bool
	 */
	public function explodeB()
	{
		$send = [];

		$source = (new Vector3($this->source->x, $this->source->y, $this->source->z))->floor();

		if ($this->what instanceof Entity) {
		}

		$explosionSize = $this->size * 2;
		$minX = (int)floor($this->source->x - $explosionSize - 1);
		$maxX = (int)ceil($this->source->x + $explosionSize + 1);
		$minY = (int)floor($this->source->y - $explosionSize - 1);
		$maxY = (int)ceil($this->source->y + $explosionSize + 1);
		$minZ = (int)floor($this->source->z - $explosionSize - 1);
		$maxZ = (int)ceil($this->source->z + $explosionSize + 1);

		$explosionBB = new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);
		$list = $this->level->getNearbyEntities($explosionBB, $this->what instanceof Entity ? $this->what : null);

		foreach ($list as $entity) {
			$distance = $entity->distance($this->source) / $explosionSize;

			if ($distance <= 1) {
				$motion = $entity->subtract($this->source)->normalize();
				$impact = (1 - $distance) * $this->kb;

				$entity->setMotion($motion->multiply($impact));
			}
		}

		foreach ($this->affectedBlocks as $coord) {
			$negX = (($coord >> 2) & 0b1);
			$negY = (($coord >> 1) & 0b1);
			$negZ = (($coord) & 0b1);

			$x = ($source->x + (($coord >> 19) & 0xFF)) + ($negX ? -$this->size : 0);
			$y = ($source->y + (($coord >> 11) & 0xFF)) + ($negY ? -$this->size : 0);
			$z = ($source->z + (($coord >> 3) & 0xFF)) + ($negZ ? -$this->size : 0);

			$block = $this->level->getBlockFromXYZ($x, $y, $z);

			if ($block->getId() === Block::TNT) {
				$mot = (new Random())->nextSignedFloat() * M_PI * 2;

				$tnt = Entity::createEntity("PrimedTNT", $this->level->getChunk($block->x >> 4, $block->z >> 4), new CompoundTag("", [
					"Pos" => new ListTag("Pos", [
						new DoubleTag("", $block->x + 0.5),
						new DoubleTag("", $block->y),
						new DoubleTag("", $block->z + 0.5)
					]),
					"Motion" => new ListTag("Motion", [
						new DoubleTag("", -sin($mot) * 0.22),
						new DoubleTag("", 0.2),
						new DoubleTag("", -cos($mot) * 0.22)
					]),
					"Rotation" => new ListTag("Rotation", [
						new FloatTag("", 0),
						new FloatTag("", 0)
					]),
					"Fuse" => new ByteTag("Fuse", mt_rand(30, 39))
				]));

				$tnt->spawnToAll();
			}

			$this->level->setBlockIdAt($block->x, $block->y, $block->z, 0);
			$this->level->updateAround($block);

			$send[] = new Vector3($block->x - $source->x, $block->y - $source->y, $block->z - $source->z);
		}

		$pk = new ExplodePacket();

		$pk->x = $this->source->x;
		$pk->y = $this->source->y;
		$pk->z = $this->source->z;
		$pk->radius = $this->size;
		$pk->records = $send;

		$this->level->addChunkPacket($source->x >> 4, $source->z >> 4, $pk);

		return true;
	}
}