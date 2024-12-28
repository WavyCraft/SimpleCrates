<?php

declare(strict_types=1);

namespace wavycraft\simplecrates;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;

use pocketmine\block\BlockTypeIds;

use pocketmine\player\Player;

use pocketmine\world\Position;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TextColor;
use pocketmine\utils\SingletonTrait;

use wavycraft\simplecrates\utils\FloatingText;
use wavycraft\simplecrates\utils\CrateManager;
use wavycraft\simplecrates\utils\RewardManager;

use core\utils\SoundUtils;

class EventListener implements Listener {
    use SingletonTrait;

    private $plugin;
    private $cooldowns = [];

    public function __construct() {
        $this->plugin = Loader::getInstance();
    }

    public function onPlace(BlockPlaceEvent $event) {
        $item = $event->getItem();
        $nbt = $item->getNamedTag();

        if ($nbt->getTag("Key")) {
            $event->cancel();
        }
    }

    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if (CrateManager::getInstance()->isCrateBlock($block)) {
            $player->sendMessage(TextColor::RED . "You may not break the crate!");
            SoundUtils::getInstance()->playSound($player, "note.bass");
            $event->cancel();
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $blockPos = $block->getPosition();
        $crateManager = CrateManager::getInstance();

        if ($crateManager->isRemovingCrate($player)) {
            $crateManager->removeCrate($blockPos, $player);
            SoundUtils::getInstance()->playSound($player, "random.pop");
            $event->cancel();
            return;
        }

        if ($crateManager->isCreatingCrate($player)) {
            if ($block->getTypeId() === BlockTypeIds::CHEST || $block->getTypeId() === BlockTypeIds::TRAPPED_CHEST || $block->getTypeId() === BlockTypeIds::ENDER_CHEST) {
                $crateManager->finishCrateCreation($player, $blockPos);
                $event->cancel();
                } else {
                $player->sendMessage(TextColor::RED . "You need to interact with a §echest or ender chest§c to create a crate...");
            }
            return;
        }

        if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            if ($crateManager->isCrateBlock($block)) {
                $crateType = $crateManager->getCrateTypeByPosition($blockPos);

                if ($crateType !== null) {
                    $itemInHand = $player->getInventory()->getItemInHand();
                    $nbt = $itemInHand->getNamedTag();
                
                    if ($nbt->getTag("Key") !== null && $nbt->getString("Key") === $crateType) {
                        $crateManager->openCrate($player, $crateType);
                        $this->cooldowns[$player->getName()] = time();
                    } else {
                        RewardManager::getInstance()->previewCrate($player, $crateType);
                        $player->sendMessage(TextColor::YELLOW . "Previewing contents of the " . ucfirst($crateType) . " crate...");
                        SoundUtils::getInstance()->playSound($player, "random.orb");
                    }

                    $event->cancel();
                } else {
                    $player->sendMessage(TextColor::RED . "Unknown crate type...");
                }
            }
        }
    }

    public function onChunkLoad(ChunkLoadEvent $event) {
        FloatingText::loadFromFile($this->plugin->getDataFolder() . "floating_text.json");
}


    public function onChunkUnload(ChunkUnloadEvent $event) {
        FloatingText::saveFile();
    }

    public function onWorldUnload(WorldUnloadEvent $event) {
        FloatingText::saveFile();
    }

    public function onEntityTeleport(EntityTeleportEvent $event) {
        $entity = $event->getEntity();

        if ($entity instanceof Player) {
            $fromWorld = $event->getFrom()->getWorld();
            $toWorld = $event->getTo()->getWorld();

            if ($fromWorld !== $toWorld) {
                foreach (FloatingText::$floatingText as $tag => [$position, $floatingText]) {
                    if ($position->getWorld() === $fromWorld) {
                        FloatingText::makeInvisible($tag);
                    }
                }
            }
        }
    }
}
