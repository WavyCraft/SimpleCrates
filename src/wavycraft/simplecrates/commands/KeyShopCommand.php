<?php

declare(strict_types=1);

namespace wavycraft\simplecrates\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\player\Player;

use pocketmine\utils\TextFormat as TextColor;

use wavycraft\simplecrates\Loader;

use wavycraft\simplecrates\utils\KeyManager;

use wavycraft\core\economy\MoneyManager;

use core\form\SimpleForm;
use core\form\CustomForm;

class KeyShopCommand extends Command {
    
    private $plugin;

    public function __construct() {
        parent::__construct("keyshop");
        $this->setDescription("Open the key shop");
        $this->setPermission("simplecrates.keyshop");

        $this->plugin = Loader::getInstance();
    }

    public function execute(CommandSender $sender, string $label, array $args) : bool{
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextColor::RED . "This command can only be used in-game.");
            return false;
        }

        if (!$this->testPermission($sender)) {
            return false;
        }

        $this->openKeyShop($sender);
        return true;
    }

    private function openKeyShop(Player $player) {
        $config = $this->plugin->getConfig();
        $crateConfig = $config->get("crates", []);
        
        $form = new SimpleForm(function (Player $player, ?int $data) use ($crateConfig) {
            if ($data === null) {
                return;
            }

            $crateTypes = array_keys($crateConfig);
            if (!isset($crateTypes[$data])) {
                $player->sendMessage(TextColor::RED . "Invalid crate type...");
                return;
            }

            $selectedCrateType = $crateTypes[$data];
            $this->openBuyAmountForm($player, $selectedCrateType);
        });

        $form->setTitle(TextColor::BOLD . TextColor::ITALIC . "Crate Key Shop");
        $form->setContent("Select a crate key to purchase:");

        foreach ($crateConfig as $crateType => $crateData) {
            $keyName = $crateData["key_name"] ?? $crateType;
            $price = $crateData["price"];
            $form->addButton($keyName . TextColor::EOL . TextColor::RESET . TextColor::DARK_GRAY . "Price: " . TextColor::DARK_GRAY . "$" . number_format($price));
        }

        $player->sendForm($form);
    }

    private function openBuyAmountForm(Player $player, string $crateType) {
        $config = $this->plugin->getConfig();
        $crateConfig = $config->get("crates", []);
        $pricePerKey = $crateConfig[$crateType]["price"];
        
        $form = new CustomForm(function (Player $player, ?array $data) use ($crateType, $pricePerKey) {
            if ($data === null) {
                return;
            }

            $amount = (int) ($data[1] ?? 1);
            if ($amount <= 0) {
                $player->sendMessage(TextColor::RED . "Invalid amount...");
                return;
            }

            $totalCost = $pricePerKey * $amount;
            $moneyManager = MoneyManager::getInstance();

            if ($moneyManager->getBalance($player) < $totalCost) {
                $player->sendMessage(TextColor::RED . "You do not have enough money... You need $" . number_format($totalCost) . " to purchase " . number_format($amount) . " keys!");
                return;
            }

            $moneyManager->removeMoney($player, $totalCost);
            KeyManager::getInstance()->giveCrateKey($player, $crateType, $amount);
            $player->sendMessage(TextColor::GREEN . "You have purchased " . number_format($amount) . " " . ucfirst($crateType) . " keys for $" . number_format($totalCost) . "!");
        });

        $balance = (int) MoneyManager::getInstance()->getBalance($player);
        $form->setTitle(TextColor::BOLD . TextColor::ITALIC . "Buy Key");
        $form->addLabel("Enter the amount of keys you want to buy for crate: " . ucfirst($crateType) . TextColor::EOL . TextColor::EOL . "Price per key: " . TextColor::GREEN . "$" . number_format($pricePerKey) . TextColor::EOL . TextColor::EOL . TextColor::WHITE . "Current balance: " . TextColor::YELLOW . "$" . number_format($balance));
        $form->addInput("Amount:", "enter a positive number...");
        $player->sendForm($form);
    }
}
