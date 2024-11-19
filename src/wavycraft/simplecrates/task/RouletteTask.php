<?php

declare(strict_types=1);

namespace wavycraft\simplecrates\task;

use pocketmine\Server;

use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;

use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;

use pocketmine\math\Vector3;

use pocketmine\world\Position;
use pocketmine\world\particle\BlockBreakParticle;

use pocketmine\entity\Entity;

use pocketmine\player\Player;

use pocketmine\scheduler\Task;

use pocketmine\utils\TextFormat;

use wavycraft\simplecrates\utils\RewardManager;

use wavycraft\core\utils\SoundUtils;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;

class RouletteTask extends Task {

    const INVENTORY_ROW_COUNT = 9;
    
    private Player $player;
    
    private InvMenu $menu;

    private Position $cratePosition;
    
    private int $currentTick = 0;
    private int $itemsLeft;
    
    private bool $showReward = false;
    public bool $isOpen = false;
    
    private array $lastRewards = [];
    
    private string $crateType;

    public function __construct(Player $player, Position $cratePosition, string $crateType) {
        $this->player = $player;
        $this->crateType = $crateType;
        $this->cratePosition = $cratePosition;
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
        $this->menu->setName("Opening a " . ucfirst($crateType) . " Crate");
        $this->menu->getInventory()->setContents([
            0 => ($vine = VanillaBlocks::VINES()->asItem()->setCustomName(TextFormat::ITALIC)),
            1 => $vine,
            2 => $vine,
            3 => $vine,
            4 => ($endRod = VanillaBlocks::END_ROD()->asItem()->setCustomName(TextFormat::ITALIC)),
            5 => $vine,
            6 => $vine,
            7 => $vine,
            8 => $vine,
            18 => $vine,
            19 => $vine,
            20 => $vine,
            21 => $vine,
            22 => $endRod,
            23 => $vine,
            24 => $vine,
            25 => $vine,
            26 => $vine
        ]);
        $this->menu->setListener(InvMenu::readonly());
        $this->menu->send($player);
        $this->itemsLeft = $this->getDropCount();
    }

    public function onRun() : void{
        if (!$this->player->isOnline()) {
            $this->closeCrate();
            if (($handler = $this->getHandler()) !== null) $handler->cancel();
            return;
        }

        $this->currentTick++;
        $speed = 3;
        $safeSpeed = max($speed, 1);
        $duration = 80;
        $safeDuration = (($duration / $safeSpeed) >= 5.5) ? $duration : (5.5 * $safeSpeed);

        if ($this->currentTick >= $safeDuration) {
            if (!$this->showReward) {
                $this->showReward = true;
            } elseif ($this->currentTick - $safeDuration > 20) {
                $this->itemsLeft--;
                $reward = $this->lastRewards[floor(self::INVENTORY_ROW_COUNT / 2)];

                if ($reward['type'] === "item") {
                    $this->player->getInventory()->addItem($reward['item']);
                }

                if ($this->itemsLeft === 0) {
                    $this->player->removeCurrentWindow();
                    SoundUtils::getInstance()->playSound($this->player, "random.levelup");
                    $prizeMessage = "§l§f(§a!§f)§r§f Player " . $this->player->getName() . " has won §b" . ($reward['item'])->getName() . "§f from a " . $this->crateType . " crate!";
                    Server::getInstance()->broadcastMessage($prizeMessage);

                    $this->sendLightning(new Position($this->cratePosition->getX(), $this->cratePosition->getY() + 1, $this->cratePosition->getZ(), $this->cratePosition->getWorld()));
                    $this->closeCrate();
                    if (($handler = $this->getHandler()) !== null) $handler->cancel();
                } else {
                    $reward = RewardManager::getInstance()->givePrize($this->player, $this->crateType);
                    if ($reward !== null && $reward['type'] === "item") {
                        $this->player->getInventory()->addItem($reward['item']);
                    }
                    $this->currentTick = 0;
                    $this->showReward = false;
                }
            }
            return;
        }

        if ($this->currentTick % $safeSpeed === 0) {
            $drop = RewardManager::getInstance()->givePrize($this->player, $this->crateType);
            $this->lastRewards[self::INVENTORY_ROW_COUNT] = $drop;

            foreach ($this->lastRewards as $slot => $lastReward) {
                if ($slot !== 0) {
                    $this->lastRewards[$slot - 1] = $lastReward;
                    $this->menu->getInventory()->setItem($slot + self::INVENTORY_ROW_COUNT - 1, $lastReward['item']);
                    SoundUtils::getInstance()->playSound($this->player, "random.orb");
                }
            }
        }
    }

    public function getDropCount() : int{
        return 1;
    }

    public function closeCrate() {
        if (!$this->isOpen) return;
        $this->isOpen = false;
        $this->player = null;
    }

    public function sendLightning(Position $position) {
        $packet = new AddActorPacket();
        $packet->actorUniqueId = Entity::nextRuntimeId();
        $packet->actorRuntimeId = 1;
        $packet->position = $position->asVector3();
        $packet->type = EntityIds::LIGHTNING_BOLT;
        $packet->yaw = $this->player->getLocation()->getYaw();
        $packet->syncedProperties = new PropertySyncData([], []);
        
        $sound = PlaySoundPacket::create("ambient.weather.thunder", $position->getX(), $position->getY(), $position->getZ(), 100, 1);
        NetworkBroadcastUtils::broadcastPackets($position->getWorld()->getPlayers(), [$packet, $sound]);

        $block = VanillaBlocks::CHEST();
        $particle = new BlockBreakParticle($block);
        $position->getWorld()->addParticle($position, $particle);
    }
}
