<?php

declare(strict_types=1);

namespace wavycraft\simplecrates\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\player\Player;

use wavycraft\simplecrates\utils\CrateManager;

class CreateCrateCommand extends Command {

    public function __construct() {
        parent::__construct("createcrate");
        $this->setDescription("Create a crate by right-clicking or left-clicking a chest");
        $this->setUsage("Usage: /createcrate <type>");
        $this->setPermission("simplecrates.createcrate");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return;
        }

        if (!$this->testPermission($sender)) return;

        if (count($args) < 1) {
            $sender->sendMessage($this->getUsage());
            return;
        }

        $type = strtolower($args[0]);
        CrateManager::getInstance()->startCrateCreation($sender, $type);
    }
}
