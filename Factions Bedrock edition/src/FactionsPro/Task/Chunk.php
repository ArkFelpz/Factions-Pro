<?php

namespace FactionsPro\Task;

use pocketmine\scheduler\Task as PluginTask;
use pocketmine\level\particle\DustParticle as ExplodeParticle;
use pocketmine\math\Vector3;
use FactionsPro\FactionMain;
use pocketmine\block\Block;

class Chunk extends PluginTask{
	
	public function __construct($plugin, $player){
		$this->plugin = $plugin;
		$this->player = $player->getName();
		
	}
	
	public function onRun(int $timer){
		$p = $this->plugin->getServer()->getPlayerExact($this->player);
		
		if(!is_null($p)){
			$chunk = $p->chunk;
			$level = $p->getLevel();
			$x = $chunk->getX() << 4;
			$z = $chunk->getZ() << 4;
			$cor = mt_rand(0, 15);
			
			for($xx = $x; $xx <= ($x + 16); $xx += 8){
				for($zz = $z; $zz <= ($z + 16); $zz += 8){
					if($xx == $x or $xx == ($x + 16) or $zz == $z or $zz == ($z + 16)){
						for($y = ($p->y - 10); $y <= ($p->y + 10); $y += 2){
							$level->addParticle(new ExplodeParticle(new Vector3($xx, $y, $zz), 255, 255, 255, 255));
							$level->addParticle(new ExplodeParticle(new Vector3($xx, $y, $zz), 255, 255, 255, 255));
						}
					}
				}
			}
		} else {
			unset($this->plugin->chunk[strtolower($this->player)]);
			$this->cancelled();
		}
	}
	
	public function cancelled(){
		unset($this->plugin->chunk[strtolower($this->player)]);
		$this->plugin->getScheduler()->cancelTask($this->getTaskId());
	}
}