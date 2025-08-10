<?php

namespace FactionsPro\tasks;

use pocketmine\scheduler\Task;
use FactionsPro\FactionMain;

class UpdaterTimer extends Task {

	private $main;

	public function __construct(FactionMain $main) {
		$this->main = $main;
	}

	public function onRun($timer) {
		$monies = [];
		if(($economy = $this->main->getServer()->getPluginManager()->getPlugin('EconomyAPI'))) {
			$monies = method_exists($economy, 'getAllMoney') ? $economy->getAllMoney() : [];
		}

		$this->main->getServer()->getAsyncPool()->submitTask(
			new AsyncUpdate($this->main->getDataFolder(), $monies)
		);
	}

}