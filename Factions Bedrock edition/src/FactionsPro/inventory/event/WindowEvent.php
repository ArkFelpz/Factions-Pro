<?php

namespace FactionsPro\inventory\event;

use FactionsPro\inventory\Window;

use pocketmine\item\Item;
use pocketmine\Player;

class WindowEvent {

	private $lastTransaction = false;

	private  $actions;
	private $window;
	private $source;

	public function __construct(Window $window, Player $source, array $actions, bool $lastTransaction = false) {
		$this->window = $window;
		$this->source = $source;
		$this->actions = $actions;
		$this->lastTransaction = $lastTransaction;
	}

	public function isLastTransaction() : bool {
		return $this->lastTransaction;
	}

	public function getClickedItem() : Item {
		$action = $this->actions[0];
		return $action->oldItem->getId() === Item::AIR ? $action->newItem : $action->oldItem;
	}

	public function getWindow() : Window {
		return $this->window;
	}

	public function getSource() : Player {
		return $this->source;
	}

	public function getActions() : array {
		return $this->actions;
	}
}