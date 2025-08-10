<?php

declare(strict_types=1);

namespace FactionsPro\inventory;

use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\nbt\tag\CompoundTag;

use pocketmine\level\Position;
use pocketmine\math\Vector3;

use pocketmine\block\Block;
use pocketmine\tile\Chest;

use pocketmine\Player;

class NormalWindow extends Window {

	public const TYEPE = self::NORMAL_WINDOW;

	public function getNetworkType() : int {
		return WindowTypes::CONTAINER;
	}

	public function getDefaultSize() : int{
		return 27;
	}

	public function onOpen(Player $player) : void {
		$packet = $this->createSpawnPacket($this->getHolder());
		$packet->namedtag = $this->getSerializedSpawnCompound($this->getSpawnCompound());

		$player->dataPacket($packet);
		parent::onOpen($player);
	}

	/**
	 * @return Block[]
	 */
	public function getBlocks(bool $realBlock) : array {
		$position = $this->position;
		$blocks = [];

		if (false === $realBlock) {
			$blocks[] = Block::get(Block::CHEST, 0, Position::fromObject($position->add(0)));
		} else {
			$blocks[] = $position->level->getBlock($position->add(0), true, false);
		}
		return $blocks;
	}

	/**
	 * @return CompoundTag
	 */
	public function getSpawnCompound() : CompoundTag {
		$nbt = Chest::createNBT($this->getHolder());
		$nbt->setString('CustomName', $this->windowName);

		return $nbt;
	}

}