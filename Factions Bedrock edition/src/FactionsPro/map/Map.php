<?php

namespace FactionsPro\map;

use pocketmine\level\Position;
use pocketmine\Player;

use FactionsPro\FactionMain;

class Map{
	
	private $letras = "";
	
	const NN = 'N';
    const NE = '/';
    const EE = 'L';
    const SE = '\\';
    const SS = 'S';
    const SW = '/§r';
    const WW = 'O';
    const NW = '\\§r';
	
	protected $x = 16;
	protected $y = 26;
	
	public $plugin = null;
	
	public function __construct(FactionMain $plugin, int $y = 16, int $x = 26){
		$this->plugin = $plugin;
		
		$this->x = $x;
		$this->y = $y;
	}
	
	private $list = [];
	
	public function sendMapTo(Player $p, bool $noArray = true){
		$fac = $this->plugin->getPlayerFaction($p->getName());
		
		$b =  $this->plugin->getMapBlock();
		$chunk = $p->chunk;
		
		$minX = $chunk->getX();
		$minZ = $chunk->getZ();
		
		$mX = ceil($this->y / 2);
		$mZ = ceil($this->x / 2);
		
		$this->list = ["§4" => [], "§a" => []];
		
		$point = self::getCompassPointForDirection($p->getYaw());
		$msg = " §l§7X§r  ";
		//$msg .= "§a" . str_repeat("-", $mX) . "()" .str_repeat("-", $mX) . "§r\n";
		for($i = 0; $i <= ($mZ * 2) + 1; $i++){
			$msg .= "§l§f" . substr($this->letras, $i, 1) . "§r§f.";
		}
		$msg .= "§r\n";
		
		$n = 1;
		for($x = -$mX; $x <= $mX; $x++){
			for($z = -$mZ; $z <= ($mZ + 1); $z++){
				if($z == -$mZ){
					$nn = $x + $mX + 1;
					if($nn <= 9){
						$nn = "0" . $nn;
					}
					$msg .= "§f" . $nn . ". §r";
				}
				if($x == 0 and $z == 0){
					$msg .= "§b" . $b;
				} else {
					$key = FactionMain::pos($x, $z, $p->getLevel());
					if(isset($this->plugin->atacks[$key][$fac])){
						$msg .= "§d$b";
					} else {
						$pos = new Position((($minX + $x) << 4) + 8, $p->y, (($minZ + $z) << 4) + 8, $p->getLevel());
						$nn = $x + $mX + 1;
						if($nn <= 9){
							$nn = "0" . $nn;
						}
						$k = "§l§f" . substr($this->letras, $z + $mZ, 1) . "§r§f:" . $nn . "§7";
						$msg .= $this->getColorPoint($p, $pos, $k) . $b;
					}
					
				}
			}
			switch($n){
				case 1:
				$msg .= " " . ($point === self::NW ? "§l§c" : "§l§6") . self::NW . "§r";
				$msg .= ($point === self::NN ? "§l§c" : "§l§6") . self::NN . "§r";
				$msg .= ($point === self::NE ? "§l§c" : "§l§6") . self::NE . "§r";
				break;
				case 2:
				$msg .= " " . ($point === self::WW ? "§l§c" : "§l§6") . self::WW . "§r";
				$msg .= "§l§6+§r";
				$msg .= ($point === self::EE ? "§l§c" : "§l§6") . self::EE . "§r";
				break;
				case 3:
				$msg .= " " . ($point === self::SW ? "§l§c" : "§l§6") . self::SW . "§r";
				$msg .= ($point === self::SS ? "§l§c" : "§l§6") . self::SS . "§r";
				$msg .= ($point === self::SE ? "§l§c" : "§l§6") . self::SE . "§r";
				break;
				case 6:
				$msg .= " §l§b$b §r§bVocê";
				break;
				case 7:
				$msg .= " §l§f$b §r§fSua Facçao";
				break;
				case 8:
				$msg .= " §l§a$b §r§aFacçao Aliada";
				break;
				case 9:
				$msg .= " §l§4$b §r§4Zona Inimiga";
				break;
				case 10:
				$msg .= " §l§d$b §r§dSob Ataque";
				break;
				case 11:
				$msg .= " §l§6$b §r§6Zona Protegida";
				break;
			}
			$msg .= "§r\n";
			
			$n++;
		}
		if(count($this->list) >= 1){
			$msg .= "§r\n";
			$m = 0;
			foreach($this->list as $cor => $data){
				$n = 0;
				foreach($data as $k => $fac){
					if($n == 0){
						$msg .= $cor . $b . ":§7";
					}
					$msg .= " [($k) $fac],";
					$n++;
				}
			}
			$m++;
			if($m >= 2){
				$msg .= "§r\n";
				$m = 0;
			} else {
				$msg .= " §8|§f ";
			}
		}
		$p->sendMessage($msg);
	}
	
	/*
	 * ORDEM:
	 *
	 * Aliada
	 * Claimada
	 * Sua posição
	 * Zona Livre
	 * Zona Protegida
	 * Sua facção
	 * Sob ataque
	 */
	
	public function getColorPoint(Player $p, $pos, $k){
		$fac = $this->plugin->getPlayerFaction($p->getName());
		$facc = $this->plugin->factionFromPoint($pos->x, $pos->z, $p->getLevel());
		if(is_null($facc)){
			return "§7";
		}
		
		if(isset($this->plugin->atacks[$this->plugin::pos($pos)])){
		 return "§d";
		}
		if($fac !== "false" and strtolower($fac) == strtolower($facc)) {
			return "§f";
		} elseif($this->plugin->areAllies($fac, $facc)){
			if(!in_array($facc, $this->list["§a"])){
				$this->list["§a"][$k] = $facc;
			}
			return "§a";
		} else {
			if(!in_array($facc, $this->list["§4"])){
				$this->list["§4"][$k] = $facc;
			}
			return "§4";
		}
		if($this->hasProtection($p)){
			return "§6";
		}
		return "§7";
	}
	
	public function hasProtection(Position $pos){
		$protec = $this->plugin->getServer()->getPluginManager()->getPlugin("iProtector");
		if(is_null($protec)){
			return false;
		}
		if($protec->canGetHurt($pos)){
			return true;
		}
		return false;
	}
	
    public static function getCompassPointForDirection($degrees)
    {
        $degrees = ($degrees - 180) % 360;
        if ($degrees < 0)
            $degrees += 360;

        if (0 <= $degrees && $degrees < 22.5)
            return self::NN;
        elseif (22.5 <= $degrees && $degrees < 67.5)
            return self::NE;
        elseif (67.5 <= $degrees && $degrees < 112.5)
            return self::EE;
        elseif (112.5 <= $degrees && $degrees < 157.5)
            return self::SE;
        elseif (157.5 <= $degrees && $degrees < 202.5)
            return self::SS;
        elseif (202.5 <= $degrees && $degrees < 247.5)
            return self::SW;
        elseif (247.5 <= $degrees && $degrees < 292.5)
            return self::WW;
        elseif (292.5 <= $degrees && $degrees < 337.5)
            return self::NW;
        elseif (337.5 <= $degrees && $degrees < 360.0)
            return self::NN;
        else
            return null;
    }
}