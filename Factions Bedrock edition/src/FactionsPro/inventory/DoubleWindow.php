<?php

declare(strict_types=1);

namespace FactionsPro\inventory;

use pocketmine\network\mcpe\protocol\types\WindowTypes;

use pocketmine\scheduler\Task;
use pocketmine\nbt\tag\CompoundTag;

use pocketmine\level\Position;
use pocketmine\math\Vector3;

use pocketmine\block\Block;
use pocketmine\tile\Chest;

use pocketmine\Server;
use pocketmine\Player;

class DoubleWindow extends Window {

	public const TYEPE = self::DOUBLE_WINDOW;

	public function getNetworkType() : int {
		return WindowTypes::CONTAINER;
	}

	public function getDefaultSize() : int{
		return 54;
	}

	public function onOpen(Player $player) : void {
		for($i = 0; $i <= 1; $i++) {
			$packet = $this->createSpawnPacket($this->position->add($i));

			$packet->namedtag = $this->getSerializedSpawnCompound(
				$this->getSpawnCompound((bool) $i)
			);
			$player->dataPacket($packet);
		}

		$tps = Server::getInstance()->getTicksPerSecond();
		$time = $player->getPing() <= 300 ? 6 : 0;

		if($tps >= 19) {
			$time = (int) ($time / 1.1);
		}

		self::$handler->getScheduler()->scheduleDelayedTask(
			new class($this, $player) extends Task {
				
				public function __construct(DoubleWindow $window, Player $player) {
					$this->window = $window;
					$this->player = $player;
				}
				
				public function onRun($timer) {
					if(($player = $this->player)->isOnline()) $this->window->finalOpening($player);
				}
			},
			$time
		);
	}

	/**
	 * @return Block[]
	 */
	public function getBlocks(bool $realBlock) : array {
		$position = $this->position;
		$blocks = [];

		if (false === $realBlock) {
			$blocks[] = Block::get(Block::CHEST, 0, Position::fromObject($position->add(0), $position->level));
			$blocks[] = Block::get(Block::CHEST, 0, Position::fromObject($position->add(1), $position->level));
		} else {
			$blocks[] = $position->level->getBlock($position->add(0), true, false);
			$blocks[] = $position->level->getBlock($position->add(1), true, false);
		}
		return $blocks;
	}

	/**
	 * @return CompoundTag
	 */
	public function getSpawnCompound(bool $double = false) : CompoundTag {
		$nbt = Chest::createNBT($this->position->add($double ? 1 : 0));
		$nbt->setString('CustomName', $this->windowName);

		$nbt->setInt(Chest::TAG_PAIRX, $this->position->x + ($double ? 0 : 1));
		$nbt->setInt(Chest::TAG_PAIRZ, $this->position->z);

		return $nbt;
	}

}