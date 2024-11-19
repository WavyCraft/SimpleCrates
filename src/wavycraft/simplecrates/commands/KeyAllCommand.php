<?php

declare(strict_types=1);

namespace wavycraft\simplecrates\commands;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use pocketmine\Server;

use wavycraft\simplecrates\utils\KeyManager;

class KeyAllCommand extends Command {

    public function __construct() {
        parent::__construct("keyall");
        $this->setDescription("Give a crate key to all players");
        $this->setPermission("simplecrates.keyall");
    }

    public function execute(CommandSender $sender, string $label, array $args) : bool{
        if (!$this->testPermission($sender)) {
            return false;
        }

        $crateType = $args[0] ?? null;
        if ($crateType === null) {
            $sender->sendMessage("§l§f(§4!§f)§r§f Please specify a crate type!");
            return false;
        }

        $amount = isset($args[1]) && is_numeric($args[1]) ? max(1, (int)$args[1]) : 1;
        KeyManager::getInstance()->giveCrateKeyAll($crateType, $amount);

        $sender->sendMessage("§l§f(§a!§f)§r§f You have given §a" . number_format($amount) . " " . ucfirst($crateType) . " crate keys §fto everyone on the server!");

        Server::getInstance()->broadcastMessage("§f[§bSimpleCrates§f] Everyone has been given §a" . number_format($amount) . " " . ucfirst($crateType) . " crate keys §f by §e" . $sender->getName() . "§f!");
        return true;
    }
}
