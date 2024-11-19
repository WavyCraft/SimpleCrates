<?php

declare(strict_types=1);

namespace wavycraft\simplecrates\utils;

use pocketmine\Server;

use pocketmine\player\Player;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;

use pocketmine\utils\SingletonTrait;

use pocketmine\nbt\tag\StringTag;

use wavycraft\simplecrates\Loader;

final class KeyManager {
    use SingletonTrait;

    public function getCrateKeyItem(string $crateType): ?Item {
        $config = Loader::getInstance()->getConfig();
        $crateConfig = $config->get("crates", []);

        if (!isset($crateConfig[$crateType])) {
            return null;
        }

        $item = StringToItemParser::getInstance()->parse($crateConfig[$crateType]["key"]);
        if ($item === null) {
            return null;
        }

        $item->setCustomName($crateConfig[$crateType]["key_name"]);
        $item->setLore($crateConfig[$crateType]["key_lore"]);
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 3));

        $nbt = $item->getNamedTag();
        $nbt->setTag("Key", new StringTag($crateType));
        $item->setNamedTag($nbt);

        return $item;
    }

    public function giveCrateKey(Player $player, string $crateType, int $amount = 1): void {
        $crateKey = $this->getCrateKeyItem($crateType);
        if ($crateKey === null) {
            $player->sendMessage("§l§f(§4!§f)§r§f Invalid crate type '$crateType'!");
            return;
        }

        $crateKey->setCount($amount);
        $player->getInventory()->addItem($crateKey);
    }

    public function giveCrateKeyAll(string $crateType, int $amount = 1): void {
        $crateKey = $this->getCrateKeyItem($crateType);
        if ($crateKey === null) {
            Server::getInstance()->broadcastMessage("§l§f(§4!§f)§r§f Invalid crate type '$crateType'!");
            return;
        }

        $crateKey->setCount($amount);
        foreach (Server::getInstance()->getOnlinePlayers() as $p) {
            $p->getInventory()->addItem($crateKey);
        }
    }
}
