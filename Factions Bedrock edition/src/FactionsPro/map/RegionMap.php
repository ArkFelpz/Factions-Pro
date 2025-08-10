<?php

namespace FactionsPro\map;

use pocketmine\level\Position;
use FactionsPro\FactionMain;

use pocketmine\Player;

class RegionMap {

	public const MAP_HEIGHT = 14;
	public const MAP_WIDTH = 56;

	public $scale = 10;

	private $isLoad = false;
	private $manager;

	public function __construct(FactionMain $manager) {
		$this->manager = $manager;
		$this->isLoad = true;

		$this->scale = ceil(max(1, $manager->prefs->get("PlotSize")) / 5);
	}

	public function getProtector() {
		return $this->manager->getServer()->getPluginManager()->getPlugin("WorldGuard");
	}

	public function getMap(Player $player) : string {
		$position = $player->asPosition();
		if(!$this->isLoad or !$position->isValid()) return '';

		$symbol = "\u{275a}";

		$midX = floor(self::MAP_WIDTH / 2);
		$midY = floor(self::MAP_HEIGHT / 2);

		$map = array_fill(0, $midY * 2, '');
		$manager = $this->manager;

		$faction = $manager->isInFaction($player->getName()) ? $manager->getFaction($player->getName()) : null;
		$extra = [];

		for($y = -$midY; $y < $midY; $y++) {
			for($x = -$midX; $x < $midX; $x++) {
				$currentLine = $y + $midY;
				if ($y == 0 and $x == 0) {
					$map[$currentLine] .= "§e".$symbol;
					continue;
				}
				$currentPosition = $position->add($x * $this->scale, 0, $y * $this->scale);
				$currentFaction = $manager->factionFromPoint($currentPosition->x, $currentPosition->z, $position->getLevel()->getName());

				if ($currentFaction == null) {
					
					if (($protector = $this->getProtector()) !== null) {
						if ($protector->getRegionFromPosition(Position::fromObject($currentPosition, $position->getLevel())) !== "") {
							$map[$currentLine] .= "§6".$symbol;
						} else {
							$map[$currentLine] .= "§7".$symbol;
						}
					} else {
						$map[$currentLine] .= "§7".$symbol;
					}
				} else {
					if ($currentFaction == $faction) {
						$map[$currentLine] .= "§f".$symbol;
					} elseif($manager->areAllies($faction, $currentFaction)) {
						$map[$currentLine] .= "§a".$symbol;
						// $extra['aliados'][$currentFaction] =
					} elseif($manager->areEnemies($faction, $currentFaction)) {
						$map[$currentLine] .= "§4".$symbol;
						// $extra['rivais'][$currentFaction] =
					} else {
						$map[$currentLine] .= "§8".$symbol;
						// $extra['neutro'][$currentFaction] =
					}
				}
			}
		}

		$north = self::getCompassPointForDirection($player->getYaw(), true);
		$south = self::getCompassPointForDirection($player->getYaw(), false);

		$map[1] .= str_replace($north, "§l§c".$north."§l§6", "§l§6 \\N/");
		$map[2] .= str_replace($south, "§l§c".$south."§l§6", "§l§6 W+E");
		$map[3] .= str_replace($south, "§l§c".$south."§l§6", "§l§6 /S\\");

		$map[5] .= " §e".$symbol." Você";
		$map[6] .= " §6".$symbol." Zona Protegida";
		$map[7] .= " §7".$symbol." Zona Livre";
		$map[8] .= " §4".$symbol." Zona Rival";
		$map[9] .= " §a".$symbol." Zona Aliada";
		$map[10] .= " §8".$symbol." Zona Neutra";
		$map[11] .= " §f".$symbol." Sua Facção";

		$size = 29;
		$extra = "§eMapa da região:§f Veja as informações de terras próximas abaixo.\n";
		$extra .= "§2".str_repeat("-", $size)."(§f".$position->getFloorX().", ".$position->getFloorY().", ".$position->getFloorZ()."§2)".str_repeat("-", $size);

		return "\n".$extra."\n".implode("§r\n", $map);
	}

	public static function getCompassPointForDirection(float $degrees, bool $toNorth = null) : string{
        $degrees = ($degrees - 180) % 360;
		if($degrees < 0) $degrees += 360;
		
		switch(true) {
			case ((292.5 <= $degrees and $degrees < 337.5 and (is_null($toNorth) or $toNorth)) or (112.5 <= $degrees && $degrees < 157.5 and (is_null($toNorth) or !$toNorth))):
				return "\\";
			case ((22.5 <= $degrees and $degrees < 67.5 and (is_null($toNorth) or $toNorth)) or (202.5 <= $degrees and $degrees < 247.5 and (is_null($toNorth) or !$toNorth))):
				return '/';
			case (0 <= $degrees and $degrees < 22.5):
				return 'N';
			case (157.5 <= $degrees && $degrees < 202.5):
				return 'S';
			case (247.5 <= $degrees && $degrees < 292.5):
				return 'W';
			case (67.5 <= $degrees and $degrees < 112.5):
				return 'E';
		}
		return '';
	}

}