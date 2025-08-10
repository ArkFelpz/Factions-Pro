<?php

namespace FactionsPro;

use pocketmine\block\Block;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\item\Bucket;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntityExplodeEvent;

use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\inventory\InventoryCloseEvent;

use pocketmine\scheduler\Task;

use pocketmine\inventory\Inventory;
use pocketmine\item\Item;

use FactionsPro\inventory\event\WindowEvent;
use FactionsPro\inventory\Window;

use FactionsPro\tasks\AttackTimer;
use FactionsPro\data\DataBase;
use pocketmine\math\Vector3;
use pocketmine\tile\Tile;
use pocketmine\tile\ItemFrame;
use generator\block\MonsterSpawner;
use generator\tile\MobSpawner;
use Heisenburger69\BurgerSpawners\Tiles\MobSpawnerTile;
use pocketmine\item\WrittenBook;

use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\InvMenu;

use Heisenburger69\BurgerSpawners\Utilities\Utils;

class FactionListener implements Listener
{

    private $last_command = [];
    public $plugin;
    public $spawners = [];

    public function __construct(FactionMain $plugin)
    {
        $this->plugin = $plugin;
        $this->main = $plugin;
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
                if (!($tile = $p->getLevel()->getTile($b)) instanceof MobSpawner) {
                    //echo "nao e tile".PHP_EOL;
                    continue;
                }
                $id = $tile->getEntityId();
                $fac = $this->plugin->factionFromPoint($b->getFloorX(), $b->getFloorZ(), $b->getLevel()->getName());
                $data = $this->plugin->getFactionData($fac);
                $sp = isset($data["data"]["spawners"][$id]) ? $data["data"]["spawners"][$id] : ["name" => "--", "count" => 0];
                if ($add)
                    $sp["count"] += 1;
                else $sp["count"] -= 1;

                $data["data"]["spawners"][$id] = $sp;
                $this->plugin->fdata->set(strtolower($fac), $data);
                $this->plugin->fdata->save();
                $this->plugin->fdata->reload();

            }
        }
    }

    public function onSetFrameItem(PlayerInteractEvent $e)
    {
        $p = $e->getPlayer();
        $b = $e->getBlock();
        $i = $e->getItem();
        if ($p->hasPermission("set.frame") and $p->isSneaking()) {
            $tile = $b->getLevel()->getTile($b);
            if ($tile instanceof ItemFrame) {
                $tile->setItem($i);
            }
        }
    }
	
	public function onMove(PlayerMoveEvent $ev){
		$player = $ev->getPlayer();
		if($this->plugin->isInPlot($player)){
			if(isset($this->title[$player->getName()])){
				if($this->title[$player->getName()] == "claim"){
					return true;
				}
			}
			$this->title[$player->getName()] = "claim";
			$player->addTitle("§cCUIDADO", "§r§fÁrea claimada pela facção: ".$this->plugin->factionFromPoint($player->x,$player->z,$player->getLevel()), 20, 60, 20);
		}else unset($this->title[$player->getName()]);
	}

    public function onSpawnerAdd(BlockPlaceEvent $e)
    {
        $p = $e->getPlayer();
        $b = $e->getBlock();
        if ($b->getY() >= 129 and $b->getLevel()->getName() == "Factions") {
            $e->setCancelled();
            $p->sendMessage("§cO Limite de altura do mundo é de 128 blocos!");
        }
        if ($b instanceof MonsterSpawner and !$this->plugin->isInPlot($b)) {
            $e->setCancelled();
            $p->sendMessage("§cVocê deve colocar os spawners em territorios dominados!");
        }
        if ($e->isCancelled() or !$this->plugin->isInPlot($b)) return false;
        if ($b->getId() === 52) {
            $this->plugin->spawners[] = ["time" => 5, "block" => $b, "player" => $p, "add" => true];
        }
    }
	
	public $spawnerPlaced = [];
	
	public function placeSpawner(BlockPlaceEvent $ev){
		$player = $ev->getPlayer();
		$block = $ev->getBlock();
		$item = $ev->getItem();
		if($block->getId() !== 52) return true;
		if(!$this->plugin->isInFaction($player->getName())){
			$player->sendMessage("§cVocê não é membro de uma facção.");
			$ev->setCancelled();
			return true;
		}
		if(!$this->plugin->isInPlot($block)){
			$player->sendMessage("§cO bloco deve estar em uma claim.");
			$ev->setCancelled();
			return true;
		}
		$data = $this->plugin->getFactionData($this->plugin->getPlayerFaction($player->getName()));
		$entityId = $item->getNamedTag()->getInt("EntityID");
		$data["data"]["spn"]["claim"][$block->x.":".$block->y.":".$block->z.":".$block->getLevel()->getName()] = $entityId;
		$this->plugin->fdata->set($this->plugin->getPlayerFaction($player->getName()), $data);
		$this->plugin->fdata->save();
	}
	
	public function breakSpawner(BlockBreakEvent $ev){
		$player = $ev->getPlayer();
		$block = $ev->getBlock();
		if($block->getId() !== 52) return true;
		if(!$this->plugin->isInFaction($player->getName())){
			$player->sendMessage("§cVocê não é membro de uma facção.");
			$ev->setCancelled();
			return true;
		}
		$data = $this->plugin->getFactionData($this->plugin->getPlayerFaction($player->getName()));
		unset($data["data"]["spn"]["claim"][$block->x.":".$block->y.":".$block->z.":".$block->getLevel()->getName()]);
		$this->plugin->fdata->set($this->plugin->getPlayerFaction($player->getName()), $data);
		$this->plugin->fdata->save();
	}

    /**
     * @priority HIGHEST
     */
    public function seta(\pocketmine\event\entity\EntitySpawnEvent $e)
    {
        $en = $e->getEntity();
        $arr = $this->plugin->sp->get("MobsNaWindow");
        if (!isset($arr[$en::NETWORK_ID])) return true;
        if ($this->plugin->factionFromPoint($en->x, $en->z, $en->getLevel()->getName()) !== null) {
            $fac = $this->plugin->factionFromPoint($en->x, $en->z, $en->getLevel()->getName());
            $c = $this->plugin->sets;
            if ($c->exists($fac)) {
                $all = $c->get($fac);
                if (isset($all[$en::NETWORK_ID])) {
                    $ex = explode("_", $all[$en::NETWORK_ID]);
                    $en->teleport(new Vector3((int)$ex[0], (int)$ex[1], (int)$ex[2]));
                }
            }
        } else {
            //$en->close();
        }
    }

    public function teleport(EntityTeleportEvent $event)
    {
        if (!(($player = $event->getEntity()) instanceof Player)) {
            return false;
        }
        if ($player->hasPermission("staff.use")) return true;
        $position = $event->getTo();
        $manager = $this->plugin;

        if (!$player->hasPermission('staff.use') and $manager->pointIsInPlot($position->x, $position->z, $position->isValid() ? $position->level->getName() : $player->level->getName())) {
            if ($manager->factionFromPoint($position->x, $position->z, $position->level->getName()) !== $manager->getPlayerFaction($player->getName())) {
                if (isset($this->last_command[$player->getLowerCaseName()])) {
                    $opts = $this->last_command[$player->getLowerCaseName()];

                    if ($opts[0] === 'home' and (time() - $opts[1]) < 1) {
                        $player->sendMessage($this->plugin->formatMessage("§c* Seu teleporte foi cancelado, pois a área de sua home pertence à uma facção agora!"));
                    }
                }
                $event->setCancelled(true);
                return false;
            }
        }
        return true;
    }

    public function explode(EntityExplodeEvent $event)
    {
        $position = $event->getPosition();
        $manager = $this->plugin;

        if ($manager->pointIsInPlot($position->x, $position->z, $position->level->getName())) {
            $faction = $manager->factionFromPoint($position->x, $position->z, $position->level->getName());

            $players = $this->plugin->getPlayersFaction($faction, true);

            if (count($players) > 0 and !$manager->isInAtack($faction)) {
                foreach ($players as $player) $player->sendMessage($this->plugin->formatMessage("§c* Alerta! Sua facção está sob ataque!"));
            }
            $manager->addAttack($faction);
            $manager->atacks[$manager::pos($position)] = $faction;

        }
    }

    public function onBlockBreak(BlockBreakEvent $e)
    {
        $p = $e->getPlayer();
        $b = $e->getBlock();
        $pl = $this->plugin;
        if ($pl->isInPlot($b)) {
            $fac = $pl->factionFromPoint($b->x, $b->z, $b->getLevel()->getName());
            $facc = $pl->getPlayerFaction($p);
            if ($fac !== $facc) {
                $e->setCancelled();
                $p->sendMessage(TextFormat::colorize("&cEste terreno é de outra Facção."));
            }
            if ($b->getId() == 52) {
                if ($pl->isInAtack($fac)) {
                    $e->setCancelled();
                }
				$data = $this->plugin->getFactionData($this->plugin->getPlayerFaction($p->getName()));
				unset($data["data"]["spn"]["claim"][$b->x.":".$b->y.":".$b->z.":".$b->getLevel()->getName()]);
				$this->plugin->fdata->set($this->plugin->getPlayerFaction($p->getName()), $data);
				$this->plugin->fdata->save();
            }
        }

        if($b->getId() == Block::MELON_BLOCK) {
            $e->setDrops([Item::get(Item::MELON_BLOCK, 0, 1)]);
        }
    }

    public function onBlockPlace(BlockPlaceEvent $e)
    {
        $p = $e->getPlayer();
        $b = $e->getBlock();
        $pl = $this->plugin;
        if ($pl->isInPlot($b)) {
            $fac = $pl->factionFromPoint($b->x, $b->z, $b->getLevel()->getName());
            $facc = $pl->getPlayerFaction($p);
            if ($fac !== $facc) {
                $e->setCancelled();
                $p->sendMessage(TextFormat::colorize("&cEste terreno é de outra facção."));
            }
        }
    }

    public function onInteractPlot(PlayerInteractEvent $e)
    {
        $p = $e->getPlayer();
        $b = $e->getBlock();
        $i = $e->getItem();
        if (in_array($b->getId(), [7, 49, 121]) or $i->getId() == 383 and $b->getId() !== 54) return;
        $pl = $this->plugin;
        if ($pl->isInPlot($b) and $b->getId() == 54) {
            $fac = $pl->factionFromPoint($b->x, $b->z, $b->getLevel()->getName());
            $facc = $pl->getPlayerFaction($p);
            if ($fac !== $facc and !$this->plugin->hasPermission($p, 'staff.use')) {
                $e->setCancelled();
                $p->sendMessage(TextFormat::colorize("&cEste terreno é de outra facção."));
                return;
            }
            // if ( and $b->getId() == 54) {
            //    $e->setCancelled(true);
            //    $p->sendMessage(TextFormat::colorize("&cVocê não tem permissão para abrir baús no terreno da facção."));
            // }
        }

        if($i->getId() === Item::NETHER_STAR && $i->getCustomName() == "§r+1 Poder") {
            $pl->setPlayerPower($p, $pl->getPlayerPower($p) + 1);
            $p->sendMessage("§a+1 de poder adicionado.");

            if($i->getCount() > 1) {
                $i->setCount($i->getCount() - 1);
                $p->getInventory()->setItemInHand($i);
            } else {
                $p->getInventory()->remove($i);
            }
        }

        if(!$pl->iprotector->canEdit($p, $p->asPosition())) {
            if($i->getId() === Item::BUCKET) {
                if($i->getDamage() === 8 or $i->getDamage() === 10) {
                    $e->setCancelled();
                }
            }
        }
    }

    /*public function interact(PlayerInteractEvent $event) {
        $player = $event->getPlayer();

        if($event->getBlock()->getId() === 54) {

            if($this->plugin->isInPlot($player)) {
                if($this->plugin->factionFromPoint($player->getX(), $player->getZ(), $player->level->getName()) !== $this->plugin->getFaction($player->getName())) {
                    $event->setCancelled(true);
                    return false;
                }
            }
            if(!$this->plugin->hasPermission($player, 'chests')) {
                $event->setCancelled(true);
            }
        }
    }
*/
    public function onCommandPreProcess(PlayerCommandPreprocessEvent $event)
    {
        $player = $event->getPlayer();
        $message = $event->getMessage();
        if ($player->hasPermission("staff.use")) return true;
        if (substr($message, 0, 1) !== "/") {
            return false;
        }
        $args = explode(" ", substr($message, 1));
        $command = array_shift($args);

        if (!in_array($command, ['home', 'sethome', 'tpa', 'tpaccept'])) {
            return false;
        }

        $this->last_command[$player->getLowerCaseName()] = [$command, time()];

        $isInPlot = false;
        $isOwnPlot = false;
        $faction = '~';

        $manager = $this->plugin;

        if ($manager->isInPlot($player)) {
            if ($manager->isInFaction($player->getName())) {
                $faction = $manager->getFaction($player->getName());
            }
            $isInPlot = true;

            if ($manager->factionFromPoint($player->x, $player->z, $player->level->getName()) === $faction) {
                $isOwnPlot = true;
            }
        }

        if ($isInPlot) {
            if (in_array($command, ['home', 'sethome', 'tpa', 'tpahere', 'tpaccept'])) {
                if (!$isOwnPlot) {
                    $player->sendMessage($this->plugin->formatMessage("§c* Você não pode usar este comando no terreno de outra facção!"));
                    $event->setCancelled();
                    return;
                    /*if(!$this->plugin->hasPermission($player, 'home')) {
                        $player->sendMessage($this->plugin->formatMessage("§c* Você foi bloqueado de usar este comando no terreno de sua facção!"));
                        $event->setCancelled(true);
                    }*/
                }

                if ($command === 'sethome') {

                    if (!$isOwnPlot) {
                        $player->sendMessage($this->plugin->formatMessage("§c* Você não pode usar este comando no terreno de outra facção!"));
                        $event->setCancelled();
                        return;
                    }
                    //$player->sendMessage($this->plugin->formatMessage("§c* Você não pode usar este comando no terreno de outras facção!"));
                    //$event->setCancelled(true);
                }
                if (in_array($command, ['tpa', 'tpaccept'])) {

                    if (!$isOwnPlot) {
                        $player->sendMessage($this->plugin->formatMessage("§c* Você não pode usar este comando no terreno de outra facção!"));
                        $event->setCancelled();
                        return;
                    }

                    /*if($isOwnPlot and !$this->plugin->hasPermission($player, 'tpa')) {
                        $player->sendMessage($this->plugin->formatMessage("§c* Você foi bloqueado de usar este comando no terreno de sua facção!"));
                        $event->setCancelled(true);
                    }*/
                }
            }
        }
    }

    public function damageFlight(EntityDamageEvent $event)
    {

        if (($victim = $event->getEntity()) instanceof Player) {
            $damager = null;

            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
            }

            if ($damager instanceof Player and isset($this->plugin->factionFly[$damager->getName()]) and $damager->isSurvival() and $damager->getAllowFlight() === true) {
                $damager->setAllowFlight(false);
                $damager->setFlying(false);
            }
            if ($victim->isSurvival() and isset($this->plugin->factionFly[$victim->getName()]) and $victim->getAllowFlight() === true) {
                $victim->setAllowFlight(false);
                $victim->setFlying(false);
            }
        }
    }

    public function flight(PlayerMoveEvent $event)
    {
        $player = $event->getPlayer();
        if ($event->getFrom()->distance($event->getTo()) < 0.05) {
            return false;
        }
        if (isset($this->plugin->factionFly[$player->getName()])) {
            if (!$player->isSurvival()) {
                return false;
            }
            $fly = false;

            if ($this->plugin->isInPlot($player)) {
                $faction = $this->plugin->isInFaction($player->getName()) ? $this->plugin->getFaction($player->getName()) : '~';

                if ($this->plugin->factionFromPoint($player->getX(), $player->getZ(), $player->level->getName()) === $faction) {
                    $fly = $this->plugin->isInAtack($faction) === false;

                    if (($combat = $this->plugin->combat) !== null) {
                        $fly = $fly and !$combat->isTagged($player);
                    }
                }
            }
            if ($fly === true) {
                if ($player->getAllowFlight() === false) {
                    $player->setAllowFlight(true);
                    $player->setFlying(true);
                }
            } else {
                if ($player->getAllowFlight() === true) {
                    $player->setAllowFlight(false);
                    $player->setFlying(false);
                }
            }
        } elseif ($player->isSurvival() and $player->getAllowFlight() === true) {
            $player->setAllowFlight(false);
            $player->setFlying(false);
        }
    }
	
	public function onInventory(InventoryTransactionEvent $ev){
		$transaction = $ev->getTransaction();
		$player = $transaction->getSource();
		$factionName = $this->plugin->getPlayerFaction($player->getName());
		#var_dump($factionName);
		if(!isset($this->plugin->inventories[$player->getName()])) return;
		$inv = $this->plugin->inventories[$player->getName()][0];

		/////////////////////////////// MENU PRINCIPAL SESSION ///////////////////////////////
        
		if($this->plugin->inventories[$player->getName()][1] == "menu-principal"){
			$ev->setCancelled();
			foreach($transaction->getActions() as $action){
				$item = $action->getTargetItem();
				if($item->getId() == 397 && $item->getDamage() == 3 && $item->getCustomName() == "§f".$player->getName()){
					
				}
				if($item->getId() == 397 && $item->getDamage() == 1 && $item->getCustomName() == "§r§eRanking de Facções"){
					$this->plugin->inventories[$player->getName()][1] = "menu-topgeral";
					$inv->clearAll();
					$inv->setItem(22, Item::get(399, 0, 1)->setCustomName("§r§aRanking de valor")->setLore(["§r§7veja as facções com", "§r§7mais valor no servidor"]));
				}
				if($item->getId() == 397 && $item->getDamage() == 5 && $item->getCustomName() == "§r§eAjuda"){
					$this->plugin->getServer()->dispatchCommand($player, "f ajuda");
                }
                if($item->getId() == 197 && $item->getDamage() == 0 && $item->getCustomName() == "§r§cDesfazer"){
					$this->plugin->getServer()->dispatchCommand($player, "f deletar");
                }
				if($item->getId() == 315 && $item->getDamage() == 0 && $item->getCustomName() == "§r§aUpgrades"){
					$this->plugin->inventories[$player->getName()][1] = "menu-upgrades";
					$inv->clearAll();
					$inv->setItem(21, Item::get(175, 0, 1)->setCustomName("§r§eMoedas")->setLore(["§r§7Sua facção possui (getMoedas) moedas no banco"]));
					$inv->setItem(23, Item::get(315, 0, 1)->setCustomName("§r§eUpgrades")->setLore(["§r§7Clique para visualizar os upgrades já conquistados, ou", "§r§7para comprar novos upgrades."]));
					$inv->setItem(53, Item::get(331, 0, 1)->setCustomName("§r§cRemoção de moedas")->setLore(["§r§7Ao clicar neste item é possivel fazer a", "§r§7remoção das moedas armazenadas. Porém,", "§r§7será cobrado uma taxa de 30% das moedas.", "", "§r§7Minimo de moedas para utilizar a função:", "§r§7->§f50 moedas", "", "§r§4OBS: §fAs moedas serão dropadas separadamente."]));
				}
                if($item->getId() == 270 && $item->getDamage() == 0 && $item->getCustomName() == "§r§aPermissões"){
					$this->plugin->inventories[$player->getName()][1] = "menu-permissão";
					$inv->clearAll();
					$inv->setItem(21, Item::get(397, 3, 1)->setCustomName("§r§aMembros")->setLore(["§r§7Gerencie as permissões que", "§r§7cada membro de sua facção possue", "§r§7em terras da sua facção"]));
                    $inv->setItem(23, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§aAliados")->setLore(["§r§7Gerencie as permissões que", "§r§7os seu aliados possuem em", "§r§7em terras da sua facção"]));
                }
				if($item->getId() == 52 && $item->getDamage() == 0 && $item->getCustomName() == "§r§eGeradores"){
					$this->plugin->inventories[$player->getName()][1] = "menu-geradores";
					$inv->clearAll();
					$inv->setItem(10, Item::get(262, 0, 1)->setCustomName("§r§aVoltar"));
					$inv->setItem(12, Item::get(54, 0, 1)->setCustomName("§r§aGerenciar")->setLore(["", "§r§7Clique para gerenciar", "", "§r§7Os geradores em massa."]));
					$inv->setItem(13, Item::get(395, 0, 1)->setCustomName("§r§aInformações")->setLore(["", "§r§7Quantidade atual de geradores: §fcolocar os number count", "", "§r§7Iron golem: 1"]));
					$inv->setItem(14, Item::get(340, 0, 1)->setCustomName("§r§aUltimatas edições")->setLore(["", "§r§721/08/2021 ás 10:58 ArkFelpz", "", "§r§7Clique para ver o histórico de edições detalhado."]));
                }
                if($item->getId() == 397 && $item->getDamage() == 0 && $item->getCustomName() == "§r§aMembros"){
					$this->plugin->inventories[$player->getName()][1] = "menu-membros";
					$inv->clearAll();
					$inv->setItem(11, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Lider")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
                    $inv->setItem(12, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(13, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(14, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(15, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
                    $inv->setItem(20, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(21, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(22, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(23, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(24, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(29, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(30, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(31, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(32, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
					$inv->setItem(33, Item::get(397, 3, 1)->setCustomName("§r§7Nome do Membro")->setLore(["§r§fPoder: §75/5", "§r§fCargo: §7#Lider", "§r§fKDR: §70.0", "§r§fAbates:", "§r§fMortes:", "§r§fStatus: §aOnline§r§7/§cOffline"]));  
                    $inv->setItem(49, Item::get(262, 0, 1)->setCustomName("§r§aVoltar"));

              }
			}
		}
		/////////////////////////////// TOP GERAL SESSION ///////////////////////////////
        
		if($this->plugin->inventories[$player->getName()][1] == "menu-topgeral"){
			$ev->setCancelled();
			foreach($transaction->getActions() as $action){
				$item = $action->getTargetItem();
				if($item->getId() == 399 && $item->getDamage() == 0 && $item->getCustomName() == "§r§aRanking de valor"){
                    $this->plugin->inventories[$player->getName()][1] = "menu-topgeral";
                    $inv->clearAll();
                    $inv->setItem(10, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f1, §8[TCY] TheCrazy")->setLore(["§r§fValor da facção: §60", "", "§r§fTotal em Coins: §70", "§r§fTotal em geradores: §70", "§r§f● Colocados: §70", "§r§f● Armazanados: §70"]));
                    $inv->setItem(11, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f2, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(12, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f3, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(13, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f4, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(14, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f5, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(15, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f6, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(16, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f7, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(19, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f8, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(20, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f9, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(21, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f10, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(22, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f11, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(23, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f12, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(24, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f13, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(25, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f14, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(28, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f15, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(29, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f16, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(30, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f17, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(31, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f18, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(32, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f19, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(33, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f20, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(34, Item::get(ITEM::BANNER, 15, 1)->setCustomName("§r§f21, §8[TAG DA FACÇÃO] Nome da facção")->setLore(["§r§fValor da facção: §6Total em coins juntando mobspawners e money de toda facção", "", "§r§fTotal em Coins: §7Money de todos os membros", "§r§fTotal em geradores: §7Tanto os geradores colocados como os armazenados", "§r§f● Colocados: §7Total em geradores colocados dentro do claim", "§r§f● Armazanados: §7Geradores armazenados"]));
                    $inv->setItem(26, Item::get(262, 0, 1)->setCustomName("§r§aPágina 2"));
                    $inv->setItem(49, Item::get(262, 0, 1)->setCustomName("§r§aVoltar"));
				}
			}
		}
        


		/////////////////////////////// UPGRADES SESSION ///////////////////////////////

		if($this->plugin->inventories[$player->getName()][1] == "menu-upgrades"){
			$ev->setCancelled();
			foreach($transaction->getActions() as $action){
				$item = $action->getTargetItem();
				if($item->getId() == 315 && $item->getDamage() == 0 && $item->getCustomName() == "§r§eUpgrades"){
                    $this->plugin->inventories[$player->getName()][1] = "menu-upgrades-upgrade";
                    $inv->clearAll();
					$inv->setItem(0, Item::get(160, 14, 1)->setCustomName("§r§aPermissão de voo")->setLore(["§r§7Level I", "", "§r§7Este upgrade permite que membros sem §cVIP", "§r§7possam utilizar o /f voar no claim da facção.", "", "§r§fCusto: §6100 Moedas§f."]));
					$inv->setItem(9, Item::get(160, 14, 1)->setCustomName("§r§aBônus Drop")->setLore(["§r§7Level I", "", "§r§7Este upgrade permite que os bônus drop", "§r§7dos spawners de sua facção seja aumentado", "§r§7em x1,25.", "", "§r§fCusto: §6100 Moedas§f."]));
					$inv->setItem(10, Item::get(160, 14, 1)->setCustomName("§r§aBônus Drop")->setLore(["§r§7Level II", "", "§r§7Este upgrade permite que os bônus drop", "§r§7dos spawners de sua facção seja aumentado", "§r§7em x1,50.", "", "§r§fCusto: §6200 Moedas§f."]));
					$inv->setItem(18, Item::get(160, 14, 1)->setCustomName("§r§aLimite de Terreno temporário")->setLore(["§r§7Level I", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7adquirir uma maior quantia de terreno temporário.", "§r§7Com este upgrade sua facção possuirá a quantia de §f1", "§r§7terreno(s) temporario(s).", "", "§r§fCusto: §620 Moedas§f."]));
					$inv->setItem(19, Item::get(160, 14, 1)->setCustomName("§r§aLimite de Terreno temporário")->setLore(["§r§7Level II", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7adquirir uma maior quantia de terreno temporário.", "§r§7Com este upgrade sua facção possuirá a quantia de §f2", "§r§7terreno(s) temporario(s).", "", "§r§fCusto: §650 Moedas§f."]));
					$inv->setItem(20, Item::get(160, 14, 1)->setCustomName("§r§aLimite de Terreno temporário")->setLore(["§r§7Level III", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7adquirir uma maior quantia de terreno temporário.", "§r§7Com este upgrade sua facção possuirá a quantia de §f3", "§r§7terreno(s) temporario(s).", "", "§r§fCusto: §6180 Moedas§f."]));
					$inv->setItem(27, Item::get(160, 14, 1)->setCustomName("§r§aTempo de Ataque")->setLore(["§r§7Level I", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7ficar em ataque por menos tempo", "§r§7Com este upgrade cada ataque demorará §f4m 30s.", "", "§r§fCusto: §625 Moedas§f."]));
					$inv->setItem(28, Item::get(160, 14, 1)->setCustomName("§r§aTempo de Ataque")->setLore(["§r§7Level II", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7ficar em ataque por menos tempo", "§r§7Com este upgrade cada ataque demorará §f4m.", "", "§r§fCusto: §660 Moedas§f."]));
					$inv->setItem(29, Item::get(160, 14, 1)->setCustomName("§r§aTempo de Ataque")->setLore(["§r§7Level III", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7ficar em ataque por menos tempo", "§r§7Com este upgrade cada ataque demorará §f3m 30s.", "", "§r§fCusto: §6100 Moedas§f."]));
					$inv->setItem(30, Item::get(160, 14, 1)->setCustomName("§r§aTempo de Ataque")->setLore(["§r§7Level IV", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7ficar em ataque por menos tempo", "§r§7Com este upgrade cada ataque demorará §f3m.", "", "§r§fCusto: §6200 Moedas§f."]));
					$inv->setItem(36, Item::get(160, 14, 1)->setCustomName("§r§aPoder Bônus")->setLore(["§r§7Level I", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7ter um poder que nunca será perdido", "§r§7Com este upgrade sua facção terá", "§r§7um bõnus de §f1 de poder.", "", "§r§fCusto: §610 Moedas§f."]));
					$inv->setItem(37, Item::get(160, 14, 1)->setCustomName("§r§aPoder Bônus")->setLore(["§r§7Level II", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7ter um poder que nunca será perdido", "§r§7Com este upgrade sua facção terá", "§r§7um bõnus de §f2 de poder.", "", "§r§fCusto: §630 Moedas§f."]));
					$inv->setItem(38, Item::get(160, 14, 1)->setCustomName("§r§aPoder Bônus")->setLore(["§r§7Level III", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7ter um poder que nunca será perdido", "§r§7Com este upgrade sua facção terá", "§r§7um bõnus de §f5 de poder.", "", "§r§fCusto: §6100 Moedas§f."]));
					$inv->setItem(39, Item::get(160, 14, 1)->setCustomName("§r§aPoder Bônus")->setLore(["§r§7Level IV", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7ter um poder que nunca será perdido", "§r§7Com este upgrade sua facção terá", "§r§7um bõnus de §f10 de poder.", "", "§r§fCusto: §6250 Moedas§f."]));
					$inv->setItem(40, Item::get(160, 14, 1)->setCustomName("§r§aPoder Bônus")->setLore(["§r§7Level V", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7ter um poder que nunca será perdido", "§r§7Com este upgrade sua facção terá", "§r§7um bõnus de §f20 de poder.", "", "§r§fCusto: §6500 Moedas§f."]));
					$inv->setItem(45, Item::get(160, 14, 1)->setCustomName("§r§aLiberação de spawn de mob")->setLore(["§r§7Level I", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7definir o spawn de novos tipos", "§r§7de spawner em seu claim", "§r§7Com este upgrade sua facção conseguirá", "§r§7spawnar o spawner de §fAranha da Caverna.", "", "§r§fCusto: §640 Moedas§f."]));
					$inv->setItem(46, Item::get(160, 14, 1)->setCustomName("§r§aLiberação de spawn de mob")->setLore(["§r§7Level II", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7definir o spawn de novos tipos", "§r§7de spawner em seu claim", "§r§7Com este upgrade sua facção conseguirá", "§r§7spawnar o spawner de §fEsqueleto.", "", "§r§fCusto: §670 Moedas§f."]));
					$inv->setItem(47, Item::get(160, 14, 1)->setCustomName("§r§aLiberação de spawn de mob")->setLore(["§r§7Level III", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7definir o spawn de novos tipos", "§r§7de spawner em seu claim", "§r§7Com este upgrade sua facção conseguirá", "§r§7spawnar o spawner de §fPorco Zumbi.", "", "§r§fCusto: §6100 Moedas§f."]));
					$inv->setItem(48, Item::get(160, 14, 1)->setCustomName("§r§aLiberação de spawn de mob")->setLore(["§r§7Level IV", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7definir o spawn de novos tipos", "§r§7de spawner em seu claim", "§r§7Com este upgrade sua facção conseguirá", "§r§7spawnar o spawner de §fSlime.", "", "§r§fCusto: §6200 Moedas§f."]));
					$inv->setItem(49, Item::get(160, 14, 1)->setCustomName("§r§aLiberação de spawn de mob")->setLore(["§r§7Level V", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7definir o spawn de novos tipos", "§r§7de spawner em seu claim", "§r§7Com este upgrade sua facção conseguirá", "§r§7spawnar o spawner de §fBlaze.", "", "§r§fCusto: §6250 Moedas§f."]));
					$inv->setItem(50, Item::get(160, 14, 1)->setCustomName("§r§aLiberação de spawn de mob")->setLore(["§r§7Level VI", "", "§r§7Este upgrade permite que sua facção consiga", "§r§7definir o spawn de novos tipos", "§r§7de spawner em seu claim", "§r§7Com este upgrade sua facção conseguirá", "§r§7spawnar o spawner de §fIron Golem.", "",  "§r§fCusto: §6300 Moedas§f."]));  
                    $inv->setItem(53, Item::get(262, 0, 1)->setCustomName("§r§aPágina 2"));
                    

                }
			}
		}
        
		if($this->plugin->inventories[$player->getName()][1] == "menu-upgrades-upgrade"){
			$ev->setCancelled();
        }
  		/////////////////////////////// PERMISSIONS SESSION ///////////////////////////////

		if($this->plugin->inventories[$player->getName()][1] == "menu-permissão"){
			$ev->setCancelled();
			foreach($transaction->getActions() as $action){
				$item = $action->getTargetItem();
				if($item->getId() == ITEM::BANNER && $item->getDamage() == 15 && $item->getCustomName() == "§r§aAliados"){
                    $this->plugin->inventories[$player->getName()][1] = "menu-permissão-aliado";
                    $inv->clearAll();
					$liberarTpItem = Item::get(368, 0, 1);
					$liberarHomesItem = Item::get(110, 0, 1);
					if($this->plugin->getPermissionStatus($this->plugin->inventories[$player->getName()][1], "teleport", $factionName)){
						$liberarTpItem->setCustomName("§r§aAceitar pedidos de Teletransporte")->setLore(["§r§7Permite que o jogador aceite os pedidos de", "§r§7teletransporte de jogadores que não façam", "§r§7parte de sua facção dentro de suas terras", "", "§r§7Estado: §r§aLiberado"]);
						$liberarTpItem = $this->plugin->setEnchTag($liberarTpItem);
					}else{
						$liberarTpItem->setCustomName("§r§aAceitar pedidos de Teletransporte")->setLore(["§r§7Permite que o jogador aceite os pedidos de", "§r§7teletransporte de jogadores que não façam", "§r§7parte de sua facção dentro de suas terras", "", "§r§7Estado: §r§cNão Liberado"]);
					}
					if($this->plugin->getPermissionStatus($this->plugin->inventories[$player->getName()][1], "home-on-claim", $factionName)){
						$liberarHomesItem->setCustomName("§r§aHomes na facção")->setLore(["§r§7Permite o jogador se teletransportar para homes", "§r§7criadas nas terras de sua facção. Também permite", "§r§7que o jogador defina novas homes em suas terras.", "", "§r§7Estado: §r§aLiberado"]);
						$liberarHomesItem = $this->plugin->setEnchTag($liberarHomesItem);
					}else{
						$liberarHomesItem->setCustomName("§r§aHomes na facção")->setLore(["§r§7Permite o jogador se teletransportar para homes", "§r§7criadas nas terras de sua facção. Também permite", "§r§7que o jogador defina novas homes em suas terras.", "", "§r§7Estado: §r§cNão Liberado"]);
					}
                    $inv->setItem(21, $liberarTpItem);
                    $inv->setItem(23, $liberarHomesItem);
				}
				if($item->getId() == 397 && $item->getDamage() == 3 && $item->getCustomName() == "§r§aMembros"){
                    $this->plugin->inventories[$player->getName()][1] = "menu-permissão-membros";
                    $inv->clearAll();
                }
			}
		}
          		
        /////////////////////////////// PERMISSIONS ALLYS ///////////////////////////////

		if($this->plugin->inventories[$player->getName()][1] == "menu-permissão-aliado"){
			$ev->setCancelled();
			foreach($transaction->getActions() as $action){
				$item = $action->getTargetItem();
				if($item->getId() == 368 && $item->getCustomName() == "§r§aAceitar pedidos de Teletransporte"){
					$this->plugin->setPermissionStatus($this->plugin->inventories[$player->getName()][1], "teleport", !$this->plugin->getPermissionStatus($this->plugin->inventories[$player->getName()][1], "teleport", $factionName), $factionName);
					$liberarTpItem = Item::get(368, 0, 1);
					if($this->plugin->getPermissionStatus($this->plugin->inventories[$player->getName()][1], "teleport", $factionName)){
						$liberarTpItem->setCustomName("§r§aAceitar pedidos de Teletransporte")->setLore(["§r§7Permite que o jogador aceite os pedidos de", "§r§7teletransporte de jogadores que não façam", "§r§7parte de sua facção dentro de suas terras", "", "§r§7Estado: §r§aLiberado"]);
						$liberarTpItem = $this->plugin->setEnchTag($liberarTpItem);
					}else{
						$liberarTpItem->setCustomName("§r§aAceitar pedidos de Teletransporte")->setLore(["§r§7Permite que o jogador aceite os pedidos de", "§r§7teletransporte de jogadores que não façam", "§r§7parte de sua facção dentro de suas terras", "", "§r§7Estado: §r§cNão Liberado"]);
					}
					$inv->setItem(21, $liberarTpItem);
				}
				if($item->getId() == 110 && $item->getCustomName() == "§r§aHomes na facção"){
					$this->plugin->setPermissionStatus($this->plugin->inventories[$player->getName()][1], "home-on-claim", !$this->plugin->getPermissionStatus($this->plugin->inventories[$player->getName()][1], "home-on-claim", $factionName), $factionName);
					$liberarHomesItem = Item::get(110, 0, 1);
					if($this->plugin->getPermissionStatus($this->plugin->inventories[$player->getName()][1], "home-on-claim", $factionName)){
						$liberarHomesItem->setCustomName("§r§aHomes na facção")->setLore(["§r§7Permite o jogador se teletransportar para homes", "§r§7criadas nas terras de sua facção. Também permite", "§r§7que o jogador defina novas homes em suas terras.", "", "§r§7Estado: §r§aLiberado"]);
						$liberarHomesItem = $this->plugin->setEnchTag($liberarHomesItem);
					}else{
						$liberarHomesItem->setCustomName("§r§aHomes na facção")->setLore(["§r§7Permite o jogador se teletransportar para homes", "§r§7criadas nas terras de sua facção. Também permite", "§r§7que o jogador defina novas homes em suas terras.", "", "§r§7Estado: §r§cNão Liberado"]);
					}
					$inv->setItem(23, $liberarHomesItem);
				}
			}
		}

                  		/////////////////////////////// PERMISSIONS MEMBERS ///////////////////////////////


		if($this->plugin->inventories[$player->getName()][1] == "menu-permissão-membros"){
			$ev->setCancelled();
			foreach($transaction->getActions() as $action){
				$item = $action->getTargetItem();
				if($item->getId() == 397 && $item->getDamage() == 3 && $item->getCustomName() == "§r§aMembros"){
                    $this->plugin->inventories[$player->getName()][1] = "menu-permissão-membros";
                    $inv->clearAll();
					$inv->setItem(11, Item::get(397, 3, 1)->setCustomName("§r§7Vago")->setLore(["§r§aCaso tenha algum membro"]));

				}
			}
		}
                         		/////////////////////////////// MEMBERS ///////////////////////////////


		if($this->plugin->inventories[$player->getName()][1] == "menu-membros"){
			$ev->setCancelled();
			foreach($transaction->getActions() as $action){
				$item = $action->getTargetItem();
				if($item->getId() == 397 && $item->getDamage() == 3 && $item->getCustomName() == "§r§eMembros"){
                    $this->plugin->inventories[$player->getName()][1] = "menu-membros";

				}
			}
		}
		
		/////////////////////////////// GENERATOR SESSION ///////////////////////////////
		if($this->plugin->inventories[$player->getName()][1] == "menu-geradores"){
			$ev->setCancelled();
			foreach($transaction->getActions() as $action){
				$item = $action->getTargetItem();
				if($item->getId() == 54 && $item->getCustomName() == "§r§aGerenciar"){
					$inv->clearAll();
					$this->plugin->inventories[$player->getName()][1] = "menu-geradores-gerenciar";
					$inv->setItem(11, Item::get(52, 0, 1)->setCustomName("§r§aGerenciar inventário")->setLore(["§r§7Gerencie os geradores do", "§r§7seu inventário em massa"]));
					$inv->setItem(20, Item::get(408, 0, 1)->setCustomName("§r§aArmazenar todos (inv)")->setLore(["§r§7Clique para armazenar todos os", "§r§7geradores do seu inventário"]));
					$inv->setItem(29, Item::get(408, 0, 1)->setCustomName("§r§aColetar todos (inv)")->setLore(["§r§7Clique para coletar todos os", "§r§7geradores armazenados"]));
					$inv->setItem(15, Item::get(52, 0, 1)->setCustomName("§r§aGerenciar terrenos")->setLore(["§r§7Gerencie os geradores dos", "§r§7seu terrenos em massa"]));
					$inv->setItem(24, Item::get(408, 0, 1)->setCustomName("§r§aArmazenar todos (claim)")->setLore(["§r§7Clique para armazenar todos os", "§r§7geradores dos seu terrenos"]));
					$inv->setItem(33, Item::get(408, 0, 1)->setCustomName("§r§aRecolocar todos (claim)")->setLore(["§r§7Clique para recolocar todos os", "§r§7geradores armazenados"]));
				}
			}
			
		}
		if($this->plugin->inventories[$player->getName()][1] == "menu-geradores-gerenciar"){
			$ev->setCancelled();
			$factionName = $this->plugin->getPlayerFaction($player->getName());
			foreach($transaction->getActions() as $action){
				$item = $action->getTargetItem();
				if($item->getId() == 408 && $item->getCustomName() == "§r§aArmazenar todos (inv)"){
					if($this->plugin->isInAtack($factionName)){
						$player->sendMessage("§cSua facção está em ataque. Você não pode utilizar isso no momento!");
						return;
					}
					$this->plugin->saveSpawnersOnInventory($player);
				}
				if($item->getId() == 408 && $item->getCustomName() == "§r§aColetar todos (inv)"){
					if($this->plugin->isInAtack($factionName)){
						$player->sendMessage("§cSua facção está em ataque. Você não pode utilizar isso no momento!");
						return;
					}
					$this->plugin->getSpawnersOfInventory($player);
				}
				if($item->getId() == 408 && $item->getCustomName() == "§r§aArmazenar todos (claim)"){
					if($this->plugin->isInAtack($factionName)){
						$player->sendMessage("§cSua facção está em ataque. Você não pode utilizar isso no momento!");
						return;
					}
					$this->plugin->saveSpawnersFromClaim($factionName);
					$player->sendMessage("§aVocê armazenou todos os spawners do seu claim.");
				}
				if($item->getId() == 408 && $item->getCustomName() == "§r§aRecolocar todos (claim)"){
					if($this->plugin->isInAtack($factionName)){
						$player->sendMessage("§cSua facção está em ataque. Você não pode utilizar isso no momento!");
						return;
					}
					$this->plugin->placeSpawnersOnClaim($factionName);
					$player->sendMessage("§aVocê recolocou todos os spawners do seu claim.");
				}
			}
		}
	}
	
	public function onClose(InventoryCloseEvent $ev){
		$player = $ev->getPlayer();
		if(isset($this->plugin->inventories[$player->getName()])){
			unset($this->plugin->inventories[$player->getName()]);
		}
	}
	
	/*public function createChest($menuInfos, $inventoriesMenuName, $player){
		$this->plugin->getServer()->broadcastMessage("2");
		$menu = InvMenu::create($menuInfos[0]);
		$inv = $menu->getInventory();
		$this->plugin->inventories[$player->getName()] = [$inv, $inventoriesMenuName];
		$this->plugin->getScheduler()->scheduleDelayedTask(new class($this->plugin, $menu, $player, $menuInfos[1]) extends Task{
			
			public $plugin, $menu, $player, $windowName;
			
			public function __construct($plugin, $menu, $player, $windowName){
				$this->plugin = $plugin;
				$this->menu = $menu;
				$this->player = $player;
				$this->windowName = $windowName;
			}
			
			public function onRun($ticks){
				$this->menu->send($this->player, $this->windowName);
				$this->plugin->getServer()->broadcastMessage("3");
			}
			
		}, 20*2);
	}*/

    /*
        public function data(DataPacketReceiveEvent $event) {
            $source = $event->getPlayer();

            if(($packet = $event->getPacket()) instanceof InventoryTransactionPacket and isset($packet->actions[0])){
                if(($window = Window::getWindow($source)) === null) {
                    return false;
                }
                $name = strtolower($source->getName());

                if(!$this->factionWindow(new WindowEvent($window, $source, $packet->actions, $window->isLastTransaction($name)))) {
                    $event->setCancelled(true);
                    $source->setUsingItem(false);

                    foreach($packet->actions as $transaction) {
                        if(is_int($transaction->windowId)) {
                            if(($inventory = $source->getWindow($transaction->windowId)) instanceof Inventory) {
                                $inventory->sendContents($source);
                            }
                        }
                    }
                    return false;
                }
            }
            return true;
        }
    */

    public function factionChat(PlayerChatEvent $PCE)
    {

        $player = $PCE->getPlayer()->getName();
        // $max = 57344 + 10000;
        // $b = $this->i + 600;

        // $message = "";
        // while ($this->i < $b) {
        // 	@eval('$symbol = "\u{' . dechex($this->i++) . '}";');
        // 	$message .= " | ".$symbol." -> ".dechex($this->i);
        // }
        // $player->sendMessage($message);
        // return;
        //MOTD Check

        if ($this->plugin->motdWaiting($player)) {
            if (time() - $this->plugin->getMOTDTime($player) > 30) {
                $PCE->getPlayer()->sendMessage($this->plugin->formatMessage("§eTempo esgotado. Por favor, use / f desc novamente."));
                $this->plugin->db->query("DELETE FROM motdrcv WHERE player='$player';");
                $PCE->setCancelled(true);
                return true;
            } else {
                $motd = $PCE->getMessage();
                $faction = $this->plugin->getPlayerFaction($player);
                $this->plugin->setMOTD($faction, $player, $motd);
                $PCE->setCancelled(true);
                $PCE->getPlayer()->sendMessage($this->plugin->formatMessage("§aAtualizada com êxito a descrição da facção. Tipo /f info.", true));
            }
            return true;
        }
        if (isset($this->plugin->factionChatActive[$player])) {

            if ($this->plugin->isInFaction($player)) {
                if ($this->plugin->factionChatActive[$player]) {
                    $msg = $PCE->getMessage();
                    $faction = $this->plugin->getPlayerFaction($player);
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $fP) {
                        if ($this->plugin->getPlayerFaction($fP->getName()) == $faction) {
                            if ($this->plugin->getServer()->getPlayer($fP->getName())) {
                                $PCE->setCancelled(true);
                                $this->plugin->getServer()->getPlayer($fP->getName())->sendMessage(TextFormat::YELLOW . "[FAC] $faction" . TextFormat::YELLOW . " $player: " . TextFormat::GRAY . $msg);
                            }
                        }
                    }
                }
            } else {
                unset($this->plugin->factionChatActive[$player]);
            }
        }
        if (isset($this->plugin->allyChatActive[$player])) {
            if ($this->plugin->isInFaction($player)) {
                if ($this->plugin->allyChatActive[$player]) {
                    $msg = $PCE->getMessage();
                    $faction = $this->plugin->getPlayerFaction($player);
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $fP) {
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($fP->getName()), $faction)) {
                            $PCE->setCancelled(true);
                            $fP->sendMessage(TextFormat::YELLOW . "[ALY] $faction" . TextFormat::YELLOW . " $player: " . TextFormat::GRAY . $msg);
                            $PCE->getPlayer()->sendMessage(TextFormat::YELLOW . "[ALY] $faction" . TextFormat::YELLOW . " $player: " . TextFormat::GRAY . $msg);
                        }
                    }
                }
            } else {
                unset($this->plugin->allyChatActive[$player]);
            }
        }
    }

    public function factionPVP(EntityDamageEvent $factionDamage)
    {
        if ($factionDamage instanceof EntityDamageByEntityEvent) {
			$factionDamage->setKnockback(0.3);
            if (!($factionDamage->getEntity() instanceof Player) or !($factionDamage->getDamager() instanceof Player)) {
                return true;
            }
            if (($this->plugin->isInFaction($factionDamage->getEntity()->getPlayer()->getName()) == false) or ($this->plugin->isInFaction($factionDamage->getDamager()->getPlayer()->getName()) == false)) {
                return true;
            }
            if (($factionDamage->getEntity() instanceof Player) and ($factionDamage->getDamager() instanceof Player)) {
                $player1 = $factionDamage->getEntity()->getPlayer()->getName();
                $player2 = $factionDamage->getDamager()->getPlayer()->getName();
                $f1 = $this->plugin->getPlayerFaction($player1);
                $f2 = $this->plugin->getPlayerFaction($player2);
                if ((!$this->plugin->prefs->get("AllowFactionPvp") && $this->plugin->sameFaction($player1, $player2) == true) or (!$this->plugin->prefs->get("AllowAlliedPvp") && $this->plugin->areAllies($f1, $f2))) {
                    $factionDamage->setCancelled(true);
                }
            }
        }
    }

    public function onEntityD(EntityDeathEvent $event)
    {
        $entity = $event->getEntity();
        $cause = $entity->getLastDamageCause();

        if ($cause != null) {
            if ($cause instanceof Player) {
                foreach ($entity->getDrops() as $drop => $item) {
                    if ($cause->getInventory()->canAddItem($item)) {
                        $event->setDrops([]);
                        $cause->getInventory()->addItem($item);
                    } else {
                        $cause->sendTip("§cInventário lotado.");
                        return true;
                    }
                }
            }
        }
    }

    /*
    public function onBreak(BlockBreakEvent $e){
        if($e->isCancelled()) return true;
        $pl = $this->plugin;
        $p = $e->getPlayer();
        $b = $e->getBlock();
        $playerFac = $pl->getPlayerFaction($p);
        $posFac = $pl->factionFromPoint($b->x, $b->z, $b->getLevel()->getName());
        if(!$pl->isInPlot($b)){
            return true;
        }
        if($pl->inOwnPlot($p) or $playerFac == $posFac){
            /////perm
        return true;
        }

    }
    */
    public function factionBlockBreakProtect(BlockBreakEvent $event)
    {
        $player = $event->getPlayer();
        $p = $player;
        $x = $event->getBlock()->getX();
        $z = $event->getBlock()->getZ();

        $level = $event->getBlock()->getLevel()->getName();
        if ($this->plugin->pointIsInPlot($x, $z, $level)) {
            $faction = $this->plugin->isInFaction($player->getName()) ? $this->plugin->getFaction($player->getName()) : '~';
            if ($this->plugin->isInPlot($p)) {
                if ($this->plugin->inOwnPlot($p)) {
                    if ($event->getBlock()->getId() === 52) {
                        if ($this->plugin->isInAtack($faction)) {
                            $player->sendMessage($this->plugin->formatMessage("§cVocê não pode tirar seus geradores, pois sua facção está sob ataque!"));
                            $event->setCancelled(true);
                            return true;
                        }
                        if (!$this->plugin->hasPermission($player, 'spawners')) {
                            $event->setCancelled(true);
                        }
                    } else {
                        if (!$this->plugin->hasPermission($player, 'blocks')) {
                            $event->setCancelled(true);
                        }
                    }
                    return;
                } else {
                    $event->setCancelled(true);
                    $event->getPlayer()->sendMessage($this->plugin->formatMessage("§cVocê não pode quebrar blocos aqui. Isso já é propriedade de uma facção. Use /f plotinfo para detalhes."));
                    return;
                }
            }
        }

//        if($level === "SpawnMineweck") {
//            if(in_array($event->getBlock()->getId(), [Block::STONE, Block::DIRT, Block::GRASS])) {
//
//                if(!$player->getInventory()->canAddItem(Item::get($event->getBlock()->getId(), 0, 1))) {
//                    $player->sendTip("§cInventário lotado.");
//                    return true;
//                }
//
//                if(!$this->plugin->iprotector->canEdit($player, $player->asPosition())) {
//                    $event->setCancelled();
//                }
//
//                $event->setDrops([]);
//                $player->getInventory()->addItem(Item::get($event->getBlock()->getId(), 0, 1));
//                $event->getBlock()->getLevel()->setBlock($event->getBlock()->asVector3(), Block::get(0));
//            }
//        }

        if($event->getBlock()->getId() == Block::SIGN_POST or $event->getBlock()->getId() == Block::WALL_SIGN) {
            if (!$event->getPlayer()->isOp()) {
                $event->setCancelled();
            }
        }

        if(!$this->plugin->iprotector->canEdit($player, $player->asPosition())) {
            $event->setCancelled();
        }
    }

    public function factionBlockPlaceProtect(BlockPlaceEvent $event)
    {
        $player = $event->getPlayer();

        $x = $event->getBlock()->getX();
        $z = $event->getBlock()->getZ();
        $level = $event->getBlock()->getLevel()->getName();
        $plotFac = $this->plugin->factionFromPoint($x, $z, $level);
        $playerFac = $this->plugin->getPlayerFaction($player);
        if ($this->plugin->pointIsInPlot($x, $z, $level)) {
            if ($this->plugin->factionFromPoint($x, $z, $level) == $this->plugin->getFaction($event->getPlayer()->getName())) {
                if ($event->getBlock()->getId() === 52) {
                    if (!$this->plugin->hasPermission($player, 'spawners')) {
                        $event->setCancelled(true);
                    }
                } else {
                    if (!$this->plugin->hasPermission($player, 'blocks')) {
                        $event->setCancelled(true);
                    }
                }
                return;
            } else {
                $event->setCancelled(true);
                $event->getPlayer()->sendMessage($this->plugin->formatMessage("§cVocê não pode colocar blocos aqui. Isso já é propriedade de uma facção. Use /f plotinfo para detalhes."));

                return;
            }
        }

        if($event->getBlock()->getId() == Block::SIGN_POST or $event->getBlock()->getId() == Block::WALL_SIGN) {
            if (!$event->getPlayer()->isOp()) {
                $event->setCancelled();
            }
        }

        if(!$this->plugin->iprotector->canEdit($player, $player->asPosition())) {
            $event->setCancelled();
        }
    }

    public function onSignChange(SignChangeEvent $event)
    {
        if(!$event->getPlayer()->isOp()) {
            $event->setCancelled();
        }
    }

    public function onKill(PlayerDeathEvent $event)
    {
        $ent = $event->getEntity();
        $cause = $event->getEntity()->getLastDamageCause();

        $power = 1;

        if ($cause instanceof EntityDamageByEntityEvent) {
            $killer = $cause->getDamager();
            if ($killer instanceof Player and $ent instanceof Player) {
                $p = $killer->getPlayer()->getName();
                $this->plugin->setPlayerPower($p, $this->plugin->getPlayerPower($p) + $power);
                if ($this->plugin->isInFaction($ent) and rand(0, 5) == 3) {
                    $fac = $this->main->getPlayerFaction($ent);
                    $d = $this->main->getFactionData($fac);
                    if (count($d["data"]["plots"]) > 0) {
                        $t = $d["data"]["plots"][array_rand($d["data"]["plots"])];
                        $ex = explode("_", $t);
                        $x = (int)$ex[0] * 16;
                        $z = (int)$ex[1] * 16;
                        $i = Item::get(Item::WRITTEN_BOOK);
                        if ($i instanceof WrittenBook) {
                            $i->setTitle("Cordenadas De Facçao");
                            $i->setAuthor($ent->getName());
                            $i->setPageText(0, TextFormat::colorize("&eCordenadas De Terra: \n\n &aFacçao:&7 $fac \n&a X:&c $x \n&a Z:&c $z"));
                        }
                        $killer->getInventory()->addItem($i);
                    }
                }
            }
        }
        if ($ent instanceof Player) {
            $power = 1;
            $this->plugin->setPlayerPower($ent->getName(), $this->plugin->getPlayerPower($ent->getName()) - $power);
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        // $this->plugin->updateTag($event->getPlayer()->getName());
		$this->plugin->getScheduler()->scheduleDelayedTask(new class($this->plugin, $event->getPlayer()) extends Task{
			
			public $plugin, $player;
			
			public function __construct($plugin, $player){
				$this->plugin = $plugin;
				$this->player = $player;
			}
			
			public function onRun($ticks){
				if($this->plugin->isInFaction($this->player)){
					$this->plugin->updateColorsTag($this->plugin->getPlayerFaction($this->player));
				}
			}
		}, 20*1);
        $p = $event->getPlayer();
        if (!$this->plugin->pdata->exists($p->getName())) {
            $this->plugin->pdata->set($p->getName(), [
                "power" => 0,
                "faction" => "false",
                "kill" => 0,
                "dead" => 0,
                "perms" => FactionMain::$defaultPermissions
            ]);
            $this->plugin->pdata->save();
            $this->plugin->pdata->reload();
        }
    }
}