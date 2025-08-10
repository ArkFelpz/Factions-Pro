<?php

namespace FactionsPro\data;

use FactionsPro\inventory\Window;
use FactionsPro\FactionMain;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\Player;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;

class DataBase {
	
	protected $main;

	public function __construct(FactionMain $main) {
		$this->main = $main;
		$this->pl = $main;
	}

	///// PERMISSIONS //////

	function rank(string $r){
		switch($r){
			case "Lider":
			$tag = "&6Lider";
			break;
			case "Capitao":
			$tag = "&eCapitao";
			break;
			case "Membro":
			$tag = "&7Membro";
			break;
			default:
			$tag = "---";
		}
		return $tag;
	}
	public function sendPermissionUi(Player $p){
	$pl = $this->main;
	$ui = new SimpleForm([$this, "selectedPermissionPlayer"]);
	$ui->setTitle("Selecione O Jogador");
	foreach($pl->getPlayersFaction($pl->getPlayerFaction($p), false, true) as $rank => $name){
	$ui->addButton(TextFormat::colorize("&7[".$this->rank($rank)."&7] &8$name"), -1, "", $name);
	}
	$ui->sendToPlayer($p);
	}
	public function selectedPermissionPlayer($p, $d){
	if(is_null($d)){
		return false;
	}
	$ui = new CustomForm([$this, "setNewPermissions"]);
	$ui->setTitle($d." - Permissoes");
	$ui->addLabel("Ative/Desative Os Botoes Para Alterar", $d);
	$messages = [
		"blocks" => "&eUtilizacao de Blocos No Terreno.",
		"spawners" => "&eUtilizacao de Geradores No Terreno.",
		"chests" => "&eUtilizacao de Baus No Terreno.",
		"home" => "&eUtilizacao de Homes No Terreno.",
		"tpa" => "&eUtilizacao de Tpa No Terreno."
	];
	foreach($messages as $id => $m){
		$def = (bool) $this->main->hasPermission($p, $id);
		$ui->addToggle(TextFormat::colorize(utf8_encode($m)), $def, $id);
	}
	$ui->sendToPlayer($p);
	}
	public function setNewPermissions($p, $d){
	if(is_null($d)) return true;
	foreach($d as $id => $n[]){
		if(!in_array($id, ["blocks", "spawners", "chests", "home", "tpa"])){
			$pn = $id;
			unset($d[$id]);
		}
	}
	$this->main->setPermissions($pn, $d);
	$p->sendMessage(TextFormat::colorize("&aPermissoes De&b $pn &aAlteradas!"));
	}

	///// TOPS ////////////
	public static $COINS_TYPE = "&e#{lugar} &7{fac} &cR$ &b{coins}";
	public function sendTopCoins($p){
	$data = $this->main->getCacheData();
	$p->sendMessage(TextFormat::colorize("&6Exibindo Lista Das Faccoes Mais Ricas"));
	$i = 1;
	foreach($data["coins"] as $fac => $coins){
	if($i < 11){
		$d = $this->main->getFactionData($fac);
		$n = $d["name"];
		$msg = str_replace(["{lugar}", "{fac}", "{coins}"], [$i, $n, number_format($coins, 2, ',', '.')], self::$COINS_TYPE);
		$p->sendMessage(TextFormat::colorize($msg));
	}
	$i++;
	}
	}
	public static $POWER_TYPE = "&e#{lugar} &7{fac} &cPoder &b{power}";
	public function sendTopPower(Player $p){
		$data = $this->main->getCacheData();
		$p->sendMessage(TextFormat::colorize("&6Exibindo Lista Das Faccoes Com Mais Poder"));
		$i = 1;
		foreach($data["power"] as $fac => $power){
		if($i < 11){
			$d = $this->main->getFactionData($fac);
			$n = $d["name"];
			$msg = str_replace(["{lugar}", "{fac}", "{power}"], [$i, $n, $power], self::$POWER_TYPE);
			$p->sendMessage(TextFormat::colorize($msg));
		}
		$i++;
		}
	}
	public static $SPAWNERS_TYPE = "&e#{lugar} &7{fac} &cR$ &b{valor} &6{types}";
	public function sendTopSpawner(Player $p){
		$data = $this->main->getCacheData();
		$p->sendMessage(TextFormat::colorize("&6Exibindo Lista Das Faccoes Com Mais Spawners"));
		$i = 1;
		foreach($data["spawners"] as $fac => $valor){
		if($i < 11){
			$d = $this->main->getFactionData($fac);
			$n = $d["name"];
			$msg = str_replace(["{lugar}", "{fac}", "{valor}", "{types}"], [$i, $n, number_format($valor, 2, ',', '.'), $this->main->spawnersString($fac)], self::$SPAWNERS_TYPE);
			$p->sendMessage(TextFormat::colorize($msg));
		}
		$i++;
		}
	}
	public static $GERAL_TYPE = "&e#{lugar} &7{fac} &cR$ &b{valor}";
	public function sendTopGeral(Player $p){
		$data = $this->main->getCacheData();
		$p->sendMessage(TextFormat::colorize("&6Exibindo Lista Das Faccoes Mais Ricas Em Geral"));
		$i = 1;
		foreach($data["geral"] as $fac => $valor){
		if($i < 11){
			$d = $this->main->getFactionData($fac);
			$n = $d["name"];
			$msg = str_replace(["{lugar}", "{fac}", "{valor}"], [$i, $n, number_format($valor, 2, ',', '.')], self::$GERAL_TYPE);
			$p->sendMessage(TextFormat::colorize($msg));
		}
		$i++;
		}
	}
	public function getMenu(Player $player) : Window {
		$playerName = $player->getName();
		$isInFaction = $this->main->isInFaction($playerName);

		if ($isInFaction) {
			$window = Window::get($player->asPosition(), 'Menu Da Facção', Window::DOUBLE_WINDOW);
		} else {
			$window = Window::get($player->asPosition(), $player->getName(), Window::NORMAL_WINDOW);
		}
		$this->sendMenu($window, $player);
		return $window;
	}

	public function sendMenu(Window $window, Player $player) : void {
		$playerName = $player->getName();
		$isInFaction = $this->main->isInFaction($playerName);

		$messages = [
			0 => "§r§eSolicitações de Facções\n§7Selecione para ver suas\n§7solicitações.",
			1 => "§r§6Seu Perfil:\n§8- §7Poder:§f " . $this->main->getPlayerPower($playerName) . "\n§8- §7Facção:§f " . ($isInFaction ? $this->main->getFaction($playerName) : '§cNenhuma'),
			2 => "§r§2Ranking de facções mais ricas do servidor\n§7Selecione para ver o Ranking!",
			3 => "§r§2Ranking de facções com mais poder do servidor\n§7Selecione para ver o Ranking!",
			4 => "§r§cOpções extras\n§7Selecione para configurar suas opções.",
			5 => "§r§cGerenciamento de Permissões\n§7Selecione para gerenciar as permissões\n§7 dos memebros de sua facção.",
			6 => "§r§cAbandonar Facção\n§7Selecione para sair de sua\n§7facção.",
			7 => "§r§cDeletar Facção\n§7Selecione para excluir sua\n§yfacção.",
			8 => "§r§eMembros da Facção\n§7Selecione para visualizar os\n§7membros de sua facção.",
			64 => "§r§eGeradores\n§7Clique Para Ver Os Geradores Da facção."
		];
		if($isInFaction) {
			$faction = $this->main->getFaction($playerName);

			$rank = $this->main->isLeader($playerName) ? 'Lider' : ($this->main->isOfficer($playerName) ? 'Capitão' : 'Membro');
			$factionPower = $this->main->getFactionPower($faction);
			$power = $this->main->getPlayerPower($playerName);

			$result = $this->main->db->query("SELECT * FROM motd WHERE faction='$faction';");
			$desc = $result->fetchArray(SQLITE3_ASSOC)['message'] ?? "§cSem Descrição";
			
			$leader = $this->main->getLeader($faction);

			$messages[9] = "§r§6Perfil:\n§8- §7Poder da Facção:§f ".$factionPower."\n§8- §7Seu Poder:§f ".$power."\n§8- §7Seu Cargo:§f ".$rank."\n§8- §7Lider:§f ".$leader."\n§8- §7Descrição:§f ".$desc;
		}

		if ($isInFaction) {
			$window->setItem(Window::getSlot(2, 2), Item::get(397, 3, 1)->setCustomName($messages[8]));
			$window->setItem(Window::getSlot(4, 2), Item::get(342, 0, 1)->setCustomName($messages[0]));
            $item = Item::get(Item::MOB_SPAWNER)->setCustomName($messages[64]);
				$tag = $item->getNamedTag();
				$tag->setString("type", "home");
				$item->setNamedTag($tag);
                $window->setItem(Window::getSlot(2, 5), $item);
			if($this->main->isLeader($playerName)) {
				$window->setItem(Window::getSlot(3, 2), Item::get(386, 0, 1)->setCustomName($messages[5]));
				$window->setItem(Window::getSlot(3, 5), Item::get(339, 0, 1)->setCustomName($messages[9]));
				$window->setItem(Window::getSlot(8, 5), Item::get(355, 14, 1)->setCustomName($messages[7]));
			} else {
				$window->setItem(Window::getSlot(2, 2), Item::get(339, 0, 1)->setCustomName($messages[9]));
				$window->setItem(Window::getSlot(3, 2), Item::get(397, 3, 1)->setCustomName($messages[8]));
				$window->setItem(Window::getSlot(8, 5), Item::get(355, 14, 1)->setCustomName($messages[6]));
			}
			$window->setItem(Window::getSlot(4, 5), Item::get(356, 0, 1)->setCustomName($messages[4]));

			$banner = Item::get(Item::BANNER, 0, 1);
			$banner->correctNBT();

			$banner->setDamage(1);
			$window->setItem(Window::getSlot(7, 2), (clone $banner)->setCustomName($messages[3]));

			$banner->setDamage(10);
			$window->setItem(Window::getSlot(8, 2), (clone $banner)->setCustomName($messages[2]));
		} else {
			$window->setItem(Window::getSlot(2, 2), Item::get(339, 0, 1)->setCustomName($messages[1]));
			$window->setItem(Window::getSlot(4, 2), Item::get(342, 0, 1)->setCustomName($messages[0]));

			$banner = Item::get(Item::BANNER, 0, 1);
			$banner->correctNBT();

			$banner->setDamage(1);
			$window->setItem(Window::getSlot(7, 2), (clone $banner)->setCustomName($messages[3]));

			$banner->setDamage(10);
			$window->setItem(Window::getSlot(8, 2), (clone $banner)->setCustomName($messages[2]));
		}
	}

	public function sendTop(Window $window, Player $player, int $page = 1, string $type = "coins"){
		$window->setLocal($type);
		$window->clearAll();

		$realType = array('coins' => 'Moeda(s)', 'power' => 'Poder')[strtolower($type)];

		if($this->main->isInFaction($player->getName())) {
			$faction = $this->main->getFaction($player->getName());
		} else {
			$faction = '~';
		}

		$window->setItem(Window::getSlot(9, 6), Item::get(339, 0, 1)->setCustomName("§r§eInformações:\n§8-§7 Página:§f $page"));

		$top = $this->main->getCacheData()[$type] ?? [];
		$size = 21;
		
		$max = ceil(count($top) / $size);
		$page = max(1, min($max, $page));
		
		$window->setPage($page);
		if($max >= 1){
			$factions = array_keys($top);

			if($page < $max){
				$window->setItem(Window::getSlot(9, 3), Item::get(262, 0, 1)->setCustomName("§r§2Próxima página\n§7Selecione para prosseguir."));
			}
			if($page > 1){
				$window->setItem(WIndow::getSlot(1, 3), Item::get(262, 0, 1)->setCustomName("§r§2Página anterior\n§7Selecione para voltar."));
			}
			$near = ($page * $size) - $size;

			$positionX = 0;
			$positionY = 1;

			$position = $near + 1;
			
			for($index = $near; $index < ($near + $size); $index++, $position++){
				if(++$positionX > 7) {
					$positionX = 1;
					$positionY++;
				}
				$slot = ($positionY * 9) + $positionX;

				if(isset($factions[$index])){
					$currentFaction = $factions[$index];
					$adicional = ' §c[facção inimiga]';

					$item = Item::get(Item::BANNER, 0, 1);
					$item->setDamage(8);

					if(strtolower($currentFaction) === strtolower($faction)) {
						$adicional = ' §e[sua facção]';
						$item->setDamage(15);
					} elseif($this->main->areAllies($currentFaction, $faction)) {
						$adicional = ' §a[facção aliada]';
						$item->setDamage(10);
					} elseif($this->main->areEnemies($currentFaction, $faction)) {
						$adicional = ' §c[facção inimiga]';
						$item->setDamage(1);
					}

					$item->setCustomName(implode("\n", [
						"§r§f".$position."ª Posição ".$adicional,
						"§r§8-§7 Facção:§f [".$this->main->getFactionTag($currentFaction)."] ".$this->main->getFactionData($currentFaction, "name"),
						"§r§8-§7 ".$realType.":§f ".number_format($top[$currentFaction], 0, "", ".")
					]));
				} else {
					$item = Item::get(351, 8);
					$item->setCustomName("§c~ Sem Facção");
				}

				$window->setItem($slot, $item);
			}
		}
	}

	public function sendExtraOptions(Window $window, Player $player) : void {
		$messages = [
			10 => "§r§2Vôo Automático\n§7Selecione para ativar ou\n§7desativar o seu vôo automático.",
			11 => "§r§2Chat Privado da Facção\n§7Selecione para ativar ou\n§7desativar o chat da facção.",
			12 => "§r§2Chat Privado dos Aliados\n§7Selecione para ativar ou\n§7desativar o chat aliado",
			13 => "§r§cOpção não disponível\n§7Você precisa esta em uma\n§7facção para utilizá-la"
		];

		$window->clearAll();
		$window->setItem(Window::getSlot(3, 1), Item::get(340, 0, 1)->setCustomName($messages[11]));
		if($player->hasPermission('fly.claim')) {
			$window->setItem(Window::getSlot(5, 1), Item::get(288, 0, 1)->setCustomName($messages[10]));
		}
		$window->setItem(Window::getSlot(7, 1), Item::get(386, 0, 1)->setCustomName($messages[12]));

		$playerName = $player->getName();

		if ($this->main->isInFaction($playerName)) {
			if(isset($this->main->factionChatActive[$playerName])) {
				$window->setItem(Window::getSlot(3, 2), Item::get(351, 10, 1)->setCustomName("§r§2Ativado."));
			} else {
				$window->setItem(Window::getSlot(3, 2), Item::get(351, 1, 1)->setCustomName("§r§cDestivado."));
			}

			if(isset($this->main->factionFly[$playerName])) {
				$window->setItem(Window::getSlot(5, 2), Item::get(351, 10, 1)->setCustomName("§r§2Ativado."));
			} else {
				$window->setItem(Window::getSlot(5, 2), Item::get(351, 1, 1)->setCustomName("§r§cDestivado."));
			}

			if(isset($this->main->allyChatActive[$playerName])) {
				$window->setItem(Window::getSlot(7, 2), Item::get(351, 10, 1)->setCustomName("§r§2Ativado."));
			} else {
				$window->setItem(Window::getSlot(7, 2), Item::get(351, 1, 1)->setCustomName("§r§cDestivado."));
			}
		} else {
			$window->setItem(Window::getSlot(3, 2), Item::get(351, 8, 1)->setCustomName($messages[13]));
			$window->setItem(Window::getSlot(5, 2), Item::get(351, 8, 1)->setCustomName($messages[13]));
			$window->setItem(Window::getSlot(7, 2), Item::get(351, 8, 1)->setCustomName($messages[13]));
		}
	}

	public function sendRequests(Window $window, Player $source) : void {
		$window->clearAll();

		$lowercaseName = strtolower($source->getName());
		$query = $this->main->db->query("SELECT * FROM confirm WHERE player='$lowercaseName' GROUP BY faction;");
		
		$array = [];
		while($result = $query->fetchArray(SQLITE3_ASSOC)) {
			$time = $result['timestamp'];
			if((time() - $time) <= 120) $array[$result['faction']] = $time;
		}
		$positionX = 0;
		$positionY = 1;

		if(($count = count($array)) > 0) {
			$max = 21;

			foreach($array as $faction => $temp) {
				$time = (time() - $temp);
				if(++$positionX > 7) {
					$positionX = 1;
					$positionY++;
				}
				$slot = ($positionY * 9) + $positionX;

				$item = Item::get(339, 0, 1)->setCustomName("§r§eSolicitação de:§f [{$this->main->getFactionTag($faction)}] $faction\n§8-§7 pedido há:§f $time segundos atrás\n§r\n§7Selecione para aceitar a solicitação\nda facção {$faction}.");
				
				$tag = $item->getNamedTag();
				$tag->setString('requestFrom', $faction);
				$item->setNamedTag($tag);

				$window->setItem($slot, $item);

				if(--$max < 0) break;
			}
		}
	}

	public function getRequestsWindow(Player $source) : Window {
		$window = Window::get($source->asPosition(), 'Solicitações de Facções', Window::DOUBLE_WINDOW);
		$this->sendRequests($window, $source);
		return $window;
	}

	public function sendMembersPermissions(Window $window, Player $source) : void {
		$window->clearAll();
		$name = $source->getName();

		if($this->main->isInFaction($name) === false) {
			return;
		}
		$faction = $this->main->getFaction($name);
		$pls = $this->main->getFactionData($faction, "data")["players"];
		$players = [];
		foreach($pls as $p => $c){
			$players[] = $p;
		}

		if(($count = min(18, count($players))) > 0) {
			$row = 1;
			$column = 1;

			$max = $count;

			for($i = 0; $i < 18; $i++) {
				$currentSlot = $row + ($column * 9);
				if(++$row > 7) {
					$row = 1;
					$column++;
				}
				if(!isset($players[$i])) continue;

				$player = $players[$i];
				$permissions = $this->main->getPermissions($player, true);

				$item = Item::get(397, 3, 1)->setCustomName(implode("\n", [
					"§r§e".$player,
					"§8 §cPermissões: ",
					"§8 - §7Utilizar Blocos: ".($permissions['blocks'] ? '§2Liberado' : '§cNão Liberado'),
					"§8 - §7Utilizar Geradores: ".($permissions['spawners'] ? '§2Liberado' : '§cNão Liberado'),
					"§8 - §7Utilizar Homes: ".($permissions['home'] ? '§2Liberado' : '§cNão Liberado'),
					"§8 - §7Utilizar Tpa: ".($permissions['tpa'] ? '§2Liberado' : '§cNão Liberado'),
					"§8 - §7Utilizar Baús: ".($permissions['chests'] ? '§2Liberado' : '§cNão Liberado')
				]));
				$tag = $item->getNamedTag();
				$tag->setString('permissionsOf', $player);
				$item->setNamedTag($tag);

				$window->setItem($currentSlot, $item);
				if(--$count < 0) break;
			}
		}
	}
	public function toName($dm){
		$ids = [
		10 => "Galinha",
		11 => "Vaca",
		12 => "Porco",
		13 => "Ovelha",
		15=> "Villager",
		16 => "CoguVaca",
		20=> "Golem",
		21=> "Golem de Neve",
		28=> "Urso Polar"
		];
		if($this->main->sp->exists("Geradores")){
		$ids = $this->main->sp->get("Geradores");
		}
		return isset($ids[$dm]) ? $ids[$dm] : "UNKNOWN";
	}
	public $tag = "&6Gerador De {nome} \n&7Quantidade: &e{quantia}";
	public function setGeraByFac($w, $fac, $clear = false){
		if($clear){
			for($i = 1; $i < 9; $i++){
				$w->setItem($w::getSlot($i, 4), Item::get(0));
				$w->setItem($w::getSlot($i, 5), Item::get(0));
			}
		}
		$c = $this->main->gera;
		$n = 2;
		$co = 4;
		foreach($c->get($fac) as $dm => $arr){
			$i = Item::get(Item::MOB_SPAWNER);
			$i->setCustomName("a");
			$tag = $i->getNamedTag();
			$tag->setInt("dm", $dm);
			$tag->setString("type", "remove");
			$i->setNamedTag($tag);
			$i->setCustomName(TextFormat::colorize(str_replace(["{nome}", "{quantia}"], [$this->toName($dm), $arr["count"]], $this->tag)));
			$w->setItem($w::getSlot($n, $co), $i);
			$n++;
			if($n == 9){
				$n = 2;
				$co = 5;
			}
		}
	}
	public function onVerifyAdd($p, $win){
	$inv = $p->getInventory();
	$list = [];
	for($i = 0; $i < $inv->getSize(); $i++){
		$item = $inv->getItem($i);
		if($item->getId() == Item::MOB_SPAWNER and $item->getDamage() !== 0){
			$list[$i] = $item;
		}
	}
	if(count($list) > 0){
	$fac = $this->main->getPlayerFaction($p->getName());
     foreach($list as $index => $item){
		$inv->setItem($index, Item::get(0));
		$this->main->addGera($fac, $item);
	 }
	 $win->setItem($win::getSlot(5, 4), Item::get(0));
	 $this->setGeraByFac($win, $fac, true);
	}
	}
	public function onVerifyRemove($p, $win, $item){
		$m = $this->main;
		$fac = $this->main->getPlayerFaction($p->getName());
		if($m->isLeader($p->getName()) or $m->isOfficer($p->getName())){
			$dm = $item->getNamedTag()->getInt("dm");
			if($m->removeGera($fac, $dm, $p)){
				
				if($this->main->haveGera($fac)){
			$this->setGeraByFac($win, $fac);
		}else{
		$win->setItem(Window::getSlot(5, 4), Item::get(30)->setCustomName("§cSua faccao nao tem Geradores Armazenados"));
		}
			}
			
		}
	}
    public function getGeradoresMenu(Player $p) : Window{
		$fac = $this->main->getPlayerFaction($p->getName());
		$pos = new \pocketmine\level\Position($p->x, $p->y - 3, $p->z, $p->level);
		$win = Window::get($p->asPosition(), "Geradores", Window::DOUBLE_WINDOW);
		$chest = Item::get(Item::CHEST)->setCustomName("§eArmazenar todos geradores de seu inventario");
		$tag = $chest->getNamedTag();
		$tag->setString("type", "add");
		$chest->setNamedTag($tag);
		$win->setItem(Window::getSlot(5, 2), $chest);
		$win->setItem(Window::getSlot(5, 6), Item::get(Item::ARROW)->setCustomName("§r§cVoltar para o menu\n§7Selecione para voltar ao\n§7menu da facção."));
		if($this->main->haveGera($fac)){
			$this->setGeraByFac($win, $fac);
		}else{
		$win->setItem(Window::getSlot(5, 4), Item::get(30)->setCustomName("§cSua faccao nao tem Geradores Armazenados"));
		}
		return $win;
	}
	public function getMembersPermissions(Player $source) : Window {
		$inventory = Window::get($source->asPosition(), 'Gerenciamento de Permissões', Window::DOUBLE_WINDOW);
		$this->sendMembersPermissions($inventory, $source);
		return $inventory;
	}

	public function getMemberPermissionWindow(Player $source, string $member) : Window {
		$windowName = $member;
		if(strlen($windowName) > 11) {
			$windowName = substr($windowName, 0, 11).'...';
		}
		$inventory = Window::get($source->asPosition(), $windowName.' - Permissões', Window::DOUBLE_WINDOW);
		$this->setMemberPermissions($inventory, $member);

		return $inventory;
	}

	public function setMemberPermissions(Window $window, string $member) : void {
		$messages = [
			40 => "§r§eUtilização de Blocos\n§7Selecione para bloquear\n§7a utilização de blocos em seu terreno.",
			41 => "§r§eUtilização de Geradores\n§7Selecione para impedir\n§7a utilização de geradores em seu terreno.",
			42 => "§r§eUtilização de Baús\n§7Selecione para bloquear a utilização\n§7de baús em seu terreno.",
			43 => "§r§eUtilização de Homes\n§7Selecione para bloquear o uso\n§7de homes em seu terreno.",
			44 => "§r§eUtilização de Tpa\n§7Selecione para desativar pedidos\n§7de teleporte em seu terreno."
		];
		$manager = $this->main;
		$window->clearAll();
		
		if($manager->isInFaction($member)) {
			$faction = $manager->getFaction($member);
			$permissions = $manager->getPermissions($member, true);

			$item = Item::get(270, 0, 1)->setCustomName($messages[40]);
			$tag = $item->getNamedTag();
			$tag->setString('setPermissionTo', $member);
			$item->setNamedTag($tag);

			$window->setItem(Window::getSlot(3, 3), $item);

			$item = Item::get(52, 0, 1)->setCustomName($messages[41]);
			$tag = $item->getNamedTag();
			$tag->setString('setPermissionTo', $member);
			$item->setNamedTag($tag);

			$window->setItem(Window::getSlot(4, 3), $item);

			$item = Item::get(54, 0, 1)->setCustomName($messages[42]);
			$tag = $item->getNamedTag();
			$tag->setString('setPermissionTo', $member);
			$item->setNamedTag($tag);

			$window->setItem(Window::getSlot(5, 3), $item);

			$item = Item::get(342, 0, 1)->setCustomName($messages[43]);
			$tag = $item->getNamedTag();
			$tag->setString('setPermissionTo', $member);
			$item->setNamedTag($tag);

			$window->setItem(Window::getSlot(6, 3), $item);

			$item = Item::get(381, 0, 1)->setCustomName($messages[44]);
			$tag = $item->getNamedTag();
			$tag->setString('setPermissionTo', $member);
			$item->setNamedTag($tag);

			$window->setItem(Window::getSlot(7, 3), $item);

			$row = 3;
			foreach(['blocks', 'spawners', 'chests', 'home', 'tpa'] as $type) {
				$value = $permissions[$type] ?? true;
				$window->setItem(Window::getSlot($row++, 4), Item::get(351, $value ? 10 : 1, 1)->setCustomName(
					$value ? '§r§7Estado:§2 Liberado' : '§r§7Estado:§c Não Liberado'
				));
			}
			$item = Item::get(397, 3, 1)->setCustomName(implode("\n", [
				"§r§e".$member,
				"§8 §cPermissões: ",
				"§8 - §7Utilizar Blocos: ".($permissions['blocks'] ? '§2Liberado' : '§cNão Liberado'),
				"§8 - §7Utilizar Geradores: ".($permissions['spawners'] ? '§2Liberado' : '§cNão Liberado'),
				"§8 - §7Utilizar Homes: ".($permissions['home'] ? '§2Liberado' : '§cNão Liberado'),
				"§8 - §7Utilizar Tpa: ".($permissions['tpa'] ? '§2Liberado' : '§cNão Liberado'),
				"§8 - §7Utilizar Baús: ".($permissions['chests'] ? '§2Liberado' : '§cNão Liberado')
			]));

			$window->setItem(Window::getSlot(5), $item);
		} else {
			$item = Item::get(351, 1, 1)->setCustomName("§f{$member}§c saiu de sua facção!");
			$window->setItem(Window::getSlot(5, 3), $item);
		}
	}

	public function getMemberWindow(Player $source) : Window {
		$inventory = Window::get($source->asPosition(), 'Membros da Facção', Window::DOUBLE_WINDOW);
		$faction = $this->main->getFaction($source->getName());

		$row = 1;
		$column = 1;

		$pls = $this->main->getFactionData($faction, "data")["players"];
		$players = [];
		foreach($pls as $p => $c){
			$players[] = $p;
		}
		$max = min(18, $this->main->prefs->get("MaxPlayersPerFaction", 15));

		for($i = 0; $i < $max; $i++) {
			$currentSlot = $row + ($column * 9);
			if(++$row > 7) {
				$row = 1;
				$column++;
			}

			if(!isset($players[$i])) {
				$inventory->setItem($currentSlot, Item::get(351, 8, 1)->setCustomName("§cSem Jogador\n§7Vaga disponível."));
				continue;
			}
			$player = $players[$i];
		    $name = $player;

			$rank = $this->main->isLeader($name) ? 'Lider' : ($this->main->isOfficer($name) ? 'Capitão' : 'Membro');

			$item = Item::get(397, 3, 1)->setCustomName(implode("\n", [
				"§r§e".$name,
				"§8- §7Cargo:§f ".$rank,
				"§8- §7Poder:§f ".$this->main->getPlayerPower($name)
			]));

			$inventory->setItem($currentSlot, $item);
		}
		return $inventory;
	}

}