<?php

namespace FactionsPro;

use FactionsPro\data\DataBase;
use FactionsPro\inventory\Window;
use FactionsPro\tasks\AttackTimer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\item\ItemBlock;
use jojoe77777\FormAPI\SimpleForm;

use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\InvMenu;

use Ultimate\Kdr\KdrPlayerManager;

class FactionCommands
{
    const TYPE_SETSPAWN = "setspawn";

    /** @var FactionMain */
    public $plugin;
    public static $invites;

    public function __construct(FactionMain $pg)
    {
        $this->plugin = $pg;
        $this->pl = $pg;
    }

    public function alphanum($string)
    {
        if (function_exists('ctype_alnum')) {
            $return = ctype_alnum($string);
        } else {
            $return = preg_match('/^[a-z0-9]+$/i', $string) > 0;
        }
        return $return;
    }

    public function uiDataProcess($p, $d)
    {
        if (is_null($d)) return;
        $a = explode(":", $d);
        switch ($a[0]) {
            case self::TYPE_SETSPAWN:
                $dm = $a[1];
                if ($dm == 41) $dm = 20;
                $this->plugin->setSpawnOn($p, $dm);
                return false;
                break;
        }
    }

    public function verifyPermission(Player $p, bool $isInFaction = false, bool $isInPlot = false, bool $inOwnPlot, bool $leader = false, bool $officer = false): array
    {
        $pl = $this->pl;
        $perm = [false, ""];
        if ($isInFaction) {
            if ($pl->isInFaction($p)) {
                $perm[0] = true;
            } else return [false, "&cVocê não está em uma facção."];
        }
        if ($isInPlot) {
            if ($pl->isInPlot($p)) {
                $perm[0] = true;
            } else return [false, "&cVocê não está em um terreno"];
        }
        if ($inOwnPlot) {
            if ($pl->inOwnPlot($p)) {
                $perm[0] = true;
            } else return [false, "&cVocê não está em Terreno da sua Facção"];
        }
        if ($leader) {
            if ($pl->isLeader($p)) {
                $perm[0] = true;
            } else return [false, "&cVocê não é o Lider da Facção"];
        }
        if ($officer) {
            if ($pl->isOfficer($p)) {
                $perm[0] = true;
            } else return [false, "&cVocê não é Capitão da Facção"];
        }
        return $perm;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args)
    {
        $playerName = $sender->getName();
        $p = $sender;
        $pl = $this->plugin;
        $factionName = $this->plugin->getPlayerFaction($playerName);
        $all = $pl->getFactionData($factionName); 
       
        
        
        if (strtolower($command->getName()) !== "f" || empty($args)) {
			$menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
            $inv = $menu->getInventory();
			$this->plugin->inventories[$playerName] = [$inv, "menu-principal"];
			$cargo = "";
			$playersPower = 0;
			$kdrKills = KdrPlayerManager::getPlayerKills($playerName);
			$kdrDeaths = KdrPlayerManager::getPlayerDeaths($playerName);
			if(!$this->plugin->isInFaction($playerName)){
				/* 	PLAYER SEM FACÇÃO
					ADICIONAR NOVOS ITENS	
				*/
                
				 $inv->setItem(10, Item::get(397, 3, 1)->setCustomName("§r§7".$playerName)->setLore(["§f", "§r§fCargo: §7Nenhum", "§r§fKDR:§7 ".KdrPlayerManager::getPlayerKdr($playerName), "§r§fAbates: §7".$kdrKills["total"]." §8[Inimigo: §7".$kdrKills["inimigo"]." §8Neutro: §7".$kdrKills["neutro"]." §8Civil: ".$kdrKills["civil"]."§7]", "§r§fMortes §7".$kdrDeaths["total"]." §8[Inimigo: §7".$kdrDeaths["inimigo"]." §8Neutro: §7".$kdrDeaths["neutro"]." §8Civil: ".$kdrDeaths["civil"]."§7]", "§r§fStatus: §aOnline"]));
                $inv->setItem(14, Item::get(397, 1, 1)->setCustomName("§r§eRanking de Facções")->setLore(["§r§7Clique para ver os rankings com", "§r§7as melhores facções do  servidor."]));
                $inv->setItem(15, Item::get(397, 2, 1)->setCustomName("§r§eFacções Online")->setLore(["§r§7Clique para ver as facções online."]));          
                $inv->setItem(16, Item::get(397, 5, 1)->setCustomName("§r§eAjuda")->setLore(["§r§7Todas as ações disponiveis neste menu.", "§r§7também podem ser realizadas por", "§r§7comando. Utilize §f/f help §7", "§r§7para ver todos os comandos disponiveis"]));
                $inv->setItem(38, Item::get(339, 0, 1)->setCustomName("§r§aMapa")->setLore(["§r§7Veja o mapa da região próxima a você.", "", "§r§fClique: Ver o mapa"]));
                $inv->setItem(39, Item::get(2, 0, 1)->setCustomName("§r§aTerreno da §cZona de guerra/Zona protegida/claim de facção/area livre"));
                $inv->setItem(40, Item::get(339, 0, 1)->setCustomName("§r§aConvites de Facçãoes")->setLore(["§r§7VocÊ não possui nenhum convite pendente"]));

				$menu->send($sender, "§8".$factionName);
				return true;
			}
			if($this->plugin->isLeader($playerName)){
				$cargo = "Líder";
			}elseif($this->plugin->isOfficer($playerName)){
				$cargo = "Capitão";
			}elseif($this->plugin->isMember($playerName)){
				$cargo = "Membro";
			}
			foreach($this->plugin->getMembersOfFaction($factionName) as $name){
				$playersPower += $this->plugin->getPlayerPower($name);
			}
			
 			$inv->setItem(10, Item::get(397, 3, 1)->setCustomName("§r§7".$playerName)->setLore(["§f", "§r§fCargo: §7#".$cargo, "§r§fKDR:§7 ".KdrPlayerManager::getPlayerKdr($playerName), "§r§fAbates: §7".$kdrKills["total"]." §8[Inimigo: §7".$kdrKills["inimigo"]." §8Neutro: §7".$kdrKills["neutro"]." §8Civil: ".$kdrKills["civil"]."§7]", "§r§fMortes §7".$kdrDeaths["total"]." §8[Inimigo: §7".$kdrDeaths["inimigo"]." §8Neutro: §7".$kdrDeaths["neutro"]." §8Civil: ".$kdrDeaths["civil"]."§7]", "§r§fStatus: §aOnline"]));
			$inv->setItem(14, Item::get(397, 1, 1)->setCustomName("§r§eRanking de Facções")->setLore(["§r§7Clique para ver os rankings com", "§r§7as melhores facções do  servidor."]));
			$inv->setItem(15, Item::get(397, 2, 1)->setCustomName("§r§eFacções Online")->setLore(["§r§7Clique para ver as facções online."]));          
			$inv->setItem(16, Item::get(397, 5, 1)->setCustomName("§r§eAjuda")->setLore(["§r§7Todas as ações disponiveis neste menu.", "§r§7também podem ser realizadas por", "§r§7comando. Utilize §f/f help §7", "§r§7para ver todos os comandos disponiveis"]));
			$inv->setItem(28, Item::get(397, 0, 1)->setCustomName("§r§aMembros")->setLore(["§r§7Mostrar membros da facção."]));
			$inv->setItem(29, Item::get(Item::DIRT, 0, 1)->setCustomName("§r§aTerrenos")->setLore(["§r§7Clique para ver os terrenos da facção."]));
			$inv->setItem(34, Item::get(Item::BANNER, 15, 1)->setCustomName("§r§e".strtoupper($this->plugin->getFactiontag($factionName))." - ".$factionName)->setLore(["§r§fPoder: §7".$playersPower, "§r§fPoder máximo: ".$this->plugin->getMembersOnline($factionName)*$this->plugin->prefs->get("MaxPowerPerPlayer", 5), "§r§f#".$cargo , "§r§fLider: §7[".$this->plugin->getFactiontag($factionName)."] §f".$this->plugin->getLeader($factionName), "§r§fMembros: §7(".$this->plugin->getMembersOnline($factionName)."/15) ".$this->plugin->getMembersOnline($factionName)." online", "§r§r".$this->plugin->getMembersMessage($factionName), "§r§7", "§r§fAliados: ".$this->plugin->getAlliesMessage($factionName), "§r§fInimigos: ".$this->plugin->getEnemiesMessage($factionName)]));
			$inv->setItem(37, Item::get(52, 0, 1)->setCustomName("§r§eGeradores")->setLore(["§r§7Gerêncie os geradores da facção."]));
			$inv->setItem(38, Item::get(Item::PAPER, 0, 1)->setCustomName("§r§aMapa")->setLore(["§r§7Veja o mapa da região próxima a você", "", "§r§fBotão esquerdo: §7Ver o mapa", "§r§fBotão direito: §7Ligar o modo mapa"]));
			$inv->setItem(39, Item::get(270, 0, 1)->setCustomName("§r§aPermissões")->setLore(["§r§7Clique para gerenciar as", "§r§7permissões da facção."]));
			$inv->setItem(40, Item::get(315, 0, 1)->setCustomName("§r§aUpgrades")->setLore(["§r§7Clique para gerenciar os", "§r§7upgrades da facçãoo."]));
			$inv->setItem(43, Item::get(431, 0, 1)->setCustomName("§r§cDesfazer")->setLore(["§r§7Utilize para desfazer a facção."]));
            $menu->send($sender, "§8".$factionName);
			return true;
        }
        
        
        
        
		if(in_array($args[0], ["help"])){
			$sender->sendMessage(TextFormat::colorize("&eComandos Disponiveis
            &c/f criar <tag> <nome>&7- Criar uma facção.
            &c/f deletar&7 - Deletar sua facção.
            &c/f convidar <jogador>&7 - Convidar jogadores para a facção.
            &c/f expulsar <jogador>&7 - Expulsar jogador da facção.
            &c/f promover <jogador>&7 - Promover jogador a capitão.
            &c/f rebaixar <jogador>&7 - Rebaixar jogador a membro.
            &c/f sair&7 - Sair de uma facção.
            &c/f ally <faccao>&7 - Enviar pedido de aliança.
            &c/f tirarally <faccao>&7 - Remover aliança.
            &c/f lider <player>&7 - Promover jogador a lider.
            &c/f info, /f info <faccao>&7 - Ver informações sobre a facção.
            &c/f chat&7 - Ativar bate-papo da facção.
            &c/f allychat&7 - Ativar bate-papo dos aliados.
            
            &c/f mapa&7 - Ver mapa do local.
            &c/f dominar&7 - Dominar uma (Chunk).
            &c/f abandonar&7 - Abondonar uma terra (Chunk).
            &c/f abandonar todas&7 - Abondonar todas as terras da facção
            &c/f chunk&7 - Ver limitações da chunk.
            &c/f voar&7 - Ativar e desativar o vôo no terreno.
            &c/f setspawn&7 - Setar local de spawn de um mob.
            &c/f plotinfo&7 - Verificar estado de um terreno.
            
            &c/f permissao, /f perm&7 - Alterar permissões dos membros e capitões.
            &c/f top <coins, power, spawners>
			"));
		}
        if (!in_array($args[0], ["criar", "create", "del", "deletar", "convidar", "invite", "expulsar", "kick",
            "promover", "promote", "rebaixar", "demote", "sair", "ally", "tirarally", "unally", "unally", "lider",
            "info", "chat", "allychat", "mapa", "dominar", "claim", "abandonar", "unclaim", "chunk",
            "voar", "upgrades", "fly", "setspawn", "plotinfo", "perm", "permissao", "top", "help", "ajuda", "aceitar", "negar", "negarally", "addinimigo", "enemy", "unenemy", "tirarinimigo", "geradores", "upgradar",
			"aceitarally", "negarally"])) {
            $p->sendMessage(TextFormat::colorize("&cComando Nao Existe, use /f help para ver a lista de comandos"));

        }
        if (in_array(strtolower($args[0]), ["top", "tops", "ftop"])) {
            if (!isset($args[1]) or !in_array($args[1], ["coins", "power", "spawners", "geral"])) {
                return true;
            }
            $db = new DataBase($this->plugin);
            switch ($args[1]) {
                case "coins":
                    $db->sendTopCoins($p);
                    echo "a" . PHP_EOL;
                    break;
                case "power":
                    $db->sendTopPower($p);
                    break;
                case "spawners":
                    $db->sendTopSpawner($p);
                    break;
                case "geral":
                    $db->sendTopGeral($p);
                    break;
            }
        }
        if (in_array(strtolower($args[0]), ["perm", "permissao", "permission"])) {
            $perm = $this->verifyPermission($p, true, false, false, true);
            if ($perm[0]) {
                (new DataBase($pl))->sendPermissionUi($p);
            } else $p->sendMessage(TextFormat::colorize($perm[1]));
        }
        if (in_array(strtolower($args[0]), ["setspawn", "mobspawn", "mob"])) {
            if (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção para usar isso"));
                return true;
            }
            if (!$this->plugin->inOwnPlot($p)) {
            	$sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em um clain da facção"));
            	return true;
            }
            if (!$this->plugin->isLeader($playerName) and !$this->plugin->isOfficer($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não tem permissão para fazer isso"));
                return true;
            }
            $c = $this->plugin->sp;
            $ui = new SimpleForm([$this, "uiDataProcess"]);
            $ui->setTitle("Escolha Um Mob");
            foreach ($c->get("MobsNaWindow") as $id => $name) {
                if ($id == 20) $id = 41;
                $eid = self::TYPE_SETSPAWN . ":" . $id;
                $ui->addButton($name, 0, "", $eid);
            }
            $ui->sendToPlayer($sender);
        }
        if (strtolower($args[0]) == "ajuda" or strtolower($args[0]) == "help") {
            $sender->sendMessage(TextFormat::colorize("&eComandos Disponiveis
            &c/f criar <tag> <nome>&7- Criar uma facção.
            &c/f deletar&7 - Deletar sua facção.
            &c/f convidar <jogador>&7 - Convidar jogadores para a facção.
            &c/f expulsar <jogador>&7 - Expulsar jogador da facção.
            &c/f promover <jogador>&7 - Promover jogador a capitão.
            &c/f rebaixar <jogador>&7 - Rebaixar jogador a membro.
            &c/f sair&7 - Sair de uma facção.
            &c/f ally <faccao>&7 - Enviar pedido de aliança.
            &c/f tirarally <faccao>&7 - Remover aliança.
            &c/f lider <player>&7 - Promover jogador a lider.
            &c/f info, /f info <faccao>&7 - Ver informações sobre a facção.
            &c/f chat&7 - Ativar bate-papo da facção.
            &c/f allychat&7 - Ativar bate-papo dos aliados.
            
            &c/f mapa&7 - Ver mapa do local.
            &c/f dominar&7 - Dominar uma (Chunk).
            &c/f abandonar&7 - Abondonar uma terra (Chunk).
            &c/f abandonar todas&7 - Abondonar todas as terras da facção
            &c/f chunk&7 - Ver limitações da chunk.
            &c/f voar&7 - Ativar e desativar o vôo no terreno.
            &c/f setspawn&7 - Setar local de spawn de um mob.
            &c/f plotinfo&7 - Verificar estado de um terreno.
            
            &c/f permissao, /f perm&7 - Alterar permissões dos membros e capitões.
            &c/f top <coins, power, spawners>
                        "));
            return true;
        }
        if (!$sender instanceof Player || ($sender->isOp())) {
            if (strtolower($args[0]) == "addpower") {
                if (!isset($args[1]) || !isset($args[2]) || !$this->alphanum($args[1]) || !is_numeric($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f addpower <faction name> <power>"));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $this->plugin->addFactionPower($args[1], $args[2]);
                    $sender->sendMessage($this->plugin->formatMessage("Power " . $args[2] . " added to Faction " . $args[1]));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Faction " . $args[1] . " does not exist"));
                }
            }
            if (strtolower($args[0]) == "setpower") {
                if (!isset($args[1]) || !isset($args[2]) || !$this->alphanum($args[1]) || !is_numeric($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f setpower <faction name> <power>"));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $this->plugin->setFactionPower($args[1], $args[2]);
                    $sender->sendMessage($this->plugin->formatMessage("Faction " . $args[1] . " set to Power " . $args[2]));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Faction " . $args[1] . " does not exist"));
                }
            }
            if (!$sender instanceof Player) {
                return true;
            }

        }



        
        
        ///////////////////////////////// WAR /////////////////////////////////


        /////////////////////////////// CREATE ///////////////////////////////
         
        if ($args[0] == "test") {
            print_r(["oola" => $this->plugin->getCacheData()]);
        }

        if ($args[0] == "criar") {
            if ($this->plugin->isInFaction($sender->getName())) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve deixar sua facção primeiro"));
                return true;
            }
            if (isset($args[1])) {
                if (!($this->alphanum($args[1]))) {
                    $sender->sendMessage($this->plugin->formatMessage("§c* A TAG só deve conter letras e números!"));
                    return true;
                }
                if (strlen($args[1]) !== 3) {
                    $sender->sendMessage($this->plugin->formatMessage("§c* A TAG deve conter exatamente 3 letras ou números!"));
                    return true;
                }
                if ($this->plugin->tagExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("§c* Esta TAG já está sendo usada por uma facção!"));
                    return true;
                }
                if (isset($args[2])) {
                    if (!($this->alphanum($args[2]))) {
                        $sender->sendMessage($this->plugin->formatMessage("§c* O nome facção só pode conter letras e números!"));
                        return true;
                    }
                    if ($this->plugin->isNameBanned($args[2])) {
                        $sender->sendMessage($this->plugin->formatMessage("§c* Este nome de facção não é permitido."));
                        return true;
                    }
                    if ($this->plugin->factionExists($args[2]) == true) {
                        $sender->sendMessage($this->plugin->formatMessage("§e* Este nome já está sendo usado por uma facção"));
                        return true;
                    }
                    if (strlen($args[2]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
                        $sender->sendMessage($this->plugin->formatMessage("§c* Este nome é muito longo. Por favor, tente novamente!"));
                        return true;
                    }

                    $this->plugin->createFaction($args[1], $args[2], $sender->getName());
                    if ($this->plugin->prefs->get("FactionNametags")) {
                        $this->plugin->updateTag($playername);
                    }
                    #$this->plugin->getServer()->getPluginManager()->getPlugin("DailyReward")->setMission($sender, "faction", 1);

                    $sender->sendMessage($this->plugin->formatMessage("§a* Facção criada com sucesso!", true));
                    return true;
                } else {
                    $sender->sendMessage("§c* Nome da facção não definido!");
                }
                return true;
            } else {
                $sender->sendMessage("§e* Use: /f criar (tag da facção) (nome da facção)");
                return true;
            }
        }

        /////////////////////////////// INVITE ///////////////////////////////

        if ($args[0] == "convidar") {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Use: / f convidar (jogador)"));
                return true;
            }
            if (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção para usar isso"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName) and !$this->plugin->isOfficer($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não tem permissão para fazer isso"));
                return true;
            }
            if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($playerName))) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Facção está cheia. Por favor, expulse os jogadores para abrir espaço."));
                return true;
            }
            $invited = $this->plugin->getServer()->getPlayerExact($args[1]);
            if (!$invited instanceof Player) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Jogador não está online!"));
                return true;
            }
            if ($invited->getLowerCaseName() === $sender->getLowerCaseName()) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não pode se convidar!"));
                return false;
            }
            if ($this->plugin->isInFaction($invited->getName()) === true) {
                $sender->sendMessage($this->plugin->formatMessage("§e* O jogador está atualmente em uma facção"));
                return true;
            }
            $factionName = $this->plugin->getPlayerFaction($playerName);
            $invitedName = strtolower($invited->getName());

            if (isset(self::$invites[$invitedName])) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você já enviou um pedido de solicitação a este jogador, por favor espere ele aceitar!"));
                return true;
            }
            self::$invites[$invitedName] = $factionName;
            $sender->sendMessage($this->plugin->formatMessage("§a* $invitedName foi convidado!", true));
            $invited->sendMessage($this->plugin->formatMessage("§e* Você foi convidado para $factionName. \n§eUtilize '/f aceitar' para §a§lACEITAR§r§e ou '/f negar' para §c§lNEGAR!", true));
        }

        /////////////////////////////// LEADER ///////////////////////////////
        if (in_array($args[0], ["leader", "lider", "lideranca"])) {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Use: /f lider (jogador)"));
                return true;
            }
            if (!$this->plugin->isInFaction($sender->getName())) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção para usar isso!"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve ser líder para usar isso"));
                return true;
            }
            if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Adicionar jogador à facção primeiro!"));
                return true;
            }
            if (!$this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Jogador não está online!"));
                return true;
            }
            $factionName = $this->plugin->getPlayerFaction($playerName);
            $pp = $this->plugin->getServer()->getPlayerExact($args[1]);
            $all = $pl->getFactionData($factionName);
            $all["data"]["players"][$playerName] = "member";
            $all["data"]["players"][$pp->getName()] = "leader";
            $all["leader"] = $pp->getName();
            $pl->fdata->set(strtolower($factionName), $all);
            $pl->fdata->save();
            $pl->fdata->reload();

            $sender->sendMessage($this->plugin->formatMessage("§c* Você não é mais líder!", true));
            $this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§a* Agora você é o líder \nde $factionName!", true));
            if ($this->plugin->prefs->get("FactionNametags")) {
                $this->plugin->updateTag($sender->getName());
                $this->plugin->updateTag($this->plugin->getServer()->getPlayer($args[1])->getName());
            }
        }

        /////////////////////////////// PROMOTE ///////////////////////////////

        if (in_array($args[0], ["promover", "promote"])) {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Use: /f promover (jogador)"));
                return true;
            }
            if (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção para usar isso!"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "promote")) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não tem permissão para fazer isso"));
                return true;
            }
            if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§c* O jogador não está nesta facção!"));
                return true;
            }
            if ($this->plugin->isOfficer($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* O jogador já é Capitao"));
                return true;
            }
            $promotee = $this->plugin->getServer()->getPlayerExact($args[1]);
            if ($promotee instanceof Player && $promotee->getName() == $sender->getName()) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não pode se promover"));
                return true;
            }
            $factionName = $this->plugin->getPlayerFaction($playerName);
            $all = $pl->getFactionData($factionName);
            $all["data"]["players"][$promotee->getName()] = "officer";
            $pl->fdata->set(strtolower($factionName), $all);
            $pl->fdata->save();
            $pl->fdata->reload();
            $sender->sendMessage($this->plugin->formatMessage("§e* " . $args[1] . " §efoi promovido a Officer!", true));
            if ($promotee instanceof Player) {
                $promotee->sendMessage($this->plugin->formatMessage("§a* Você agora é um oficial!", true));
                if ($this->plugin->prefs->get("FactionNametags")) {
                    $this->plugin->updateTag($promotee->getName());
                }
            }
        }

        /////////////////////////////// DEMOTE ///////////////////////////////

        if (in_array($args[0], ["rebaixar", "demote"])) {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Use: /f rebaixar (jogador)"));
                return true;
            }
            if ($this->plugin->isInFaction($sender->getName()) == false) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção para usar isso!!"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "demote")) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não tem permissão para fazer isso"));
                return true;
            }
            if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§c* O jogador não está nesta facção!"));
                return true;
            }
            if (!$this->plugin->isOfficer($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* O jogador já é membro"));
                return true;
            }
            if (($promotee = $this->plugin->getServer()->getPlayer($args[1])) instanceof Player) {
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $all = $pl->getFactionData($factionName);
                $all["data"]["players"][$promotee->getName()] = "member";
                $pl->fdata->set(strtolower($factionName), $all);
                $pl->fdata->save();
                $pl->fdata->reload();
                $sender->sendMessage($this->plugin->formatMessage("§a* " . $args[1] . " §afoi rebaixado para Membro.", true));
                $demotee->sendMessage($this->plugin->formatMessage("§c* Você foi rebaixado para Membro.", true));
            } else {
                $sender->sendMessage($this->plugin->formatMessage("§a* " . $args[1] . " §aNao Esta Online!"));
            }
            if ($this->plugin->prefs->get("FactionNametags")) {
                $this->plugin->updateTag($demotee->getName());
            }
        }

        /////////////////////////////// KICK ///////////////////////////////

        if (in_array($args[0], ["kick", "expulsar"])) {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Use /f expulsar (jogador)"));
                return true;
            }
            if ($this->plugin->isInFaction($sender->getName()) == false) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção para usar isso!"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "kick")) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não tem permissão para fazer isso"));
                return true;
            }
            if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§c* O jogador não está nesta facção!"));
                return true;
            }
            $factionName = $this->plugin->getPlayerFaction($playerName);
            $this->plugin->removeFromFaction($args[1]);

            $sender->sendMessage($this->plugin->formatMessage("§a* Você expulsou com sucesso $args[1]!", true));
            if (($kicked = $this->plugin->getServer()->getPlayerExact($args[1])) instanceof Player) {
                $kicked->sendMessage($this->plugin->formatMessage("§c* Você foi expulso de \n $factionName!", true));
            }
            return true;
        }

        if (strtolower($args[0]) == 'menu') {
			return false;
            $isInFaction = $this->plugin->isInFaction($playerName);

            $messages = [
                0 => "§r§eSolicitações de Facções\n§7Selecione para ver suas\n§7solicitações.",
                1 => "§r§6Seu Perfil:\n§8- §7Poder:§f " . $this->plugin->getPlayerPower($playerName) . "\n§8- §7Facção:§f " . ($isInFaction ? $this->plugin->getFaction($playerName) : '§cNenhuma'),
                2 => "§r§2Ranking de facções mais ricas do servidor\n§7Selecione para ver o Ranking!",
                3 => "§r§2Ranking de facções com mais poder do servidor\n§7Selecione para ver o Ranking!",
                4 => "§r§cOpções extras\n§7Selecione para configurar suas opções.",
                5 => "§r§cGerenciamento de Permissões\n§7Selecione para gerenciar as permissões\n§7 dos memebros de sua facção.",
                6 => "§r§cAbandonar Facção\n§7Selecione para sair de sua\n§7facção.",
                7 => "§r§cDeletar Facção\n§7Selecione para excluir sua\n§yfacção.",
                64 => "§r§eGeradores\n§7Clique Para Ver Os Geradores Da facção.",
                8 => "§r§eMembros da Facção\n§7Selecione para visualizar os\n§7membros de sua facção."
            ];
            if ($isInFaction) {
                $faction = $this->plugin->getFaction($playerName);

                $rank = $this->plugin->isLeader($playerName) ? 'Lider' : ($this->plugin->isOfficer($playerName) ? 'Capitão' : 'Membro');
                $factionPower = $this->plugin->getFactionPower($faction);
                $power = $this->plugin->getPlayerPower($playerName);

                $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                $desc = $result->fetchArray(SQLITE3_ASSOC)['message'] ?? "§cSem Descrição";

                $leader = $this->plugin->getLeader($faction);

                $messages[9] = "§r§6Perfil:\n§8- §7Poder da Facção:§f " . $factionPower . "\n§8- §7Seu Poder:§f " . $power . "\n§8- §7Seu Cargo:§f " . $rank . "\n§8- §7Lider:§f " . $leader . "\n§8- §7Descrição:§f " . $desc;
            }

            if ($isInFaction) {
                $window = Window::get($sender->asPosition(), 'Menu Da Facção', Window::DOUBLE_WINDOW);
                $window->setItem(Window::getSlot(2, 2), Item::get(397, 3, 1)->setCustomName($messages[8]));
                $window->setItem(Window::getSlot(4, 2), Item::get(342, 0, 1)->setCustomName($messages[0]));
                $item = Item::get(Item::MOB_SPAWNER)->setCustomName($messages[64]);
                $tag = $item->getNamedTag();
                $tag->setString("type", "home");
                $item->setNamedTag($tag);
                $window->setItem(Window::getSlot(2, 5), $item);

                if ($this->plugin->isLeader($playerName)) {
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

                $sender->addWindow($window);
            } else {
                $window = Window::get($sender->asPosition(), $sender->getName(), Window::NORMAL_WINDOW);

                $window->setItem(Window::getSlot(2, 2), Item::get(339, 0, 1)->setCustomName($messages[1]));
                $window->setItem(Window::getSlot(4, 2), Item::get(342, 0, 1)->setCustomName($messages[0]));

                $banner = Item::get(Item::BANNER, 0, 1);
                $banner->correctNBT();

                $banner->setDamage(1);
                $window->setItem(Window::getSlot(7, 2), (clone $banner)->setCustomName($messages[3]));

                $banner->setDamage(10);
                $window->setItem(Window::getSlot(8, 2), (clone $banner)->setCustomName($messages[2]));

                $sender->addWindow($window);
            }
        }

        /////////////////////////////// CLAIM ///////////////////////////////
        if (in_array($args[0], ["verchunk", "chunk"])) {
            if (isset($pl->chunks[$p->getName()])) {
                $pl->chunks[$p->getName()]->cancelled();
                $sender->sendMessage($this->plugin->formatMessage("§c* Visualizaçao De Chunk Desativada."));
                unset($pl->chunks[$p->getName()]);
            } else {
                $task = new \FactionsPro\Task\Chunk($pl, $p);
                $pl->chunks[$p->getName()] = $task;
                $pl->getScheduler()->scheduleRepeatingTask($task, 10);
                $sender->sendMessage($this->plugin->formatMessage("§a* Visualizaçao De Chunk Ativada."));
            }
        }
        if (in_array($args[0], ["claim", "dominar"])) {

            if (!$this->plugin->isInFaction($playerName)) {

                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção."));
                return true;
            }
            if (!$this->plugin->isLeader($playerName)) {

                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve ser líder para usar isso."));
                return true;
            }
            if (!$sender->isOp() and !in_array($sender->getPlayer()->getLevel()->getFolderName(), $this->plugin->prefs->get("ClaimWorlds"))) {

                $sender->sendMessage($this->plugin->formatMessage("§e* Você só pode dominar em Faction Worlds: " . implode(" ", $this->plugin->prefs->get("ClaimWorlds"))));
                return true;
            }

            if (!$this->plugin->iprotector->canEdit($sender, $sender->asPosition())) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Você só pode dominar em áreas livres."));
                return true;
            }

            if ($this->plugin->inOwnPlot($sender)) {
                $sender->sendMessage($this->plugin->formatMessage("§a* Sua facção já dominou esta área."));
                return true;
            }
            $faction = $this->plugin->getPlayerFaction($sender);
            $need = 3;
            $plots = $this->plugin->getFactionData($faction, "plots") == 0 ? 1 : $this->plugin->getFactionData($faction, "plots");
            $have = $this->plugin->getFactionPower($faction);
            $counts = $plots * $need + $need;
            if ($counts > $have) {

                $sender->sendMessage($this->plugin->formatMessage("§c* Sua facção não tem Poder suficiente para dominar essa terra."));
                $sender->sendMessage($this->plugin->formatMessage("§c* $counts Poder é necessário, mas sua facção tem apenas $have ."));
                return true;
            }

            $id = $this->plugin->domineChunk($sender, $faction);
            $messages = [
                0 => "&aTerra Foi Dominada Com Sucesso!",
                1 => "&cVoce Nao Tem Poder Sulficiente Para Claimar A Terra Dessa Faccao!",
                2 => "&cNao Foi Possivel Continuar, Tente Novamente",
                3 => "&cVocê Nao Pode Dominar Esta Area"
            ];
            $sender->sendMessage(TextFormat::colorize($messages[$id]));
        }
        if (strtolower($args[0]) == 'plotinfo') {
            $x = floor($sender->getX());
            $y = floor($sender->getY());
            $z = floor($sender->getZ());
            if (!$this->plugin->isInPlot($sender)) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Esta terra não é dominada por ninguém. Você pode domina-lo digitando /f claim", true));
                return true;
            }

            $fac = $this->plugin->factionFromPoint($x, $z, $sender->getLevel()->getName());
            $power = $this->plugin->getFactionPower($fac);
            $sender->sendMessage($this->plugin->formatMessage("§c* Este terreno é dominado por $fac com $power Poder"));
        }
        // if (strtolower($args[0]) == 'top') {
        //     $this->plugin->sendListOfTop10FactionsTo($sender);
        // }
        if (strtolower($args[0]) == 'forcedelete') {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Use: /f forcedelete (facção)"));
                return true;
            }
            if (!$this->plugin->factionExists($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist."));
                return true;
            }
            if (!($sender->isOp())) {
                $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                return true;
            }
            $this->plugin->deleteFaction($args[1]);
            $sender->sendMessage($this->plugin->formatMessage("Unwanted faction was successfully deleted and their faction plot was unclaimed!", true));
        }
        if (strtolower($args[0]) == 'addstrto') {
            if (!isset($args[1]) or !isset($args[2])) {
                $sender->sendMessage($this->plugin->formatMessage("Usage: /f addstrto (facção) <STR>"));
                return true;
            }
            if (!$this->plugin->factionExists($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist."));
                return true;
            }
            if (!($sender->isOp())) {
                $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                return true;
            }
            $this->plugin->addFactionPower($args[1], $args[2]);
            $sender->sendMessage($this->plugin->formatMessage("Successfully added $args[2] STR to $args[1]", true));
        }

        /////////////////////////////// UNCLAIM ///////////////////////////////

        if (in_array($args[0], ["unclaim", "abandonar"])) {
            if (!$this->plugin->isInFaction($sender->getName())) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção"));
                return true;
            }
            $faction = $this->plugin->getPlayerFaction($sender->getName());
            if (!$this->plugin->isLeader($sender->getName())) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve ser líder para usar isso"));
                return true;
            }
            
            if ($this->plugin->isInAtack($faction)) {
            	$sender->sendMessage($this->plugin->formatMessage("§c* Sua facção está em ataque."));
            	return false;
            }
           	
            try {
	            if ($this->plugin->isInPlot($p) and $this->plugin->inOwnPlot($p)) {
	                $this->plugin->unclaimChunk($p, $this->plugin->getPlayerFaction($p), (isset($args[1]) and $args[1] == "todas"));
	                $p->sendMessage("§aTerreno Removido!");
	            } elseif (isset($args[1]) and $args[1] == "todas") {
	                $this->plugin->unclaimChunk($p, $this->plugin->getPlayerFaction($p), (isset($args[1]) and $args[1] == "todas"));
	                $p->sendMessage("§aTerreno Removidos!");
	            } else {
	                $p->sendMessage("§cVoce Deve Estar No Seu Claim!");
	            }
            } catch (\Exception $except) {
            	$p->sendMessage($except->getMessage());
            }
        }

        /////////////////////////////// DESCRIPTION ///////////////////////////////

        /////////////////////////////// ACCEPT ///////////////////////////////

        if (strtolower($args[0]) == "aceitar") {

            $lowercaseName = strtolower($playerName);
            if (!isset(self::$invites[$lowercaseName])) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não possuí nenhuma solicitação de facção!"));
                return true;
            }
            $faction = self::$invites[$lowercaseName];
            if ($this->plugin->isFactionFull($faction)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* A Faccao Esta Cheia!"));
                return true;
            }
            if ($this->plugin->joinInFaction($sender, $faction)) {
                foreach ($this->plugin->getPlayersFaction($faction, true) as $player) {
                    $player->sendMessage($this->plugin->formatMessage("§2* $playerName juntou-se à facção!", true));
                }

                #$this->plugin->getServer()->getPluginManager()->getPlugin("DailyReward")->setMission($sender, "faction", 1);

                unset(self::$invites[$lowercaseName]);
                return true;
            } else {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve deixar sua facção atual para fazer isso!"));
            }
            return true;
        }

        /////////////////////////////// DENY ///////////////////////////////

        if (strtolower($args[0]) == "negar") {
            $lowercaseName = strtolower($playerName);
            if (!isset(self::$invites[$lowercaseName])) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não possuí nenhuma solicitação de facção!"));
                return true;
            }
            unset(self::$invites[$lowercaseName]);
            $sender->sendMessage($this->plugin->formatMessage("§c* Você Negou O Pedido Da Faccao"));
        }

        /////////////////////////////// DELETE ///////////////////////////////

        if (in_array($args[0], ["del", "deletar"])) {
            if ($this->plugin->isInFaction($playerName) == true) {
                if ($this->plugin->isLeader($playerName)) {
                    $faction = $this->plugin->getPlayerFaction($playerName);
                    $this->plugin->deleteFaction($faction);

                    $sender->sendMessage($this->plugin->formatMessage("§a* Facção dissolvida com sucesso!", true));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("§c* Você não é o líder!"));
                }
            } else {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não está em uma facção!"));
            }
        }

        /////////////////////////////// LEAVE ///////////////////////////////

        if (strtolower($args[0] == "sair")) {
            if ($this->plugin->isInFaction($playerName) == true) {
                $faction = $this->plugin->getFaction($playerName);

                if ($this->plugin->removeFromFaction($sender)) {
                    $sender->sendMessage($this->plugin->formatMessage("§2* Você saiu com sucesso da facção:§f $faction", true));
                    $players = $this->plugin->getPlayersFaction($faction, true);

                    if (count($players) > 0) {
                        foreach ($players as $player) $player->sendMessage($this->plugin->formatMessage("§c* $playerName saiu da facção!", true));
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("§c* Você deve excluir sua facção ou dar liderança primeiro!"));
                }
            } else {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não está em uma facção!"));
            }
        }

        /////////////////////////////// SETHOME ///////////////////////////////

        ////////////////////////////// ALLY SYSTEM ////////////////////////////////
        if (in_array($args[0], ["addaliado", "ally"])) {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Use: /f addaliado (facção)"));
                return true;
            }
            if (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve estar em uma facção para fazer isso"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName) && !$this->plugin->isOfficer($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve ser o líder ou oficial para fazer isso"));
                return true;
            }
            if (!$this->plugin->factionExists($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e A facção solicitada não existe"));
                return true;
            }
            if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                $sender->sendMessage($this->plugin->formatMessage("§c Sua facção não pode se aliar a si mesma"));
                return true;
            }
            if ($this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e Sua facção já está aliada $args[1]"));
                return true;
            }
            $fac = $this->plugin->getPlayerFaction($playerName);
            $leaderName = $this->plugin->getLeader($args[1]);
            if (!isset($fac) || !isset($leaderName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Facção não encontrada"));
                return true;
            }
            $leader = $this->plugin->getServer()->getPlayerExact($leaderName);
            $this->plugin->updateAllies($fac);
            $this->plugin->updateAllies($args[1]);

            if (!($leader instanceof Player)) {
                $sender->sendMessage($this->plugin->formatMessage("§e O líder da facção solicitada está offline"));
                return true;
            }
            if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                $sender->sendMessage($this->plugin->formatMessage("§e A facção solicitada tem a quantidade máxima de aliados", false));
                return true;
            }
            if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                $sender->sendMessage($this->plugin->formatMessage("§c Sua facção tem a quantidade máxima de aliados", false));
                return true;
            }
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
            $stmt->bindValue(":player", $leader->getName());
            $stmt->bindValue(":faction", $args[1]);
            $stmt->bindValue(":requestedby", $sender->getName());
            $stmt->bindValue(":timestamp", time());
            $result = $stmt->execute();
            $sender->sendMessage($this->plugin->formatMessage("§a*Você pediu para se aliar com $args[1]!\n§aAguarde a resposta do líder ...", true));
            $leader->sendMessage($this->plugin->formatMessage("§e* O líder de $fac solicitou uma aliança.\n§eDigite /f aceitarally para §a§lACEITAR§r§e ou /f negarally para §c§lNEGAR§r§e!.", true));
        }
        if (in_array($args[0], ["unally", "tirarally"])) {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("Use: /f delaliado (facção)"));
                return true;
            }
            if (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve estar em uma facção para fazer isso"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName) && !$this->plugin->isOfficer($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve ser o líder ou oficial para fazer isso"));
                return true;
            }
            if (!$this->plugin->factionExists($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§c A facção solicitada não existe"));
                return true;
            }
            if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                $sender->sendMessage($this->plugin->formatMessage("§e Sua facção não pode quebrar a aliança consigo mesma"));
                return true;
            }
            if (!$this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§c Sua facção não está aliada $args[1]"));
                return true;
            }

            $fac = $this->plugin->getPlayerFaction($playerName);
            $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
            $this->plugin->deleteAllies($fac, $args[1]);
            $this->plugin->subtractFactionPower($fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
            $this->plugin->subtractFactionPower($args[1], $this->plugin->prefs->get("PowerGainedPerAlly"));
            $this->plugin->updateAllies($fac);
            $this->plugin->updateAllies($args[1]);
            $sender->sendMessage($this->plugin->formatMessage("§e* Sua facção $fac não é mais aliado a $args[1]", true));
            if ($leader instanceof Player) {
                $leader->sendMessage($this->plugin->formatMessage("§a* O líder de $fac quebrou a aliança com sua facção $args[1]", false));
            }
        }
        if (strtolower($args[0] == "forceunclaim")) {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Use: /f forceunclaim (facção)"));
                return true;
            }
            if (!$this->plugin->factionExists($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§c A facção solicitada não existe"));
                return true;
            }
            if (!($sender->isOp())) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve ser OP para fazer isso."));
                return true;
            }
            $sender->sendMessage($this->plugin->formatMessage("Successfully unclaimed the unwanted plot of $args[1]"));
            $pl->unclaimChunk($sender, $args[1], (isset($args[2]) and $args[2] == "todas"));
        }

        if (strtolower($args[0] == "aceitarally")) {
            if (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve estar em uma facção para fazer isso"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve ser um líder para fazer isso"));
                return true;
            }
            $lowercaseName = strtolower($playerName);
            $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
            $array = $result->fetchArray(SQLITE3_ASSOC);
            if (empty($array) == true) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Sua facção não foi solicitada a se aliar a nenhuma facção"));
                return true;
            }
            $allyTime = $array["timestamp"];
            $currentTime = time();
            if (($currentTime - $allyTime) <= 60) { //This should be configurable
                $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                $sender_fac = $this->plugin->getPlayerFaction($playerName);
                $this->plugin->setAllies($requested_fac, $sender_fac);
                $this->plugin->addFactionPower($sender_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                $this->plugin->addFactionPower($requested_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                $this->plugin->updateAllies($requested_fac);
                $this->plugin->updateAllies($sender_fac);
                $this->plugin->deleteEnemy($requested_fac, $sender_fac);
                $sender->sendMessage($this->plugin->formatMessage("§a* Sua facção se aliou com sucesso $requested_fac", true));
                $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("§a* $playerName a partir de $sender_fac aceitou a aliança!", true));
				$this->plugin->sendMessageToFaction($requested_fac, "§a[ALERTA] O líder de sua facção declarou a facção $sender_fac como aliada.");
				$this->plugin->sendMessageToFaction($sender_fac, "§a[ALERTA] A facção $requested_fac declarou sua facção como aliada.");
            } else {
                $sender->sendMessage($this->plugin->formatMessage("§c* A solicitação expirou"));
                $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
            }
        }
        if (strtolower($args[0]) == "negarally") {
            if (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção para fazer isso"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve ser um líder para fazer isso"));
                return true;
            }
            $lowercaseName = strtolower($playerName);
            $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
            $array = $result->fetchArray(SQLITE3_ASSOC);
            if (empty($array) == true) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Sua facção não foi solicitada a se aliar a nenhuma facção"));
                return true;
            }
            $allyTime = $array["timestamp"];
            $currentTime = time();
            if (($currentTime - $allyTime) <= 60) { //This should be configurable
                $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                $sender_fac = $this->plugin->getPlayerFaction($playerName);
                $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                $sender->sendMessage($this->plugin->formatMessage("Your faction has successfully declined the alliance request.", true));
                $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$playerName from $sender_fac has declined the alliance!"));
				$this->plugin->sendMessageToFaction($requested_fac, "§c[ALERTA] O líder de sua facção declarou a facção $sender_fac como neutra.");
				$this->plugin->sendMessageToFaction($sender_fac, "§c[ALERTA] A facção $requested_fac declarou sua facção como neutra.");
            } else {
                $sender->sendMessage($this->plugin->formatMessage("Request has timed out"));
                $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
            }
        }
		
		//////////////////////////////ENEMY SYSTEM////////////////////////////
		if (in_array($args[0], ["addinimigo", "enemy"])){
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e* Use: /f addinimigo (facção)"));
                return true;
            }
            if (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve estar em uma facção para fazer isso"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName) && !$this->plugin->isOfficer($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve ser o líder ou oficial para fazer isso"));
                return true;
            }
            if (!$this->plugin->factionExists($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e A facção solicitada não existe"));
                return true;
            }
            if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                $sender->sendMessage($this->plugin->formatMessage("§c Sua facção não pode ser inimiga de si mesma"));
                return true;
            }
            if ($this->plugin->areEnemies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§e Sua facção já está é inimiga de $args[1]"));
                return true;
            }
            $fac = $this->plugin->getPlayerFaction($playerName);
            $leaderName = $this->plugin->getLeader($args[1]);
            if (!isset($fac) || !isset($leaderName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Facção não encontrada"));
                return true;
            }
            $requested_fac = strtolower($args[1]);
            $this->plugin->setEnemy($requested_fac, $fac);
            $this->plugin->updateEnemies($requested_fac);
            $this->plugin->updateEnemies($fac);
			$this->plugin->sendMessageToFaction($fac, "§c[ALERTA] O líder de sua facção declarou a facção $args[1] como inimiga.");
			$this->plugin->sendMessageToFaction($args[1], "§c[ALERTA] A facção $fac declarou sua facção como inimiga.");
        }
        if (in_array($args[0], ["unenemy", "tirarinimigo"])) {
            if (!isset($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("Use: /f tirarinimigo (facção)"));
                return true;
            }
            if (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve estar em uma facção para fazer isso"));
                return true;
            }
            if (!$this->plugin->isLeader($playerName) && !$this->plugin->isOfficer($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c Você deve ser o líder ou oficial para fazer isso"));
                return true;
            }
            if (!$this->plugin->factionExists($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§c A facção solicitada não existe"));
                return true;
            }
            if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                $sender->sendMessage($this->plugin->formatMessage("§e Sua facção não pode fazer isso consigo mesma"));
                return true;
            }
            if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("§c Sua facção não é inimiga de $args[1]"));
                return true;
            }

            $fac = $this->plugin->getPlayerFaction($playerName);
            $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
            $this->plugin->deleteEnemy($fac, $args[1]);
            $this->plugin->updateEnemies($fac);
            $this->plugin->updateEnemies($args[1]);
            $this->plugin->sendMessageToFaction($fac, "§c[ALERTA] O líder de sua facção declarou a facção $args[1] como neutra.");
			$this->plugin->sendMessageToFaction($args[1], "§c[ALERTA] A facção $fac declarou sua facção como neutra.");
        }

        /////////////////////////////// ABOUT ///////////////////////////////

        if (strtolower($args[0] == 'about')) {
            $sender->sendMessage("§eTethered_");
            $sender->sendMessage("§e@HeyDeniis");
            $sender->sendMessage("§e@DanielYTK");
        }
        ////////////////////////////// CHAT ////////////////////////////////
        if (strtolower($args[0]) == "chat" or strtolower($args[0]) == "c") {

            if (!$this->plugin->prefs->get("AllowChat")) {
                $sender->sendMessage($this->plugin->formatMessage("§cTodo o bate-papo da facção está desativado", false));
                return true;
            }

            if ($this->plugin->isInFaction($playerName)) {
                if (isset($this->plugin->factionChatActive[$playerName])) {
                    unset($this->plugin->factionChatActive[$playerName]);
                    $sender->sendMessage($this->plugin->formatMessage("§cBate-papo de facção desativado", false));
                    return true;
                } else {
                    $this->plugin->factionChatActive[$playerName] = 1;
                    $sender->sendMessage($this->plugin->formatMessage("§aChat de facção ativado", false));
                    return true;
                }
            } else {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não está em uma facção"));
                return true;
            }
        }

        if (strtolower($args[0]) === "fly" or strtolower($args[0]) === "voar") {
            if (!$sender->hasPermission('fly.claim')) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Desculpe, mas apenas VIPs tem acesso a este comando!"));
                return true;
            }
            if ($this->plugin->isInFaction($playerName)) {
                if(AttackTimer::isInAttack($this->plugin->getPlayerFaction($sender))) {
                    $sender->sendMessage("§cSua facção está sendo atacada.");
                    return true;
                }

                if (isset($this->plugin->factionFly[$playerName])) {
                    unset($this->plugin->factionFly[$playerName]);
                    $sender->sendMessage($this->plugin->formatMessage("§c* Vôo automático em seu terreno desativado com sucesso", false));

                    if ($sender->getAllowFlight() === true and $sender->isSurvival()) {
                        $sender->setAllowFlight(false);
                        $sender->setFlying(false);

                    }
                    return true;
                } else {

                    $this->plugin->factionFly[$sender->getName()] = 1;
                    $sender->setAllowFlight(true);
                    $sender->setFlying(true);
                    $sender->sendMessage($this->plugin->formatMessage("§2* Vôo automático em seu terreno ativado com sucesso", false));
                    return true;
                }
            } else {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não está em uma facção"));
                return true;
            }
        }

        if (strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac") {

            if (!$this->plugin->prefs->get("AllowChat")) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Todo o bate-papo da facção está desativado", false));
                return true;
            }

            if ($this->plugin->isInFaction($playerName)) {
                if (isset($this->plugin->allyChatActive[$playerName])) {
                    unset($this->plugin->allyChatActive[$playerName]);
                    $sender->sendMessage($this->plugin->formatMessage("§cBate-papo aliado desativado", false));
                    return true;
                } else {
                    $this->plugin->allyChatActive[$playerName] = 1;
                    $sender->sendMessage($this->plugin->formatMessage("§aBate-papo aliado ativado", false));
                    return true;
                }
            } else {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você não está em uma facção"));
                return true;
            }
        }

        /////////////////////////////// INFO ///////////////////////////////

        if (strtolower($args[0]) == 'info') {
            $faction = null;

            if (isset($args[1])) {
                if (!(ctype_alnum($args[1])) or !($this->plugin->factionExists($args[1]))) {
                    $sender->sendMessage($this->plugin->formatMessage("§c* Facção não existe"));
                    $sender->sendMessage($this->plugin->formatMessage("§c* Verifique se o nome da facção selecionada é ABSOLUTAMENTE EXATO."));
                    return true;
                }
                $faction = $args[1];

            } elseif (!$this->plugin->isInFaction($playerName)) {
                $sender->sendMessage($this->plugin->formatMessage("§c* Você deve estar em uma facção para usar isso!"));
                return true;
            } else {
                $faction = $this->plugin->getPlayerFaction(($sender->getName()));
            }
            $this->plugin->getFactionInfo($faction, $sender);
            return true;
        }
        if (strtolower($args[0]) == "map" or strtolower($args[0]) == "mapa") {
            $map = new \FactionsPro\map\Map($this->plugin, 16, 26);
            $map->sendMapTo($sender);
        }
        return true;
    }
}