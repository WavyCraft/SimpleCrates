<?php

declare(strict_types=1);

namespace wavycraft\simplecrates\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\player\Player;

use pocketmine\utils\TextFormat as TextColor;

use wavycraft\simplecrates\utils\CrateManager;

class RemoveCrateCommand extends Command {

    public function __construct() {
        parent::__construct("removecrate");
        $this->setDescription("Enter crate removal mode for a specific crate type");
        $this->setUsage("/removecrate <type>");
        $this->setPermission("simplecrates.removecrate");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
        if (!$this->testPermission($sender)) {
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage(TextColor::RED . "This command can only be used in-game.");
            return false;
        }

        if (empty($args[0])) {
            $sender->sendMessage(TextColor::RED . "Please specify the crate type to remove. Usage: /removecrate <type>");
            return false;
        }

        $crateType = strtolower($args[0]);

        if (!CrateManager::getInstance()->isValidCrateType($crateType)) {
            $sender->sendMessage(TextColor::RED . "Invalid crate type: $crateType");
            return false;
        }

        CrateManager::getInstance()->startCrateRemoval($sender, $crateType);
        return true;
    }
}
