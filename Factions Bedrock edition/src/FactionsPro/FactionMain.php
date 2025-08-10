<?php

namespace FactionsPro;

use FactionsPro\tasks\AttackTimer;
use FactionsPro\tasks\UpdaterTimer;
use generator\tile\MobSpawner;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\inventory\ShapelessRecipe;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use pocketmine\tile\Tile;
use Heisenburger69\BurgerSpawners\Tiles\MobSpawnerTile;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\NamedTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\IntTag;
use muqsit\invmenu\InvMenuHandler;

class SpawnerAtt extends Task{

	public function __construct($pl)
	{
	 $this->pl = $pl;   
	}
	public function onRun(int $t){
		#$this->pl->onSpawnerDebbug();
	}
}
class FactionMain extends PluginBase implements Listener
{

    public static $instance = null;
    public $db;
    public $prefs;
    public $war_req = [];
    public $wars = [];
    public $war_players = [];
    public $antispam;
    public $purechat;
    public $combat;

    /* @var Main */
    public $iprotector;

    public $factionChatActive = [];
    public $allyChatActive = [];
    public $regionMap;
    public $factionFly = [];
    public $fdata, $pdata;

    private $cacheData = [];

    public static $defaultPermissions = [
        'blocks' => true,
        'spawners' => true,
        'tpa' => true,
        'home' => true,
        'chests' => true
    ];

    public function setSpawnOn($p, $dm)
    {
        $fac = $this->getPlayerFaction($p->getName());
        $c = $this->sets;
        $all = [];
        if ($c->exists($fac)) {
            $all = $c->get($fac);
        }
        $all[$dm] = $p->x . "_" . $p->y . "_" . $p->z;
        $c->set($fac, $all);
        $c->save();
        $c->reload();
        $p->sendMessage(TextFormat::colorize("&aLocal De Spawn Aletrado!"));
    }

    public function terra($fac, $add = false)
    {
        $c = $this->sets;
        $all = [];
        if ($c->exists($fac)) {
            $all = $c->get($fac);
        }
        if ($add) {
            $all["plot"] = "sim";
        } else $all["plot"] = "nao";
        $c->set($fac, $all);
        $c->save();
        $c->reload();
    }

    public function getTerras($fac)
    {
        $c = $this->sets;
        $all = [];
        if ($c->exists($fac)) {
            $all = $c->get($fac);
        }
        if (!isset($all["plot"]) or $all["plot"] == "nao") return "0";
        return "1";
    }

    public static function getMain()
    {
        return self::$instance;
    }

    public function onEnable()
    {
        self::$instance = $this;
		
		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}

        @mkdir($this->getDataFolder());
        $ids = [
            10 => "Galinha",
            11 => "Vaca",
            12 => "Porco",
            13 => "Ovelha",
            15 => "Villager",
            16 => "CoguVaca",
            20 => "Golem",
            21 => "Golem de Neve",
            28 => "Urso Polar"
        ];
        $this->sp = new Config($this->getDataFolder() . "MConfig.yml", Config::YAML, [
            "Geradores" => $ids,
            "MobsNaWindow" => [
                35 => "Aranha",
                17 => "Lula",
                37 => "Slime",
                43 => "Blaze",
                32 => "Zumbi",
                23 => "Cavalo"
            ],
            "prices" => [
                35 => 60000,
                17 => 130000,
                37 => 240000,
                43 => 480000,
                32 => 720000,
                23 => 1000000
            ]
        ]);
        /*
        facname => [
            tag => "tagName",
            power => 0,
            players => 1,
            leader => "eu",
            plots => 0,
            ally: => 0,
            enemies => 0,
            data => [
                players => [],
                plots => [],
                ally => [],
                enemies => []
                spawners => [
                    id => [
                        name => "Golem",
                        count => 0
                    ]
                ]
            ]

        ]
        */
        /*
         player => [
             power => 0,
             faction => "",
             kill => 0,
             dead => 0
         ]
         */
        $this->pdata = new Config($this->getDataFolder() . "PlayerData.json");
        $this->fdata = new Config($this->getDataFolder() . "FactionsData.json");
        foreach ($this->fdata->getAll() as $n => $arr) {
            if (!is_array($arr) or count($arr) < 9) {
                $this->fdata->remove($n);
                $this->fdata->save();
            }
        }
        foreach ($this->pdata->getAll() as $n => $arr) {
            if (!$this->factionExists($arr["faction"])) {
                $arr["faction"] = "false";
                $this->pdata->set($n, $arr);
                $this->pdata->save();
            }
        }
        $this->sets = new Config($this->getDataFolder() . "Spawns.json");
        $this->gera = new Config($this->getDataFolder() . "Geradores.json");
        if (!file_exists($this->getDataFolder() . "BannedNames.txt")) {
            $file = fopen($this->getDataFolder() . "BannedNames.txt", "w");
            $txt = "Admin:admin:Staff:staff:Owner:owner:Builder:builder:Op:OP:op";
            fwrite($file, $txt);
        }


        $this->getServer()->getPluginManager()->registerEvents(new FactionListener($this), $this);

        $this->antispam = $this->getServer()->getPluginManager()->getPlugin("AntiSpamPro");
        if (!$this->antispam) {
            $this->getLogger()->info("Add AntiSpamPro to ban rude Faction names");
        }

        $this->iprotector = $this->getServer()->getPluginManager()->getPlugin("iProtector");
        $this->combat = $this->getServer()->getPluginManager()->getPlugin("CombatLogger");

        $this->fCommand = new FactionCommands($this);

        $this->prefs = new Config($this->getDataFolder() . "Prefs.yml", CONFIG::YAML, array(
            "MaxFactionNameLength" => 15,
            "MaxPlayersPerFaction" => 15,
            "OnlyLeadersAndOfficersCanInvite" => true,
            "OfficersCanClaim" => true,
            "PlotSize" => 66,
            "PlayersNeededInFactionToClaimAPlot" => 5,
            "PowerNeededToClaimAPlot" => 45,
            "PowerNeededToSetOrUpdateAHome" => 75,
            "PowerGainedPerPlayerInFaction" => 0,
            "PowerGainedPerKillingAnEnemy" => 1,
            "PowerGainedPerAlly" => 0,
            "AllyLimitPerFaction" => 2,
			"EnemyLimitPerFaction" => 2,
            "TheDefaultPowerEveryFactionStartsWith" => 0,
            "EnableOverClaim" => true,
            "OverClaimCostsPower" => false,
            "ClaimWorlds" => [],
            "AllowChat" => true,
            "AllowFactionPvp" => false,
            "AllowAlliedPvp" => false,
            "EnableMap" => true,
            "MaxMapDistance" => 500,
            "UpdaterTimer" => 300,
            "MaxPowerPerPlayer" => 5
        ));
        $this->prefs->set("MaxPlayersPerFaction", min(15, $this->prefs->get("MaxPlayersPerFaction", 15)));

        $this->regionMap = new map\RegionMap($this);
        $this->getScheduler()->scheduleRepeatingTask(new UpdaterTimer($this), max(1, (int)$this->prefs->get("UpdaterTimer", 300)) * 20);
        $this->getScheduler()->scheduleRepeatingTask(new SpawnerAtt($this), 20);

        $this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, tag TEXT, faction TEXT, rank TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS powers (player TEXT PRIMARY KEY COLLATE NOCASE, power INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT COLLATE NOCASE, faction TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS alliance (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, requestedby TEXT, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT, world TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS home(faction TEXT PRIMARY KEY, x INT, y INT, z INT, world TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS strength(faction TEXT PRIMARY KEY, power INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS allies(ID INT PRIMARY KEY,faction1 TEXT, faction2 TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS enemies(ID INT PRIMARY KEY,faction1 TEXT, faction2 TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS alliescountlimit(faction TEXT PRIMARY KEY, count INT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS perms(player TEXT PRIMARY KEY COLLATE NOCASE, blocks INT, spawners INT, tpa INT, home INT, chests INT);");


        try {
            $this->db->exec("ALTER TABLE plots ADD COLUMN world TEXT default null");
            Server::getInstance()->getLogger()->info(TextFormat::GREEN . "FactionPro: Added 'world' column to plots");
        } catch (\ErrorException $ex) {
        }
//<?
        $i = Item::get(Item::ENCHANTED_GOLDEN_APPLE);
        $re = new ShapelessRecipe(
            [
                0 => Item::get(Item::GOLD_BLOCK, 0, 1),
                1 => Item::get(Item::GOLD_BLOCK, 0, 1),
                2 => Item::get(Item::GOLD_BLOCK, 0, 1),
                3 => Item::get(Item::GOLD_BLOCK, 0, 1),
                4 => Item::get(Item::APPLE, 0, 1),
                5 => Item::get(Item::GOLD_BLOCK, 0, 1),
                6 => Item::get(Item::GOLD_BLOCK, 0, 1),
                7 => Item::get(Item::GOLD_BLOCK, 0, 1),
                8 => Item::get(Item::GOLD_BLOCK, 0, 1)
            ],
            [
                Item::get(466, 0, 1)
            ]);
        $this->getServer()->getCraftingManager()->registerShapelessRecipe($re);
        $pd = $this->pdata;
        foreach($this->fdata->getAll() as $f => $a){
        	foreach($a["data"]["players"] as $n => $rank){
        	$f = $a["name"];
        	$all = $pd->get($n, ["faction" => $f, "power" => 0, "perms" => self::$defaultPermissions]);
            $all["faction"] = $f;
            $pd->set($n, $all);
            //echo $n." => ".$f;
            
           }
        }
        
        
        
        $pd->save();
        $pd->reload();
    }

    public $atacks = [];
    public $chunks = [];
    public $spawners = [];

    public function isVip(Player $p): bool
    {
        $pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        if (!is_null($pp)) {
            $g = $pp->getUserDataMgr()->getGroup($p)->getName();
            if (strtolower($g) !== strtolower("player")) {
                return true;
            }
        }
        return false;
    }

    public function onBreakSpawner($b, $id)
    {
        if (!$this->isInPlot($b)) return false;
        $fac = $this->factionFromPoint($b->getFloorX(), $b->getFloorZ(), $b->getLevel()->getName());
        $data = $this->getFactionData($fac);
        $sp = isset($data["data"]["spawners"][$id]) ? $data["data"]["spawners"][$id] : ["name" => "--", "count" => 0];
        $sp["count"] -= 1;
        if ($sp["count"] < 0) {
            $sp["count"] = 0;
        }
        $data["data"]["spawners"][$id] = $sp;
        $this->fdata->set(strtolower($fac), $data);
        $this->fdata->save();
        $this->fdata->reload();
    }

    public function onSpawnerDebbug()
    {

        foreach ($this->spawners as $id => $a) {
            if ($a["time"] > 0) {
                $a["time"] -= 1;
                $this->spawners[$id] = $a;
                continue;
            } else {
                $b = $a["block"];
                $p = $a["player"];
                $add = (bool)$a["add"];
                unset($this->spawners[$id]);
                $tile = $p->getLevel()->getTile($b->asVector3());
                if ($tile == null) {

                    continue;
                }
                $id = $tile->getEntityId();
                $fac = $this->factionFromPoint($b->getFloorX(), $b->getFloorZ(), $b->getLevel()->getName());
                $data = $this->getFactionData($fac);
                $sp = isset($data["data"]["spawners"][$id]) ? $data["data"]["spawners"][$id] : ["name" => "--", "count" => 0];
                if ($add)
                    $sp["count"] += 1;
                else $sp["count"] -= 1;

                $data["data"]["spawners"][$id] = $sp;
                $this->fdata->set(strtolower($fac), $data);
                $this->fdata->save();
                $this->fdata->reload();

                continue;

            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        $this->fCommand->onCommand($sender, $command, $label, $args);
        return true;
    }
	
	# Atualizará a coloração dos nicks dos jogadores: 
	# 1º Com a mesma facção
	# 2º Aliados da facção
	# 3º Inimigos da facção
	# 4º Não membros da facção
	
	public function updateColorsTag(string $faction){
		$members = [];
		foreach($this->getServer()->getOnlinePlayers() as $ps){
			$members[] = $ps;
		}
		foreach($this->getServer()->getOnlinePlayers() as $ps){ // Yatoo
			foreach($members as $membro){ // Daniel
				if($ps->getName() != $membro->getName()){
					if($this->getPlayerFaction($ps->getName()) == $this->getPlayerFaction($membro->getName())){
						$this->getServer()->broadcastMessage($ps->getName()." => ".$membro->getName(). "1");
						if(!$this->factionExists($this->getPlayerFaction($ps->getName())) or !$this->factionExists($this->getPlayerFaction($membro->getName()))){
							$this->getServer()->broadcastMessage($ps->getName()." => ".$membro->getName(). "2");
							$this->onNickChange($ps, $membro, "§f".$ps->getName());
							$this->onNickChange($membro, $ps, "§f".$membro->getName());
							continue;
						}
						$this->getServer()->broadcastMessage($ps->getName()." => ".$membro->getName(). "3");
						$this->onNickChange($ps, $membro, "§a".$ps->getName());
						$this->onNickChange($membro, $ps, "§a".$membro->getName());
						continue;
					}elseif($this->areAllies($this->getPlayerFaction($ps->getName()), $this->getPlayerFaction($membro->getName()))){
						$this->getServer()->broadcastMessage($ps->getName()." => ".$membro->getName(). "4");
						$this->onNickChange($ps, $membro, "§e".$ps->getName());
						$this->onNickChange($membro, $ps, "§e".$membro->getName());
						continue;
					}elseif($this->areEnemies($this->getPlayerFaction($ps->getName()), $this->getPlayerFaction($membro->getName()))){
						$this->getServer()->broadcastMessage($ps->getName()." => ".$membro->getName(). "5");
						$this->onNickChange($ps, $membro, "§c".$ps->getName());
						$this->onNickChange($membro, $ps, "§c".$membro->getName());
						continue;
					}elseif($this->getPlayerFaction($ps->getName()) != $this->getPlayerFaction($membro->getName())){
						$this->getServer()->broadcastMessage($ps->getName()." => ".$membro->getName(). "6");
						$this->onNickChange($ps, $membro, "§f".$ps->getName());
						$this->onNickChange($membro, $ps, "§f".$membro->getName());
						continue;
					}
				}
			}
		}
	}

    public function isInAtack($fac)
    {
        return AttackTimer::isInAttack($fac);
    }

    public function addAttack($fac)
    {
        AttackTimer::send($this, $fac);
    }

    public function setCacheData(array $cache): void
    {
        $this->cacheData = $cache;
    }

    public function getCacheData(): array
    {
        return $this->cacheData;
    }
	
	public const TAG_ENCH = "ench";
	
	public function setEnchTag(Item $item){
		$ench = $item->getNamedTagEntry(self::TAG_ENCH);
		if(!($ench instanceof ListTag)){
			$ench = new ListTag(self::TAG_ENCH, [], NBT::TAG_Compound);
			$item->setNamedTagEntry($ench);
		}
		return $item;
	}
	
	public function onNickChange(Player $playerNameToChange, Player $playerToSendNameTo, string $name){
		$pk = new \pocketmine\network\mcpe\protocol\SetActorDataPacket();
		$pk->entityRuntimeId = $playerNameToChange->getId();
		$pk->metadata = [
			\pocketmine\entity\Entity::DATA_NAMETAG => [\pocketmine\entity\Entity::DATA_TYPE_STRING, $name]
		]; // not too sure if this will override ALL the metadata properties or not
		$playerToSendNameTo->dataPacket($pk);
	}

    public static function pos($x, $z = null, $l = null)
    {
        if ($x instanceof Position) {
            $z = $x->getFloorZ();
            $l = $x->getLevel();
            $x = $x->getFloorX();
        }
        $chunk = $l->getChunk($x >> 4, $z >> 4);
        if (!is_null($chunk)) {
            return $chunk->getX() . "_" . $chunk->getZ();
        } else return "error";
    }

    public function spawnersString(string $fac): string
    {
        $str = "(";
        $data = $this->getFactionData($fac);
        $names = $this->sp->get("MobsNaWindow");
        foreach ($data["data"]["spawners"] as $id => $d) {
            $n = isset($names[$id]) ? $names[$id] : "Unknown";
            $c = $d["count"];
            if ($c > 0) {
                $str .= $n . " " . $c . ", ";
            }
        }
        $str .= ")";
        $str = str_replace(", )", ")", $str);
        return $str;
    }

    public function domineChunk($p, $faction)
    {

        //Não pensei em outro metodo ja que um salva por chunk e outro por posicoes

        for ($y = 0; $y < 128; $y++) {
            for ($x = 0; $x < 16; $x++) {
                for ($z = 0; $z < 16; $z++) {

                    $iprotector = Server::getInstance()->getPluginManager()->getPlugin('iProtector');

                    if (!$iprotector->canEdit($p, new Position($x, $y, $z, $p->level))) {

                        return 3;
                    }
                }
            }
        }

        if ($this->isInPlot($p) and !$this->inOwnPlot($p)) {
            $ofac = $this->factionFromPoint($p->getFloorX(), $p->getFloorZ(), $p->getLevel());
            $need = 3;
            $plots = $this->getFactionData($ofac, "plots");
            $have = $this->getFactionPower($ofac);
            $counts = $plots * $need + $need + $need;
            if ($counts > $have) {
                $this->unclaimChunk($p, $ofac);
                $pos = self::pos($p);
                $all = $this->getFactionData($faction);
                $all["data"]["plots"][$pos] = $pos;
                $this->fdata->set(strtolower($faction), $all);
                $this->fdata->save();
                $this->fdata->reload();
                $this->onUpdate($faction);
                return 0;
            } else return 1;
        } else {
            $pos = self::pos($p);
            $all = $this->getFactionData($faction);
            $all["data"]["plots"][$pos] = $pos;
            $this->fdata->set(strtolower($faction), $all);
            $this->fdata->save();
            $this->fdata->reload();
            $this->onUpdate($faction);
            return 0;
        }
        return 2;
    }

    public function unclaimChunk(?Position $p = null, $faction, bool $all = false)
    {
        if ($all) {
        	
        	$all = $this->getFactionData($faction);
        	
        	$unclain = true;
        	foreach ($p->getLevel()->getTiles() as $tile) {
        		if ($tile->getSaveId() == 'MobSpawner') {
        			$tx = $tile->getX();
        			$tz = $tile->getZ();
        			
        			foreach (($all["data"]["plots"] ?? []) as $pos_string) {
		        		$chunkX = (int)explode('_', $pos_string)[0];
		        		$chunkZ = (int)explode('_', $pos_string)[1];
		        		
		        		$chunk = $p->getLevel()->getChunk($chunkX, $chunkZ, true);
		        		$minx = $chunk->getX() << 4;
		        		$minz = $chunk->getZ() << 4;
		        		
		        		$maxx = $minx + 16;
		        		$maxz = $minz + 16;
		        		
		        		if ($tx < $maxx and $tx > $minx and $tz < $maxz and $tz > $minz) {
		        			$unclain = false;
		        			break;
		        		}
		        	}
		        	
		        	if (!$unclain) break;
        		}
        	}
        	
        	if (!$unclain) {
        		throw new \Exception('§r§cSpawner nos claims !.');
        	}
        	
            $all["data"]["plots"] = [];
        } else {
        	
        	$unclain = true;
        	
        	$chunk = $p->getLevel()->getChunk($p->getFloorX() >> 4, $p->getFloorZ() >> 4, true);
        	$minx = $chunk->getX() << 4;
        	$minz = $chunk->getZ() << 4;
        	
        	$maxx = $minx + 16;
        	$maxz = $minz + 16;
        	
        	foreach ($p->getLevel()->getTiles() as $tile) {
        		if ($tile instanceof MobSpawner) {
        			$tx = $tile->getX();
        			$tz = $tile->getZ();
        			if ($tx < $maxx and $tx > $minx and $tz < $maxz and $tz > $minz) {
        				$unclain = false;
        				break;
        			}
        		}
        	}
        	
        	if (!$unclain) {
        		throw new \Exception('§r§cSpawners no claim !.');
        	}
        	
            $pos = self::pos($p);
            $all = $this->getFactionData($faction);
            unset($all["data"]["plots"][$pos]);
        }
        $this->fdata->set(strtolower($faction), $all);
        $this->fdata->save();
        $this->fdata->reload();
        $this->onUpdate($faction);
    }

    public function isInPlot(Position $p)
    {
        $pos = self::pos($p);
        foreach ($this->fdata->getAll() as $f => $d) {
        	foreach (($d["data"]["plots"] ?? []) as $pos2) {
                if ($pos == $pos2) return true;
            }
        }
        return false;
    }

    public function inOwnPlot(Player $p)
    {
        if (!$this->isInPlot($p)) return false;
        return strtolower($this->getPlayerFaction($p)) === strtolower($this->factionFromPoint($p->getFloorX(), $p->getFloorZ(), $p->getLevel()));
    }

    public function factionFromPoint($x, $z, $l = null)
    {
        if (is_string($l)) {
            $l = $this->getServer()->getLevelByName($l);
        }
        if (!$l instanceof Level) return null;
        $pos = self::pos($x, $z, $l);
        foreach ($this->fdata->getAll() as $f => $d) {
            foreach (($d["data"]["plots"] ?? []) as $pos2) {
                if ($pos == $pos2) {
                    $fd = $this->getFactionData($f);
                    return $fd["name"];
                }
            }
        }
        return null;
    }

    public function setPlayerData($p, $t, $v)
    {
        if ($p instanceof Player) {
            $p = strtolower($p->getName());
        }
        $all = $this->pdata->get($p);
        $all[$t] = $v;
        $this->pdata->set($p, $all);
        $this->pdata->save();
        $this->pdata->reload();
    }

    public function getPlayerData($p, $t = null)
    {
        if ($p instanceof Player) {
            $p = $p->getName();
        } elseif ($p instanceof StringTag) {
            $p = $p->getValue();
        }
        if ($t == null) $t = "thishsishis";
        $all = $this->pdata->get($p);
        return $all[$t] ?? $all;
    }

    public function setFactionData($f, $t, $v)
    {
        $f = strtolower($f);
        $all = $this->fdata->get($f);
        $all[$t] = $v;
        $this->fdata->set($f, $all);
        $this->fdata->save();
        $this->fdata->reload();
    }

    public function getFactionData($f, $t = null)
    {
        $f = strtolower($f);

        if ($t == null) $t = "thishsishis";
        $all = $this->fdata->get($f);
        return $all[$t] ?? $all;
    }

    public function setPlayerPower($player, int $power = 0): void
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }
        if ($power >= 0 and $power <= $this->prefs->get("MaxPowerPerPlayer", 5)) {
            $this->setPlayerData($player, "power", $power);
            if ($this->isInFaction($player)) {
                $this->updateFactionPower($this->getFaction($player));
            }
        }
    }

    public function getPlayerPower($player): int
    {
		if ($player instanceof Player) {
            $player = $player->getName();
        }
        return $this->getPlayerData($player, "power");
    }
	
	public function saveSpawnersOnInventory(Player $player){
		$faction = $this->getPlayerFaction($player->getName());
		if($this->isLeader($player->getName()) or $this->isOfficer($player->getName())){
			$data = $this->getFactionData($faction);
			$semSpawners = true;
			foreach($player->getInventory()->getContents() as $slot => $item){
				if($item->getId() === 52 && $item->getDamage() !== 0){
					$semSpawners = false;
					$data["data"]["spn"]["inventory"][] = "52:".$item->getDamage().":".$item->getCount();
				}
				$player->getInventory()->setItem($slot, Item::get(0));
			}
			$this->fdata->set($faction, $data);
			$this->fdata->save();
			$player->sendMessage("§aVocê depositou todos os spawners de seu inventário.");
		}else{
			$player->sendMessage("§cVocê deve ser líder ou oficial");
		}
	}
	
	public function getSpawnersOfInventory(Player $player){
		$faction = $this->getPlayerFaction($player->getName());
		if($this->isLeader($player->getName()) or $this->isOfficer($player->getName())){
			$data = $this->getFactionData($faction);
			foreach($data["data"]["spn"]["inventory"] as $key => $spawner){
				$ex = explode(":", $spawner);
				$item = Item::get($ex[0], $ex[1], $ex[2]);
				if($player->getInventory()->canAddItem($item)){
					$player->getInventory()->addItem($item);
					unset($data["data"]["spn"]["inventory"][$key]);
				}else{
					$player->sendMessage("§cNão há mais espaço em seu inventário para adicionar mais itens");
					break;
				}
			}
			$this->fdata->set($faction, $data);
			$this->fdata->save();
			$player->sendMessage("§aVocê recolheu todos os spawners do inventário da facção.");
		}else{
			$player->sendMessage("§cVocê deve ser líder ou oficial");
		}
	}
	
	public function saveSpawnersFromClaim(string $faction){
		$data = $this->getFactionData($faction);
		foreach($data["data"]["spn"]["claim"] as $loc => $spawnerId){
			$data["data"]["spn"]["claim-saved"][$loc] = $spawnerId;
			unset($data["data"]["spn"]["claim"][$loc]);
			$loc = explode(":", $loc);
			$level = $this->getServer()->getLevelByName($loc[3]);
			$loc = new Position($loc[0], $loc[1], $loc[2], $level);
			$level->setBlock($loc, Block::get(0));
			$tile = $level->getTile($loc);
			if($tile instanceof MobSpawnerTile){
				$tile->close();
			}
		}
		$this->fdata->set($faction, $data);
		$this->fdata->save();
	}
	
	public function placeSpawnersOnClaim(string $faction){
		$data = $this->getFactionData($faction);
		foreach($data["data"]["spn"]["claim-saved"] as $loc => $spawnerId){
			$data["data"]["spn"]["claim"][$loc] = $spawnerId;
			unset($data["data"]["spn"]["claim-saved"][$loc]);
			$loc = explode(":", $loc);
			$level = $this->getServer()->getLevelByName($loc[3]);
			$loc = new Position($loc[0], $loc[1], $loc[2], $level);
			if($level->getBlock($loc)->getId() !== 0){
				continue;
			}
			$level->setBlock($loc, Block::get(52, 0));
			$this->getScheduler()->scheduleDelayedTask(new class($this, $level->getBlock($loc), $loc, $spawnerId) extends Task{
				
				public $plugin, $block, $location, $spawnerId;
				
				public function __construct($plugin, $block, $location, $spawnerId){
					$this->plugin = $plugin;
					$this->block = $block;
					$this->location = $location;
					$this->spawnerId = $spawnerId;
				}
				
				public function onRun($ticks){
					$nbt = new CompoundTag("", [
						new StringTag(Tile::TAG_ID, Tile::MOB_SPAWNER),
						new IntTag(Tile::TAG_X, (int)$this->location->x),
						new IntTag(Tile::TAG_Y, (int)$this->location->y),
						new IntTag(Tile::TAG_Z, (int)$this->location->z)
					]);

					$tile = Tile::createTile(Tile::MOB_SPAWNER, $this->location->getLevel(), $nbt);
					if ($tile instanceof MobSpawnerTile) {
						$tile->setEntityId($this->spawnerId);
					}
				}
				
			}, 20);
		}
		$this->fdata->set($faction, $data);
		$this->fdata->save();
	}

    public function createFaction(string $tag, string $faction, string $leader): bool
    {
        if ($this->isInFaction($leader) or $this->factionExists($faction) or $this->tagExists($tag)) {
            return false;
        }
        $this->fdata->set(strtolower($faction), [
            "name" => $faction,
            "tag" => $tag,
            "power" => 0,
            "players" => 1,
            "leader" => $leader,
            "spawners" => 0,
            "plots" => 0,
            "ally" => 0,
            "enemy" => 0,
            "data" => [
                "players" => [
                    $leader => "leader"
                ],
                "plots" => [],
                "ally" => [],
                "enemy" => [],
                "spawners" => [],
				"spn" => [
					"inventory" => [],
					"claim" => [],
					"claim-saved" => [],
				],
            ],
			"permissions" => [
				"ally" => [
					"teleport" => false,
					"home-on-claim" => false,
				],
				"members" => [],
			],

        ]);
        $this->fdata->save();
        $this->fdata->reload();
        $this->setPlayerData($leader, "faction", $faction);
		$this->updateColorsTag($faction);
        return true;
    }

    public function onUpdate($f)
    {
        $all = $this->getFactionData($f);
        foreach ($all["data"] as $type => $arr) {
            $all[$type] = count($arr);
        }
        $this->fdata->set(strtolower($f), $all);
        $this->fdata->save();
        $this->fdata->reload();
    }

    public function getMapBlock()
    {

        $symbol = hex2bin(self::HEX_SYMBOL);

        return $symbol;
    }

    const HEX_SYMBOL = "e29688";

    public function getFactionInfo(string $fac, Player $p)
    {
        $f = $this->getFactionData($fac);
        $sc = 0;
        foreach ($f["data"]["spawners"] as $id => $arr) {
            $sc += $arr["count"];
        }
        $sc .= " " . $this->spawnersString($fac);
        $text = "&f[&6{tag}&f] &6{faction}

&eDono: &7{leader}
&eMembros: &7{members}/15
&ePoder: &7{power}/{maxpower}
&eTerras: &7{dirts}
&eGeradores: &7{spawners}
&eAliados: &7{ally}
&eJogadores Online: &7{online}
{onlines}";
        return $p->sendMessage(TextFormat::colorize(str_replace([
            "{leader}",
            "{members}",
            "{power}",
            "{dirts}",
            "{spawners}",
            "{ally}",
            "{online}",
            "{onlines}",
            "{tag}",
            "{faction}",
            "{maxpower}"
        ], [
            $f["leader"],
            $f["players"],
            $f["power"],
            $f["plots"],
            $sc,
            $this->getAllAlly($f),
            count($this->getPlayersFaction($fac, true)),
            $this->getMembersMessage($f),
            $this->getFactionTag($fac),
            $f["name"],
            ($f["players"] * 5)
        ], $text)));
    }

    public function getAllAlly($f)
    {
        $msg = "";
        foreach ($f["data"]["ally"] as $a => $v) {
            $msg .= $a . " ";
        }
        return $msg == "" ? "-" : $msg;
    }
	
	public function getAllEnemy($f)
    {
        $msg = "";
        foreach ($f["data"]["enemy"] as $a => $v) {
            $msg .= $a . " ";
        }
        return $msg == "" ? "-" : $msg;
    }
	
	public function getCountMembersInFaction($faction){
		if(is_string($faction)){
			$faction = $this->getFactionData($faction);
		}
		return count($faction["data"]["players"]);
	}
	
	public function getMembersOnline($faction){
		if(is_string($faction)){
			$faction = $this->getFactionData($faction);
		}
		$quantidade = 0;
        foreach($faction["data"]["players"] as $p => $v) {
			if($this->getServer()->getPlayer($p) instanceof Player){
				$quantidade++;
			}
        }
		return $quantidade;
	}
	
	public function getMembersOfFaction($faction){
		if(is_string($faction)){
			$faction = $this->getFactionData($faction);
		}
		$players = [];
		foreach($faction["data"]["players"] as $p => $v) {
			$players[] = $p;
        }
		return $players;
	}

    public function getMembersMessage($f)
    {
		if(is_string($f)){
			$f = $this->getFactionData($f);
		}
        $nicks = [];
		$items = 0;
		$linha = 1;
        $vs = ["leader" => "§6[Lider]", "officer" => "§b[Capitao]", "member" => "§7[Membro]"];
		$msg = "";
        foreach ($f["data"]["players"] as $p => $v) {
			if($items === 2){
				$items = 0;
				$linha++;
			}
            $cor = $this->getServer()->getPlayer($p) instanceof Player ? "§a" : "§c";
            $nicks[$linha][] = $vs[$v] . " $cor" . $p;
			$items++;
        }
		foreach($nicks as $nameArray){
			foreach($nameArray as $playersName){
				$msg .= $playersName." ";
			}
			$msg .= "\n";
		}
        return $msg;
    }

    public function haveGera($fac): bool
    {
        if ($this->gera->exists($fac)) {
            return count($this->gera->get($fac)) !== 0;
        }
        return false;
    }

    public function addGera($fac, $item)
    {
        $dm = $item->getDamage();
        $c = $this->gera;
        $cc = $c->get($fac);
        $rc = $item->getCount();
        if (isset($cc[$dm])) {
            $rc = $cc[$dm]["count"] + $item->getCount();
        }
        $all = $c->get($fac);
        $all[$dm] = [
            "count" => $rc,
            "item" => $item->jsonSerialize()
        ];
        $c->set($fac, $all);
        $c->save();
        $c->reload();
    }

    public function removeGera($fac, $dm, $p)
    {
        $c = $this->gera;
        $cc = $c->get($fac);
        if (isset($cc[$dm])) {
            $i = Item::jsonDeserialize($cc[$dm]["item"]);
            $i->setCount($cc[$dm]["count"]);
            if ($p->getInventory()->canAddItem($i)) {
                $p->getInventory()->addItem($i);
                unset($cc[$dm]);
                $c->set($fac, $cc);
                $c->save();
                $c->reload();
                return true;
            } else {
                $p->sendMessage("Libere Espaço em seu inventario!");
            }
        }
        return false;
    }

    public function joinInFaction($player, string $faction): bool
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }
        if (!$this->isInFaction($player)) {
            $this->setPlayerData($player, "faction", $faction);
            $all = $this->getFactionData($faction);
            $all["data"]["players"][$player] = "member";
            $this->fdata->set(strtolower($faction), $all);
            $this->fdata->save();
            $this->fdata->reload();
            $this->updateFactionPower($faction);
            $this->onUpdate($faction);
			$this->updateColorsTag($faction);
            return true;
        }
        return false;
    }

    public function tagExists($tag)
    {
        foreach ($this->fdata->getAll() as $f => $a) {
            if ($a["tag"] == $tag) {
                return true;
            }
        }
        return false;
    }

    public function setPermissions($player, array $perms = []): bool
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        } elseif ($player instanceof StringTag) {
            $player = $player->getValue();
        }
        if ($this->isInFaction($player)) {
            $all = $this->getPlayerData($player);
            foreach ($perms as $key => $value) {
                $all["perms"][$key] = $value;

            }
            $this->pdata->set($player, $all);
            $this->pdata->save();
            $this->pdata->reload();
            return true;
        }
        return false;
    }

    public function getFactionTag(string $faction): string
    {
        return $this->getFactionData($faction, "tag");
    }

    public function hasPermission($player, string $permission): bool
    {

        $permissions = $this->getPermissions($player, true);
        return $permissions[$permission] ?? false;
    }

    public function getPermissions($player, bool $force = false): ?array
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        } elseif ($player instanceof StringTag) {
            $player = $player->getValue();
        }
        $default = self::$defaultPermissions;
        $permissions = [];

        if ($this->isInFaction($player)) {
            $result = $this->getPlayerData($player)["perms"];
            foreach ($default as $key => $value) {
                $permissions[$key] = (bool)($result[$key] ?? $value);
            }
            return $permissions;
        }
        return $force ? $default : null;
    }

    public function removeFromFaction($player): bool
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }
        if ($this->isInFaction($player) and !$this->isLeader($player)) {
            $faction = $this->getFaction($player);
            $this->setPlayerData($player, "faction", "false");
            $all = $this->getFactionData($faction);
            unset($all["data"]["players"][$player]);
            $this->fdata->set(strtolower($faction), $all);
            $this->fdata->save();
            $this->fdata->reload();
            $this->onUpdate($faction);

            $this->updateFactionPower($faction);
            if ($this->prefs->get("FactionNametags")) {
                $this->updateTag($player);
            }
			$this->updateColorsTag($faction);
            return true;
        }
        return false;
    }

    public function deleteFaction(string $faction): bool
    {
        if ($this->factionExists($faction)) {
            $faction = strtolower($faction);
            $all = $this->getFactionData($faction);
            foreach ($all["data"]["players"] as $p => $d) {
                $this->setPlayerData($p, "faction", "false");
            }
			# TODO
			# RETIRAR ALIADOS E INIMIGOS
            $this->fdata->remove($faction);
            $this->fdata->save();
            $this->fdata->reload();
			$this->updateColorsTag($faction);
            return true;
        }
        return false;
    }

    public function setEnemies($faction1, $faction2)
    {
        $all = $this->getFactionData($faction1);
        $all["data"]["enemies"][$faction2] = $faction2;
        $this->fdata->set(strtolower($faction1), $all);
        $this->fdata->save();
        $this->fdata->reload();

        $all = $this->getFactionData($faction2);
        $all["data"]["enemies"][$faction1] = $faction1;
        $this->fdata->set(strtolower($faction2), $all);
        $this->fdata->save();
        $this->fdata->reload();
		$this->updateColorsTag($faction1);
    }

    public function isInFaction($player)
    {
        return $this->getPlayerData($player, "faction") !== "false";
    }

    public function getFaction($player)
    {
        return $this->getPlayerData($player, "faction") == "false" ? null : $this->getPlayerData($player, "faction");
    }

    public function setAllies($faction1, $faction2)
    {
        $all = $this->getFactionData($faction1);
        $all["data"]["ally"][$faction2] = $faction2;
        $this->fdata->set(strtolower($faction1), $all);
        $this->fdata->save();
        $this->fdata->reload();
        $this->onUpdate($faction1);
        $all = $this->getFactionData($faction2);
        $all["data"]["ally"][$faction1] = $faction1;
        $this->fdata->set(strtolower($faction2), $all);
        $this->fdata->save();
        $this->fdata->reload();
        $this->onUpdate($faction2);
		$this->updateColorsTag($faction1);
    }

    public function areAllies($faction1, $faction2)
    {
        $all = $this->getFactionData($faction1, "data");
        return isset($all["ally"][$faction2]);
    }

    public function updateAllies($faction)
    {
        $all = $this->getFactionData($faction, "data");
        $this->setFactionData($faction, "ally", count($all["ally"]));
    }
	
	public function getAllies($faction){
		return $this->getFactionData($faction)["data"]["ally"];
	}
	
	public function getAlliesMessage($faction){
		$msg = "";
		$allies = $this->getFactionData($faction)["data"]["ally"];
		if(count($allies) < 1){
			return "§7Nenhum";
		}
		foreach($allies as $ally){
			$msg = "§a".$ally." ";
		}
		return $msg;
	}

    public function getAlliesCount($faction)
    {
        return $this->getFactionData($faction, "ally");
    }

    public function getAlliesLimit()
    {
        return (int)$this->prefs->get("AllyLimitPerFaction");
    }

    public function deleteAllies($faction1, $faction2)
    {
        $all = $this->getFactionData($faction1);
        unset($all["data"]["ally"][$faction2]);
        $this->fdata->set(strtolower($faction1), $all);
        $this->fdata->save();
        $this->fdata->reload();
        $this->onUpdate($faction1);
        $all = $this->getFactionData($faction2);
        unset($all["data"]["ally"][$faction1]);
        $this->fdata->set(strtolower($faction2), $all);
        $this->fdata->save();
        $this->fdata->reload();
        $this->onUpdate($faction2);
		$this->updateColorsTag($faction1);
    }
	
	public function setEnemy($faction1, $faction2)
    {
        $all = $this->getFactionData($faction1);
        $all["data"]["enemy"][$faction2] = $faction2;
        $this->fdata->set(strtolower($faction1), $all);
        $this->fdata->save();
        $this->fdata->reload();
        $this->onUpdate($faction1);
        $all = $this->getFactionData($faction2);
        $all["data"]["enemy"][$faction1] = $faction1;
        $this->fdata->set(strtolower($faction2), $all);
        $this->fdata->save();
        $this->fdata->reload();
        $this->onUpdate($faction2);
		$this->updateColorsTag($faction1);
    }

    public function areEnemies($faction1, $faction2)
    {
        $all = $this->getFactionData($faction1, "data");
        return isset($all["enemy"][$faction2]);
    }

    public function updateEnemies($faction)
    {
        $all = $this->getFactionData($faction, "data");
        $this->setFactionData($faction, "enemy", count($all["enemy"]));
    }

    public function getEnemiesCount($faction)
    {
        return $this->getFactionData($faction, "enemy");
    }
	
	public function getEnemies($faction){
		return $this->getFactionData($faction)["data"]["enemy"];
	}
	
	public function getEnemiesMessage($faction){
		$msg = "";
		$enemies = $this->getFactionData($faction)["data"]["enemy"];
		if(count($enemies) < 1){
			return "§7Nenhum";
		}
		foreach($enemies as $enemy){
			$msg = "§c".$enemy." ";
		}
		return $msg;
	}

    public function getEnemiesLimit()
    {
        return (int)$this->prefs->get("EnemyLimitPerFaction");
    }

    public function deleteEnemy($faction1, $faction2)
    {
        $all = $this->getFactionData($faction1);
        unset($all["data"]["enemy"][$faction2]);
        $this->fdata->set(strtolower($faction1), $all);
        $this->fdata->save();
        $this->fdata->reload();
        $this->onUpdate($faction1);
        $all = $this->getFactionData($faction2);
        unset($all["data"]["enemy"][$faction1]);
        $this->fdata->set(strtolower($faction2), $all);
        $this->fdata->save();
        $this->fdata->reload();
        $this->onUpdate($faction2);
		$this->updateColorsTag($faction1);
    }
	
	public function sendMessageToFaction($faction, $message){
		if($this->factionExists($faction)){
			foreach($this->getServer()->getOnlinePlayers() as $ps){
				if($this->getPlayerFaction($ps->getName()) == $faction){
					$ps->sendMessage($message);
				}
			}
		}
	}

    public function getPlayersFaction(string $faction, bool $online = true, bool $rank = false): array
    {
        if (!$this->factionExists($faction)) return [];
        $players = [];
        foreach ($this->getFactionData($faction)["data"]["players"] as $player => $c) {

            if ($online) {
                if (($pp = $this->getServer()->getPlayerExact($player)) instanceof Player) $players[] = $pp;
            } else {
                $cc = "";
                if ($c == "leader") {
                    $cc = "Lider";
                } elseif ($c == "officer") {
                    $cc = "Capitao";
                } else $cc = "Membro";

                if (!$rank)
                    $players[] = $player;
                else
                    $players[$cc] = $player;
            }
        }
        return $players;
    }

    public function updateFactionPower(string $faction): void
    {
        $power = 0;
        foreach ($this->getPlayersFaction($faction, false) as $player) {
            $power += $this->getPlayerPower($player);
        }
        $this->setFactionPower($faction, $power);
    }

    public function getFactionPower($faction)
    {
        return $this->getFactionData($faction, "power");
    }

    public function setFactionPower($faction, $power)
    {
        if ($power < 0) {
            $power = 0;
        }
        $this->setFactionData($faction, "power", $power);
    }

    public function addFactionPower($faction, $power)
    {
        if ($this->getFactionPower($faction) + $power < 0) {
            $power = $this->getFactionPower($faction);
        }
        $this->setFactionPower($faction, $this->getFactionPower($faction) + $power);
    }

    public function subtractFactionPower($faction, $power)
    {
        if ($this->getFactionPower($faction) - $power < 0) {
            $power = $this->getFactionPower($faction);
        }
        $this->setFactionPower($faction, $this->getFactionPower($faction) - $power);
    }

    public function isLeader($player)
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }
        $faction = $this->getPlayerData($player, "faction");
        return $this->getFactionData($faction, "leader") === $player;
    }

    public function isOfficer($player)
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }
        $faction = $this->getPlayerData($player, "faction");
        return $this->getFactionData($faction, "data")["players"][$player] == "officer";
    }

    public function isMember($player)
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }
        $faction = $this->getPlayerData($player, "faction");
        return $this->getFactionData($faction, "data")["players"][$player] == "member";
    }
	
	public function getPlayerCargo($player){
		if ($player instanceof Player) {
            $player = $player->getName();
        }
        $faction = $this->getPlayerData($player, "faction");
        return $this->getFactionData($faction, "data")["players"][$player];
	}

    public function getPlayersInFactionByRank($s, $faction, $rank)
    {
        return $this->getPlayersFaction($faction, false); //grr
    }

    public function getAllAllies($s, $faction)
    {
        return $this->getAllAlly($this->getFactionData($faction));
    }

    public function getNearbyPlots(Player $player)
    {
        $playerLevel = $player->getLevel()->getName();
        $playerX = $player->getX();
        $playerZ = $player->getZ();
        $maxDistance = $this->prefs->get("MaxMapDistance");
        $result = $this->db->query("SELECT faction, x1, z1, x2, z2 FROM plots WHERE ((x1 + (x2 - x1) / 2) - $playerX) * ((x1 + (x2 - x1) / 2) - $playerX) + ((z1 + (z2 - z1) / 2) - $playerZ) * ((z1 + (z2 - z1) / 2) - $playerZ) <= $maxDistance * $maxDistance AND world = '$playerLevel';");
        $factionPlots = array();
        $i = 0;
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            if (!isset($res['faction'])) continue;
            $factionPlots[$i]['faction'] = $res['faction'];
            $factionPlots[$i]['x1'] = $res['x1'];
            $factionPlots[$i]['x2'] = $res['x2'];
            $factionPlots[$i]['z1'] = $res['z1'];
            $factionPlots[$i]['z2'] = $res['z2'];
            $i++;
        }
        return $factionPlots;
    }

    public function getPlayerFaction($player)
    {
        return $this->getFaction($player);
    }

    public function getLeader($faction)
    {
        return $this->getFactionData($faction, "leader");
    }

    public function factionExists($faction)
    {
        $lowercasefaction = strtolower($faction);
        return $this->fdata->exists($lowercasefaction);
    }

    public function sameFaction($player1, $player2)
    {
        return $this->getPlayerFaction($player1) == $this->getPlayerFaction($player2) and $this->isInFaction($player1);
    }

    public function getNumberOfPlayers($faction)
    {
        return $this->getFactionData($faction, "players");
    }

    public function isFactionFull($faction)
    {
        return $this->getNumberOfPlayers($faction) >= $this->prefs->get("MaxPlayersPerFaction");
    }

    public function isNameBanned($name)
    {
        $bannedNames = file_get_contents($this->getDataFolder() . "BannedNames.txt");
        $isBanned = false;
        if (isset($name) && $this->antispam && $this->antispam->getProfanityFilter()->hasProfanity($name)) $isBanned = true;
        return (strpos(strtolower($bannedNames), strtolower($name)) > 0 || $isBanned);
    }

    public function pointIsInPlot(int $x, int $z, $l)
    {
        return !is_null($this->factionFromPoint($x, $z, $l));
    }

    public function cornerIsInPlot($x1, $z1, $x2, $z2, string $level)
    {
        return ($this->pointIsInPlot($x1, $z1, $level) || $this->pointIsInPlot($x1, $z2, $level) || $this->pointIsInPlot($x2, $z1, $level) || $this->pointIsInPlot($x2, $z2, $level));
    }

    public function formatMessage($string, $confirm = false)
    {
        if ($confirm) {
            return TextFormat::GREEN . "$string";
        } else {
            return TextFormat::YELLOW . "$string";
        }
    }

    public function motdWaiting($player)
    {
        $stmt = $this->db->query("SELECT player FROM motdrcv WHERE player='$player';");
        $array = $stmt->fetchArray(SQLITE3_ASSOC);
        return !empty($array);
    }

    public function getMOTDTime($player)
    {
        $stmt = $this->db->query("SELECT timestamp FROM motdrcv WHERE player='$player';");
        $array = $stmt->fetchArray(SQLITE3_ASSOC);
        return $array['timestamp'];
    }

    public function setMOTD($faction, $player, $msg)
    {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO motd (faction, message) VALUES (:faction, :message);");
        $stmt->bindValue(":faction", $faction);
        $stmt->bindValue(":message", $msg);
        $result = $stmt->execute();

        $this->db->query("DELETE FROM motdrcv WHERE player='$player';");
    }

    public function updateTag($playername)
    {
        if (!isset($this->purechat)) {
            $this->purechat = $this->getServer()->getPluginManager()->getPlugin("PureChat");
        }
        $p = $this->getServer()->getPlayerExact($playername);
        if ($p === null) {
            return;
        }
        $f = $this->getPlayerFaction($playername);
        if (!$this->isInFaction($playername)) {
            if (isset($this->purechat)) {
                $levelName = $this->purechat->getConfig()->get("enable-multiworld-chat") ? $p->getLevel()->getName() : null;
                $nameTag = $this->purechat->getNametag($p, $levelName);
                $p->setNameTag($nameTag);
            } else {
                $p->setNameTag(TextFormat::ITALIC . TextFormat::YELLOW . "<$playername>");
            }
        } elseif (isset($this->purechat)) {
            $levelName = $this->purechat->getConfig()->get("enable-multiworld-chat") ? $p->getLevel()->getName() : null;
            $nameTag = $this->purechat->getNametag($p, $levelName);
            $p->setNameTag($nameTag);
        } else {
            $p->setNameTag(TextFormat::ITALIC . TextFormat::GOLD . "<$f> " .
                TextFormat::ITALIC . TextFormat::YELLOW . "<$playername>");
        }
    }
	
	public function getPermissionStatus(string $menuId, string $option, string $faction){
		$factionData = $this->getFactionData($faction);
		if($menuId == "menu-permissão-aliado"){
			return $factionData["permissions"]["ally"][$option];
		}
	}
	
	public function setPermissionStatus(string $menuId, string $option, bool $status, string $faction){
		$factionData = $this->getFactionData($faction);
		if($menuId == "menu-permissão-aliado"){
			$factionData["permissions"]["ally"][$option] = $status;
		}
		$this->fdata->set(strtolower($faction), $factionData);
		$this->fdata->save();
		$this->fdata->reload();
	}

    public function sendSB(Player $p)
    {

    }

    public function onDisable()
    {
        if (isset($this->db)) $this->db->close();
    }
}