<?php

declare(strict_types=1);

namespace FactionsPro\inventory;

use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;

use pocketmine\network\mcpe\protocol\types\ContainerIds;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\inventory\ContainerInventory;

use pocketmine\inventory\BaseInventory;

use pocketmine\scheduler\Task;
use pocketmine\nbt\tag\CompoundTag;

use pocketmine\plugin\PluginBase;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

use pocketmine\block\Block;
use pocketmine\tile\Chest;

use pocketmine\Server;
use pocketmine\Player;

abstract class Window extends ContainerInventory {

	public const DOUBLE_WINDOW = 1;
	public const NORMAL_WINDOW = 0;

	protected static $handler = null;
	protected static $users = [];

	/**
	 * @param PluginBase $handler
	 */
	public static function registerHandler(PluginBase $handler) : void {
		if(self::$handler === null) self::$handler = $handler;
	}

	public static function getWindow($user) {
		if($user instanceof Player) {
			$user = $user->getName();
		}
		return self::$users[strtolower($user)] ?? null;
	}

	public static function reopenWindow(Player $player, Window $window) : void {
		if(($inventory = Window::getWindow($player)) instanceof Window) {
			$player->removeAllWindows(true);
			usleep(1000);
		}
		$tps = Server::getInstance()->getTicksPerSecond();
		$time = $player->getPing() <= 1000 ? 10 : 0;
		
		if($tps <= 10) {
			$time = (int) ($time / 1.1);
		}
$time = 40;
		self::$handler->getScheduler()->scheduleDelayedTask(
			new class($window, $player) extends Task {
				
				public function __construct(Window $window, Player $player) {
					$this->window = $window;
					$this->player = $player;
				}
				
				public function onRun($timer) {
					if(($player = $this->player)->isOnline()){
						$player->addWindow($this->window);
					}
				}
			},
			$time
		);
	}

	public static function get(Position $position, string $name = 'Chest', int $windowType = 0) : ?Window {
		if($windowType === self::NORMAL_WINDOW) {
			return (new NormalWindow($position, $name));
		}
		if(self::$handler === null) return null;

		if($windowType === self::DOUBLE_WINDOW) {
			return (new DoubleWindow($position, $name));
		}
		return $window;
	}

	public static function getSlot(int $row = 0, int $column = 1) {
		return (max(1, $row) + ((max(1, $column) - 1) * 9)) - 1;
	}

	/**
	 * @var Position
	 */
	protected $position;
	/**
	 * @var string
	 */
	protected $windowName;
	protected $lastTransactions = [];

	protected $local = 'menu';
	protected $page = 1;

	protected function __construct(Position $position, string $name) {
		$pos = clone $position->floor();
		$this->position = new Position($pos->getX(), $pos->getY() + ($pos->getY() < 4 ? 3 : -3), $pos->getZ(), $position->getLevel());

		$this->windowName = substr($name, 0, 32);
		parent::__construct($this->position->asVector3());
	}

	public function isLastTransaction(string $name) : bool {
		if(isset($this->lastTransactions[$name])) {
			unset($this->lastTransactions[$name]);
			return true;
		}
		$this->lastTransactions[$name] = true;
		return false;
	}

	public function getName() : string{
		return $this->windowName;
	}

	public function setLocal(string $local) : void {
		$this->local = $local;
	}

	public function getLocal() : string {
		return $this->local;
	}

	public function setPage(int $page) : void {
		$this->page = max(1, $page);
	}

	public function getPage() : int {
		return $this->page;
	}


	public function open(Player $player) : bool {
		self::$users[strtolower($player->getName())] = $this;
		$this->position->level->sendBlocks([$player], $this->getBlocks(false), UpdateBlockPacket::FLAG_ALL_PRIORITY);
		
		return parent::open($player);
	}

	public function onClose(Player $player) : void {
		$this->position->level->sendBlocks([$player], $this->getBlocks(true), UpdateBlockPacket::FLAG_ALL_PRIORITY);
		unset(self::$users[strtolower($player->getName())]);
		parent::onClose($player);
	}

	/**
	 * @return Block[]
	 */
	abstract public function getBlocks(bool $realBlock) : array;

	/**
	 * Returns encoded NBT (varint, little-endian) used to spawn this block entity to clients.
	 *
	 * @return string encoded NBT
	 */
	final public function getSerializedSpawnCompound(CompoundTag $compound) : string {
		return (new NetworkLittleEndianNBTStream())->write($compound);
	}

	final public function createSpawnPacket(Vector3 $position) : BlockActorDataPacket {
		$packet = new BlockActorDataPacket();
		$packet->x = $position->x;
		$packet->y = $position->y;
		$packet->z = $position->z;

		return $packet;
	}

	final public function finalOpening(Player $player) : void {
	parent::onOpen($player);
	}

}