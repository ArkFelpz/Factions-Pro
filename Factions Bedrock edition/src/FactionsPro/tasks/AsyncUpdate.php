<?php

namespace FactionsPro\tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use FactionsPro\FactionMain;

class AsyncUpdate extends AsyncTask
{

	private $folder, $monies, $sp;

	public function __construct (string $folder, array $monies = [])
	{
		$this->folder = $folder . 'FactionsData.json';
		$this->sp = $folder . "MConfig.yml";
		$this->monies = serialize($monies);
	}

	public function getSpawnerCoins ($data)
	{
		$sp = new Config($this->sp);
		$prices = $sp->get("prices");
		$coins = 0;
		if (isset($data["data"]["spawners"])) {
			foreach ($data["data"]["spawners"] as $id => $d) {
				if (isset($prices[$id])) {
					$coins += $prices[$id] * $d["count"];
				}
			}
		}
		else {
			print_r($data);
		}
		return $coins;
	}

	public function onRun ()
	{
		$monies = unserialize($this->monies);
		$all = (new Config($this->folder))->getAll();
		$factions = [];
		$cache = [];
		$cache["coins"] = [];
		$cache["power"] = [];
		$cache["geral"] = [];
		$cache["spawners"] = [];
		foreach ($all as $fac => $d) {
			$factions[$fac] = [
					'players' => [],
					'tag' => '---'
			];
			
			foreach (($d["data"]["players"] ?? []) as $p => $r) {
				$factions[$fac]["players"][] = [
						$p,
						$r
				];
			}
			if (isset($d["tag"])) {
				$factions[$fac]["tag"] = $d["tag"];
			}
		}
		foreach ($factions as $name => $data) {
			$cache["spawners"][$name] = $this->getSpawnerCoins($all[$name]);
			foreach (($data['players'] ?? []) as $value) {
				if (! isset($cache['coins'][$name])) {
					$cache['coins'][$name] = 0;
				}
				$cache['coins'][$name] += (int) ($monies[strtolower($value[0])] ?? 0);
			}
			$power = $all[$name]["power"];

			$cache['power'][$name] = $power;
		}
		if (isset($cache['coins'])) arsort($cache['coins'], true);
		if (isset($cache['power'])) arsort($cache['power'], true);
		if (isset($cache['spawners'])) arsort($cache['spawners'], true);
		foreach ($cache['coins'] as $f => $v) {
			$cache['geral'][$f] = $v;
		}
		foreach ($cache['spawners'] as $f => $v) {
			$cache['geral'][$f] += $v;
		}
		$this->setResult($cache);
	}

	public function onCompletion (Server $server)
	{
		$manager = $server->getPluginManager();

		if (($main = $manager->getPlugin('FactionsPro')) instanceof PluginBase) {
			$result = $this->getResult();
			$main->setCacheData($result);
		}
	}
}