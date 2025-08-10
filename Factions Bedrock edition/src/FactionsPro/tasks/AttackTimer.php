<?php


namespace FactionsPro\tasks;

use pocketmine\Player;
use pocketmine\scheduler\Task;
use FactionsPro\FactionMain;

class AttackTimer extends Task
{

    private static $attacks = [];

    public static function isInAttack(string $faction): bool
    {
        return isset(self::$attacks[$faction]);
    }

    public static function get(string $faction)
    {
        if (self::isInAttack($faction)) {
            return self::$attacks[$faction];
        }
        return null;
    }

    public static function send(FactionMain $main, string $faction): bool
    {
        if (self::isInAttack($faction) === false) {
            self::$attacks[$faction] = $main->getScheduler()->scheduleRepeatingTask(new AttackTimer($main, $faction), 5);
            return true;
        } else {
            self::get($faction)->getTask()->resetTime();
        }
        return false;
    }

    private $faction;
    private $main;

    private $time = 0;

    private function __construct(FactionMain $main, string $faction)
    {
        $this->faction = $faction;
        $this->main = $main;
    }

    public function onRun($timer)
    {
        //echo "TEMPO ". $this->time . PHP_EOL;
        if (++$this->time <= 1200) {
            $players = $this->main->getPlayersFaction($this->faction, true);

            if (count($players) > 0) {
                $msg = "Sua fac01o est2 sob ataque!";
                $pos = (int)($this->time % strlen($msg));

                $finalMessage = "§c" . substr($msg, 0, $pos) . "§4" . substr($msg, $pos, 1) . "§c" . substr($msg, $pos + 1);
                $finalMessage = str_replace(['0', '1', '2'], ['ç', 'ã', 'á'], $finalMessage); //fix color errors with accented letters

                foreach ($players as $player) {
                    $player->sendPopup($finalMessage);

                    if (isset($this->main->factionFly[$player->getName()])) {
                        $player->setAllowFlight(false);
                        $player->setFlying(false);
                        unset($this->main->factionFly[$player->getName()]);
                    }
                }
            }
        } else {
            $this->getHandler()->cancel();
            unset(self::$attacks[$this->faction]);
            foreach ($this->main->atacks as $pos => $fac) {
                if (strtolower($fac) == strtolower($this->faction)) {
                    unset($this->main->atacks[$pos]);
                }
            }
        }
    }

    public function resetTime(): void
    {
        $this->time = 0;
    }

}