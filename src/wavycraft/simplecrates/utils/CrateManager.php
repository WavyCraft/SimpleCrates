<?php

declare(strict_types=1);

namespace wavycraft\simplecrates\utils;

use pocketmine\player\Player;

use pocketmine\block\Block;

use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat as TextColor;

use pocketmine\world\Position;

use wavycraft\simplecrates\Loader;

use wavycraft\simplecrates\task\RouletteTask;

use wavycraft\core\utils\SoundUtils;

final class CrateManager {
    use SingletonTrait;

    private $creatingCrate = [];
    private $removingCrate = [];
    private $lavaParticleTasks = [];

    public function isCrateBlock(Block $block) : bool {
        $crateConfig = new Config(Loader::getInstance()->getDataFolder() . "crate_locations.json", Config::JSON);

        foreach ($crateConfig->getAll() as $coordinates) {
            if (
                isset($coordinates["x"], $coordinates["y"], $coordinates["z"], $coordinates["world"]) &&
                $coordinates["x"] === $block->getPosition()->getX() &&
                $coordinates["y"] === $block->getPosition()->getY() &&
                $coordinates["z"] === $block->getPosition()->getZ() &&
                $coordinates["world"] === $block->getPosition()->getWorld()->getFolderName()
            ) {
                return true;
            }
        }
        
        return false;
    }

    public function getCratePositionByType(string $crateType) : ?Position{
        $crateConfig = new Config(Loader::getInstance()->getDataFolder() . "crate_locations.json", Config::JSON);
    
        foreach ($crateConfig->getAll() as $coordinates) {
            if (isset($coordinates["type"]) && $coordinates["type"] === $crateType) {
                return new Position(
                    (float)$coordinates["x"],
                    (float)$coordinates["y"],
                    (float)$coordinates["z"],
                    Loader::getInstance()->getServer()->getWorldManager()->getWorldByName($coordinates["world"])
                );
            }
        }

        return null;
    }

    public function getCrateTypeByPosition(Position $position): ?string {
        $crateConfig = new Config(Loader::getInstance()->getDataFolder() . "crate_locations.json", Config::JSON);
    
        foreach ($crateConfig->getAll() as $crateType => $coordinates) {
            if (
                isset($coordinates["x"], $coordinates["y"], $coordinates["z"], $coordinates["world"]) &&
                $coordinates["x"] === $position->getX() &&
                $coordinates["y"] === $position->getY() &&
                $coordinates["z"] === $position->getZ() &&
                $coordinates["world"] === $position->getWorld()->getFolderName()
            ) {
                return $coordinates["type"] ?? null;
            }
        }

        return null;
    }

    public function openCrate(Player $player, string $crateType) {
        $inventory = $player->getInventory();
        $crateKeyFound = false;
        $cratePosition = null;

        foreach ($inventory->getContents() as $slot => $item) {
            $nbt = $item->getNamedTag();

            if (($nbt->getTag("Key") !== null) && $nbt->getString("Key") === $crateType) {
                if ($item->getCount() > 1) {
                    $item->setCount($item->getCount() - 1);
                    $inventory->setItem($slot, $item);
                } else {
                    $inventory->clear($slot);
                }
                $crateKeyFound = true;
                break;
            }
        }

        if ($crateKeyFound) {
            $cratePosition = $this->getCratePositionByType($crateType);

            if ($cratePosition === null) {
                $player->sendMessage(TextColor::RED . "Crate location not found for $crateType.");
                return;
            }

            $task = new RouletteTask($player, $cratePosition, $crateType);
            Loader::getInstance()->getScheduler()->scheduleRepeatingTask($task, 1);

            $player->sendMessage("You opened a $crateType crate!");
            SoundUtils::getInstance()->playSound($player, "random.levelup");
        } else {
            $player->sendMessage(TextColor::RED . "You need a $crateType key to open this crate.");
        }
    }

    public function startCrateCreation(Player $player, string $type) {
        $crateConfig = Loader::getInstance()->getConfig();
        $crateKeys = $crateConfig->get("crates", []);

        if (!isset($crateKeys[$type])) {
            $player->sendMessage(TextColor::RED . "Crate type '$type' does not exist!");
            return;
        }

        $this->creatingCrate[$player->getName()] = $type;
        $player->sendMessage(TextColor::GREEN . "Right-click or left-click a chest block to create a " . ucfirst($type) . " crate.");
    }

    public function isCreatingCrate(Player $player): bool {
        return isset($this->creatingCrate[$player->getName()]);
    }

    public function finishCrateCreation(Player $player, Position $position) {
        $playerName = $player->getName();
        if (!isset($this->creatingCrate[$playerName])) return;

        $crateType = $this->creatingCrate[$playerName];
        unset($this->creatingCrate[$playerName]);

        $crateConfig = Loader::getInstance()->getConfig();
        $crateKeys = $crateConfig->get("crates", []);
        $floatingText = $crateKeys[$crateType]["crate_floating_text"] ?? "§l§e" . ucfirst($crateType) . " Crate\nYou need a key to open!";

        $crateLocations = new Config(Loader::getInstance()->getDataFolder() . "crate_locations.json", Config::JSON);
        $crateLocations->set($crateType . "_crate", [
            "x" => $position->getX(),
            "y" => $position->getY(),
            "z" => $position->getZ(),
            "world" => $position->getWorld()->getFolderName(),
            "type" => $crateType,
            "particle" => true
        ]);
        $crateLocations->save();

        FloatingText::create(
            new Position($position->getX() + 0.5, $position->getY() + 1, $position->getZ() + 0.5, $position->getWorld()),
            "{$crateType}_crate_floating_text",
            $floatingText
        );

        $player->sendMessage(TextColor::GREEN . ucfirst($crateType) . " crate created successfully!");
        SoundUtils::getInstance()->playSound($player, "random.pop");
    }

    public function isValidCrateType(string $type): bool {
        $crateConfig = Loader::getInstance()->getConfig();
        $crateKeys = $crateConfig->get("crates", []);
        return isset($crateKeys[$type]);
    }

    public function startCrateRemoval(Player $player, string $type) {
        $this->removingCrate[$player->getName()] = $type;
        $player->sendMessage(TextColor::GREEN . "Crate removal mode enabled for $type crate. Interact with a crate to remove it.");
    }

    public function isRemovingCrate(Player $player): bool {
        return isset($this->removingCrate[$player->getName()]);
    }

    public function removeCrate(Position $position, Player $player) {
        $playerName = $player->getName();
        $crateType = $this->removingCrate[$playerName] ?? null;
    
        if ($crateType === null) {
            $player->sendMessage(TextColor::RED . "Crate type not set...");
            return;
        }

        $foundCrateType = $this->getCrateTypeByPosition($position);
    
        if ($foundCrateType === $crateType) {
            $crateLocations = new Config(Loader::getInstance()->getDataFolder() . "crate_locations.json", Config::JSON);
        
            $crateLocations->remove($crateType . "_crate");
            $crateLocations->save();

            FloatingText::remove("{$crateType}_crate_floating_text");

            $player->sendMessage(TextColor::GREEN . ucfirst($crateType) . " crate removed successfully!");
            $this->finishCrateRemoval($player);
        } else {
            $player->sendMessage(TextColor::RED . "Thats not a $crateType crate!");
        }
    }
    
    public function finishCrateRemoval(Player $player) {
        unset($this->removingCrate[$player->getName()]);
        $player->sendMessage(TextColor::GREEN . "Successfully removed the crate!");
    }   
}
