<?php

namespace FactionsPro\Task;

use pocketmine\scheduler\Task as PluginTask;
use pocketmine\utils\TextFormat;

use FactionsPro\FactionMain;

class FactionAtack extends PluginTask{
	
	private $next = 0;
	private $time = 180 * 4; //3 min
	
	private $faction = null;
	private $pos = null;
	
	public function __construct(FactionMain $plugin, $fac, $pos){
		//parent::__construct($plugin);
		$this->plugin = $plugin;
		
		$this->faction = $fac;
		$this->pos = $pos;
	}
	
	
	public function onRun(int $timer){
		$this->time--;
		if($this->time <= 0){
			if(isset($this->plugin->atacks[$this->pos][$this->faction])){
				unset($this->plugin->atacks[$this->pos][$this->faction]);
			}
			$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
		} else {
			$this->broadcast($this->getTextMov("Sua facção està sobre ataque!"));
		}
	}
	
	public function setTimer(int $timer){
		$this->time = $timer;
	}
	
	public function broadcast(string $msg = ""){
		if(is_null($this->faction)){
			return true;
		}
		$players = $this->plugin->getPlayersByFaction($this->faction);
		if(count($players) < 1){
			return true;
		}
		foreach($players as $p){
			$p->sendPopup($msg);
		}
		return true;
	}
	
	public function getTextMov(string $text, $cor = "§4"){
		$size = strlen($text);
		
		
		for($i = 0; $i <= $size; $i++){
			$letra = substr($text, $i, 1);
			if($i == $this->next){
				$next = $i + 1;
				$next = substr($text, $next, 1) == " " ? ($i + 2) : $next;
				
				if($next > $size){
					$next = 0;
				}
				$this->next = $next;
				$format = TextFormat::RED . substr($text, 0, $i) . TextFormat::DARK_RED . substr($text,  $i, 1) . TextFormat::RED . substr($text, $i + 1, $size - ($i + 1));
				/* ANT BUG */
				$format = str_replace(["v", "k", "h"], ["ç", "ã", "á"], $format);
				
				return $format;
			}
		}
		$this->next = 0;
		return "§c$text";
	}
	
	public function getFistLetra($text, $min = 1){
		for($i = 0; $i <= strlen($text); $i++){
			if($i >= $min){
				$letra = substr($text, $i, 1);
				if($letra !== "" or $letra !== " "){
					return $letra;
				}
			}
		}
		return false;
	}
	
}