<?php

namespace BayAlper10\EggWarsTeam;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskScheduler;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\command\{CommandSender, Command};
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\{Player, Server};
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;

use BayAlper10\EggWarsTeam\npc\EWEntity;
use BayAlper10\EggWarsTeam\formapi\SimpleForm;
use BayAlper10\EggWarsTeam\Resetmap;
use BayAlper10\EggWarsTeam\RefreshArena;

class EWMain extends PluginBase implements Listener{

  public $mode = 0;
  public $arenas = array();
  public $currentLevel = "";
  public $reds = [];
  public $blues = [];
  public $yellows = [];
  public $greens = [];
  public static $instance;
  private $EWEntity;

  public function onEnable(): void{
    $this->getLogger()->info("§aEggWars eklentisi aktif");
    $this->getServer()->getPluginManager()->registerEvents($this, $this);

    Entity::registerEntity(EWEntity::class, true);
    self::$instance = $this;

    $this->win = new Config($this->getDataFolder()."win.yml", Config::YAML);

    $this->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 20);
    $this->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);

    @mkdir($this->getDataFolder());
    $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    if($config->get("arenas") != null){
      $this->arenas = $config->get("arenas");
    }
    foreach($this->arenas as $lev){
      $this->getServer()->loadLevel($lev);
    }
    $config->save();
  }

  public function topWin($p){
      $player = $p->getPlayer();
      $swallet = $this->win->getAll();
      $c = count($swallet);
      $message = "";
      $top = "§aEn Çok Kazananlar";
      arsort($swallet);
      $i = 1;
      foreach($swallet as $name => $amount){
        $message .= "§b ".$i.". §7".$name."  §egalibiyet  §f".$amount."\n";
        if($i > 9){
          break;
        }
        ++$i;
      }
      $x = $this->getConfig()->get("win-x");
      $y = $this->getConfig()->get("win-y");
      $z = $this->getConfig()->get("win-z");
      $p = new FloatingTextParticle(new Vector3($x, $y + 1, $z), $message, $top);
  		$player->getLevel()->addParticle($p);
    }

  public function spawnNPC(Player $player): void{
    $nbt = Entity::createBaseNBT($player, null, $player->getYaw(), $player->getPitch());
    $skinTag = $player->namedtag->getCompoundTag("Skin");
    assert($skinTag !== null);
    $nbt->setTag(clone $skinTag);
    $nametag = "§aMarket";

    $entity = Entity::createEntity("EWEntity", $player->getLevel(), $nbt);
    $entity->setNameTag($nametag);
    $entity->spawnToAll();
  }

  public function getZip(){
    return new RefreshArena($this);
  }

  public function refreshArenas(){
    $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    $config->set("arenas", $this->arenas);
    foreach($this->arenas as $arena){
      $config->set($arena . "PlayTime", 780);
      $config->set($arena . "StartTime", 90);
    }
    $config->save();
  }

  public function onQuit(PlayerQuitEvent $event): void{
    $player = $event->getPlayer();
    if(isset($this->reds[$player->getName()])){
      unset($this->reds[$player->getName()]);
    }
    if(isset($this->blues[$player->getName()])){
      unset($this->blues[$player->getName()]);
    }
    if(isset($this->yellows[$player->getName()])){
      unset($this->yellows[$player->getName()]);
    }
    if(isset($this->greens[$player->getName()])){
      unset($this->greens[$player->getName()]);
    }
  }

  public function onDeath(PlayerDeathEvent $event): void{
    $player = $event->getEntity();
    $map = $player->getLevel()->getFolderName();
    if(in_array($map, $this->arenas)){
      if($player->getLastDamageCause() instanceof EntityDamageByEntityEvent){
        $murder = $player->getLastDamageCause()->getDamager();
        if($murder instanceof Player){
          $event->setDeathMessage("");
          foreach ($player->getLevel()->getPlayers() as $pl){
            $pl->sendMessage($player->getNameTag() . " §cisimli oyuncu " . $murder->getNameTag() . " §ctarafından öldürüldü.");
          }
        }
      }
      $player->setNameTag($player->getName());
      if(isset($this->reds[$player->getName()])){
        unset($this->reds[$player->getName()]);
      }
      if(isset($this->blues[$player->getName()])){
        unset($this->blues[$player->getName()]);
      }
      if(isset($this->yellows[$player->getName()])){
        unset($this->yellows[$player->getName()]);
      }
      if(isset($this->greens[$player->getName()])){
        unset($this->greens[$player->getName()]);
      }
    }
  }

  public function onLogin(PlayerJoinEvent $event): void{

    $player = $event->getPlayer();
    $w = $this->getConfig()->get("world");
    $world = $player->getLevel()->getName() === "$w";
    $top = $this->getConfig()->get("enable");
    $name = $player->getName();
    if(!$this->win->exists($name)){
      $this->win->set($name, 0);
      $this->win->save();
    }
    if($world){
      if($top == "true"){
        $this->topWin($player);
      }
    }


    $player = $event->getPlayer();
    $player->getInventory()->clearAll();
    $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
    $this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
    $player->teleport($spawn, 0, 0);
  }

  public function onBreak(BlockBreakEvent $event): void{
    $player = $event->getPlayer();
    $level = $player->getLevel()->getFolderName();
    $block = $event->getBlock();
    $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
    $x = $block->getX();
    $y = $block->getY();
    $z = $block->getZ();
        $eggred = $config->get($level . "EggRed");
        $eggblue = $config->get($level . "EggBlue");
        $eggyellow = $config->get($level . "EggYellow");
        $egggreen = $config->get($level . "EggBlue");
      if($x == $eggred[0] && $y == $eggred[1] && $z == $eggred[2]){
        $this->reds = [];
        var_dump($this->reds);
        $config->set($level . "RedKirildimi", "evet");
        $config->save();
      }
      if($x == $eggblue[0] && $y == $eggblue[1] && $z == $eggblue[2]){
        $this->blues = [];
        var_dump($this->blues);
        $config->set($level . "BlueKirildimi", "evet");
        $config->save();
      }
      if($x == $eggyellow[0] && $y == $eggyellow[1] && $z == $eggyellow[2]){
        $this->yellows = [];
        var_dump($this->yellows);
        $config->set($level . "YellowKirildimi", "evet");
        $config->save();
      }
      if($x == $egggreen[0] && $y == $egggreen[1] && $z == $egggreen[2]){
        $this->greens = [];
        var_dump($this->greens);
        $config->set($level . "GreenKirildimi", "evet");
        $config->save();
      }
    if(in_array($level, $this->arenas)){
      $starttime = $config->get($level . "StartTime");
      if($starttime > 0){
        $event->setCancelled(true);
      }
    }
  }

  public function onPlace(BlockPlaceEvent $event): void{
    $player = $event->getPlayer();
    $level = $player->getLevel()->getFolderName();
    $block = $event->getBlock();
    if(in_array($level, $this->arenas)){
      $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
      $starttime = $config->get($level . "StartTime");
      if($starttime > 0){
        $event->setCancelled(true);
      }
    }
  }

  public function onChat(PlayerChatEvent $event): void{
    $player = $event->getPlayer();
    $message = $event->getMessage();
    $level = $player->getLevel()->getFolderName();
    if(in_array($level,$this->arenas)){
      if(isset($this->reds[$player->getName()])){
        $event->setFormat("§4[KIRMIZI] §8" . $player->getName() . " §7" . $message);
      }
      if(isset($this->blues[$player->getName()])){
        $event->setFormat("§b[MAVİ] §8" . $player->getName() . " §7" . $message);
      }
      if(isset($this->yellows[$player->getName()])){
        $event->setFormat("§e[SARI] §8" . $player->getName() . " §7" . $message);
      }
      if(isset($this->greens[$player->getName()])){
        $event->setFormat("§a[YEŞİL] §8" . $player->getName() . " §7" . $message);
      }
    }
  }

  public function marketForm($player){
    $form = new SimpleForm(function (Player $event, $data){
      $player = $event->getPlayer();
      $oyuncu = $player->getName();

      if($data===null){
        return;
      }

      switch($data){
        case 0:
        $this->blokForm($player);
        break;
        case 1:
        $this->kilicForm($player);
        break;
        case 2:
        $this->zirhForm($player);
        break;
        case 3:
        $this->yemekForm($player);
        break;
      }
    });
    $form->setTitle("§aMarket");
    $form->addButton("Bloklar");
    $form->addButton("Kılıçlar");
    $form->addButton("Zırhlar");
    $form->addButton("Yemekler");
    $form->sendToPlayer($player);
  }

  public function yemekForm($player){
    $form = new SimpleForm(function (Player $event, $data){
      $player = $event->getPlayer();
      $oyuncu = $player->getName();

      if($data===null){
        return;
      }

      switch($data){
        case 0:
        if($player->getInventory()->contains(Item::get(265,0,5))){
          $player->getInventory()->addItem(Item::get(364,0,2));
          $player->getInventory()->removeItem(Item::get(265,0,5));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
        case 1:
        if($player->getInventory()->contains(Item::get(265,0,5))){
          $player->getInventory()->addItem(Item::get(366,0,2));
          $player->getInventory()->removeItem(Item::get(265,0,5));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
        case 2:
        if($player->getInventory()->contains(Item::get(265,0,3))){
          $player->getInventory()->addItem(Item::get(322,0,10));
          $player->getInventory()->removeItem(Item::get(265,0,3));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
      }
    });
    $form->setTitle("§aBlok Marketi");
    $form->addButton("Biftek - 5 Demir");
    $form->addButton("Tavuk Eti - 5 Demir");
    $form->addButton("Havuç - 3 Demir");
    $form->sendToPlayer($player);
  }

  public function blokForm($player){
    $form = new SimpleForm(function (Player $event, $data){
      $player = $event->getPlayer();
      $oyuncu = $player->getName();

      if($data===null){
        return;
      }

      switch($data){
        case 0:
        if($player->getInventory()->contains(Item::get(265,0,1))){
          $player->getInventory()->addItem(Item::get(35,0,5));
          $player->getInventory()->removeItem(Item::get(265,0,1));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
        case 1:
        if($player->getInventory()->contains(Item::get(265,0,3))){
          $player->getInventory()->addItem(Item::get(20,0,5));
          $player->getInventory()->removeItem(Item::get(265,0,3));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
        case 2:
        if($player->getInventory()->contains(Item::get(265,0,10))){
          $player->getInventory()->addItem(Item::get(5,0,5));
          $player->getInventory()->removeItem(Item::get(265,0,10));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
        case 3:
        if($player->getInventory()->contains(Item::get(266,0,5))){
          $player->getInventory()->addItem(Item::get(49,0,2));
          $player->getInventory()->removeItem(Item::get(266,0,5));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
      }
    });
    $form->setTitle("§aBlok Marketi");
    $form->addButton("Yün - 1 Demir");
    $form->addButton("Cam - 3 Demir");
    $form->addButton("Tahta - 10 Demir");
    $form->addButton("Obsidyen - 5 Altın");
    $form->sendToPlayer($player);
  }

  public function kilicForm($player){
    $form = new SimpleForm(function (Player $event, $data){
      $player = $event->getPlayer();
      $oyuncu = $player->getName();

      if($data===null){
        return;
      }

      switch($data){
        case 0:
        if($player->getInventory()->contains(Item::get(265,0,5))){
          $item = Item::get(268,0,1);
          $enchantment = Enchantment::getEnchantment(9);
          $encInstance = new EnchantmentInstance($enchantment);
          $item->addEnchantment($encInstance);
          $player->getInventory()->addItem($item);
          $player->getInventory()->removeItem(Item::get(265,0,5));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
        case 1:
        if($player->getInventory()->contains(Item::get(265,0,15))){
          $item = Item::get(272,0,1);
          $enchantment = Enchantment::getEnchantment(9);
          $encInstance = new EnchantmentInstance($enchantment);
          $item->addEnchantment($encInstance);
          $player->getInventory()->addItem($item);
          $player->getInventory()->removeItem(Item::get(265,0,15));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
        case 2:
        if($player->getInventory()->contains(Item::get(266,0,10))){
          $item = Item::get(267,0,1);
          $enchantment = Enchantment::getEnchantment(9);
          $encInstance = new EnchantmentInstance($enchantment);
          $item->addEnchantment($encInstance);
          $player->getInventory()->addItem($item);
          $player->getInventory()->removeItem(Item::get(266,0,10));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
      }
    });
    $form->setTitle("§aKılıç Marketi");
    $form->addButton("Tahta Kılıç - 5 Demir");
    $form->addButton("Taş Kılıç - 15 Demir");
    $form->addButton("Demir Kılıç - 10 Altın");
    $form->sendToPlayer($player);
  }

  public function zirhForm($player){
    $form = new SimpleForm(function (Player $event, $data){
      $player = $event->getPlayer();
      $oyuncu = $player->getName();

      if($data===null){
        return;
      }

      switch($data){
        case 0:
        if($player->getInventory()->contains(Item::get(265,0,5))){
          $player->getArmorInventory()->setHelmet(Item::get(298));
          $player->getArmorInventory()->setChestplate(Item::get(299));
          $player->getArmorInventory()->setLeggings(Item::get(300));
          $player->getArmorInventory()->setBoots(Item::get(301));
          $player->getInventory()->removeItem(Item::get(265,0,5));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
        case 1:
        if($player->getInventory()->contains(Item::get(266,0,10))){
          $player->getArmorInventory()->setHelmet(Item::get(302));
          $player->getArmorInventory()->setChestplate(Item::get(303));
          $player->getArmorInventory()->setLeggings(Item::get(304));
          $player->getArmorInventory()->setBoots(Item::get(305));
          $player->getInventory()->removeItem(Item::get(266,0,10));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
        case 2:
        if($player->getInventory()->contains(Item::get(264,0,20))){
          $player->getArmorInventory()->setHelmet(Item::get(306));
          $player->getArmorInventory()->setChestplate(Item::get(307));
          $player->getArmorInventory()->setLeggings(Item::get(308));
          $player->getArmorInventory()->setBoots(Item::get(309));
          $player->getInventory()->removeItem(Item::get(264,0,20));
        }else{
          $player->sendMessage("§cYeterli materyal yok!");
        }
        break;
      }
    });
    $form->setTitle("§aBlok Marketi");
    $form->addButton("Deri Set - 5 Demir");
    $form->addButton("Zincir Set - 10 Sltın");
    $form->addButton("Demir Set - 20 Elmas");
    $form->sendToPlayer($player);
  }

  public function onDamage(EntityDamageEvent $event): void{

    if($event->getEntity() instanceof EWEntity){
      $event->setCancelled(true);
      $this->marketForm($event->getDamager());
    }

    $levelm = $event->getEntity()->getLevel()->getFolderName();
    $level = $this->getServer()->getLevelByName($levelm);
    if($event instanceof EntityDamageByEntityEvent){
      if($event->getEntity() instanceof Player && $event->getDamager() instanceof Player){
        if($event->getBaseDamage() >= $event->getEntity()->getHealth()){
          $event->setCancelled(true);
          if(isset($this->reds[$event->getEntity()->getName()])){
            $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
            $thespawn = $config->get($levelm . "Spawn1");
            $spawn = new Position($thespawn[0],$thespawn[1]+1,$thespawn[2], $level);
            $level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
            $event->getEntity()->teleport($spawn, 0, 0);
            $event->getEntity()->getInventory()->clearAll();
            $event->getEntity()->removeAllEffects();
          }elseif(isset($this->blues[$event->getEntity()->getName()])){
            $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
            $thespawn = $config->get($levelm . "Spawn3");
            $spawn = new Position($thespawn[0],$thespawn[1]+1,$thespawn[2], $level);
            $level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
            $event->getEntity()->teleport($spawn, 0, 0);
            $event->getEntity()->getInventory()->clearAll();
            $event->getEntity()->removeAllEffects();
          }elseif(isset($this->yellows[$event->getEntity()->getName()])){
            $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
            $thespawn = $config->get($levelm . "Spawn5");
            $spawn = new Position($thespawn[0],$thespawn[1]+1,$thespawn[2], $level);
            $level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
            $event->getEntity()->teleport($spawn, 0, 0);
            $event->getEntity()->getInventory()->clearAll();
            $event->getEntity()->removeAllEffects();
          }elseif(isset($this->greens[$event->getEntity()->getName()])){
            $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
            $thespawn = $config->get($levelm . "Spawn7");
            $spawn = new Position($thespawn[0],$thespawn[1]+1,$thespawn[2], $level);
            $level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
            $event->getEntity()->teleport($spawn, 0, 0);
            $event->getEntity()->getInventory()->clearAll();
            $event->getEntity()->removeAllEffects();
          }else{
            $event->getEntity()->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
            $event->getEntity()->getInventory()->clearAll();
            $event->getEntity()->removeAllEffects();
          }
        }


        $level = $event->getEntity()->getLevel()->getFolderName();
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if(in_array($level,$this->arenas)){
          if(isset($this->reds[$event->getEntity()->getName()]) && isset($this->reds[$event->getDamager()->getName()])){
            $event->setCancelled(true);
          }elseif(isset($this->blues[$event->getEntity()->getName()]) && isset($this->blues[$event->getDamager()->getName()])){
            $event->setCancelled(true);
          }elseif(isset($this->yellows[$event->getEntity()->getName()]) && isset($this->yellows[$event->getDamager()->getName()])){
            $event->setCancelled(true);
          }elseif(isset($this->greens[$event->getEntity()->getName()]) && isset($this->greens[$event->getDamager()->getName()])){
            $event->setCancelled(true);
          }
        }
      }
    }
  }

  public function onInteract(PlayerInteractEvent $event): void{
    $player = $event->getPlayer();
    $block = $event->getBlock();
    $tile = $player->getLevel()->getTile($block);
    $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    $egg = 1;

    $config = new Config($this->getDataFolder()."config.yml", Config::YAML);
    $arenas = $config->get("arenas");
    if(!empty($arenas)){
      foreach($arenas as $arena){
        $dgen1 = $config->get($arena . "Demir1");
        $dgen1l = $config->get($arena . "Demir1level");
        if($block->getX() == $dgen1[0] && $block->getY() == $dgen1[1] && $block->getZ() == $dgen1[2]){
          if($dgen1l == 1.5){
            if($player->getInventory()->contains(Item::get(265, 0, 30))){
              $this->getScheduler()->cancelTask(3);
              $config->set($arena . "Demir1level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(265, 0, 30));
            }else{
              $player->sendMessage("§c30 Demir Gerekli!");
            }
          }elseif($dgen1l == 2.5){
            if($player->getInventory()->contains(Item::get(266, 0, 15))){
              $this->getScheduler()->cancelTask(4);
              $config->set($arena . "Demir1level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(266, 0, 15));
            }else{
              $player->sendMessage("§c15 Altın Gerekli!");
            }
          }
        }
        $dgen2 = $config->get($arena . "Demir2");
        $dgen2l = $config->get($arena . "Demir2level");
        if($block->getX() == $dgen2[0] && $block->getY() == $dgen2[1] && $block->getZ() == $dgen2[2]){
          if($dgen2l == 1.5){
            if($player->getInventory()->contains(Item::get(265, 0, 30))){
              $this->getScheduler()->cancelTask(6);
              $config->set($arena . "Demir2level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(265, 0, 30));
            }else{
              $player->sendMessage("§c30 Demir Gerekli!");
            }
          }elseif($dgen2l == 2.5){
            if($player->getInventory()->contains(Item::get(266, 0, 15))){
              $this->getScheduler()->cancelTask(7);
              $config->set($arena . "Demir2level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(266, 0, 15));
            }else{
              $player->sendMessage("§c15 Altın Gerekli!");
            }
          }
        } //
        $dgen3 = $config->get($arena . "Demir3");
        $dgen3l = $config->get($arena . "Demir3level");
        if($block->getX() == $dgen3[0] && $block->getY() == $dgen3[1] && $block->getZ() == $dgen3[2]){
          if($dgen3l == 1.5){
            if($player->getInventory()->contains(Item::get(265, 0, 30))){
              $this->getScheduler()->cancelTask(9);
              $config->set($arena . "Demir3level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(265, 0, 30));
            }else{
              $player->sendMessage("§c30 Demir Gerekli!");
            }
          }elseif($dgen3l == 2.5){
            if($player->getInventory()->contains(Item::get(266, 0, 15))){
              $this->getScheduler()->cancelTask(10);
              $config->set($arena . "Demir3level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(266, 0, 15));
            }else{
              $player->sendMessage("§c15 Altın Gerekli!");
            }
          }
        }
        $dgen4 = $config->get($arena . "Demir4");
        $dgen4l = $config->get($arena . "Demir4level");
        if($block->getX() == $dgen4[0] && $block->getY() == $dgen4[1] && $block->getZ() == $dgen4[2]){
          if($dgen4l == 1.5){
            if($player->getInventory()->contains(Item::get(265, 0, 30))){
              $this->getScheduler()->cancelTask(12);
              $config->set($arena . "Demir4level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(265, 0, 30));
            }else{
              $player->sendMessage("§c30 Demir Gerekli!");
            }
          }elseif($dgen4l == 2.5){
            if($player->getInventory()->contains(Item::get(266, 0, 15))){
              $this->getScheduler()->cancelTask(13);
              $config->set($arena . "Demir4level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(266, 0, 15));
            }else{
              $player->sendMessage("§c15 Altın Gerekli!");
            }
          }
        }




        $dgen4 = $config->get($arena . "Altin1");
        $dgen4l = $config->get($arena . "Altin1level");
        if($block->getX() == $dgen4[0] && $block->getY() == $dgen4[1] && $block->getZ() == $dgen4[2]){
          if($dgen4l == 1.5){
            if($player->getInventory()->contains(Item::get(266, 0, 30))){
              $this->getScheduler()->cancelTask(15);
              $config->set($arena . "Altin1level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(266, 0, 30));
            }else{
              $player->sendMessage("§c30 Altın Gerekli!");
            }
          }elseif($dgen4l == 2.5){
            if($player->getInventory()->contains(Item::get(264, 0, 15))){
              $this->getScheduler()->cancelTask(16);
              $config->set($arena . "Altin1level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 15));
            }else{
              $player->sendMessage("§c15 Elmas Gerekli!");
            }
          }
        } //
        $dgen4 = $config->get($arena . "Altin2");
        $dgen4l = $config->get($arena . "Altin2level");
        if($block->getX() == $dgen4[0] && $block->getY() == $dgen4[1] && $block->getZ() == $dgen4[2]){
          if($dgen4l == 1.5){
            if($player->getInventory()->contains(Item::get(266, 0, 30))){
              $this->getScheduler()->cancelTask(18);
              $config->set($arena . "Altin2level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(266, 0, 30));
            }else{
              $player->sendMessage("§c30 Altın Gerekli!");
            }
          }elseif($dgen4l == 2.5){
            if($player->getInventory()->contains(Item::get(264, 0, 15))){
              $this->getScheduler()->cancelTask(19);
              $config->set($arena . "Altin2level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 15));
            }else{
              $player->sendMessage("§c15 Elmas Gerekli!");
            }
          }
        }
        $dgen4 = $config->get($arena . "Altin3");
        $dgen4l = $config->get($arena . "Altin3level");
        if($block->getX() == $dgen4[0] && $block->getY() == $dgen4[1] && $block->getZ() == $dgen4[2]){
          if($dgen4l == 1.5){
            if($player->getInventory()->contains(Item::get(266, 0, 30))){
              $this->getScheduler()->cancelTask(21);
              $config->set($arena . "Altin3level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(266, 0, 30));
            }else{
              $player->sendMessage("§c30 Altın Gerekli!");
            }
          }elseif($dgen4l == 2.5){
            if($player->getInventory()->contains(Item::get(264, 0, 15))){
              $this->getScheduler()->cancelTask(22);
              $config->set($arena . "Altin3level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 15));
            }else{
              $player->sendMessage("§c15 Elmas Gerekli!");
            }
          }
        }
        $dgen4 = $config->get($arena . "Altin4");
        $dgen4l = $config->get($arena . "Altin4level");
        if($block->getX() == $dgen4[0] && $block->getY() == $dgen4[1] && $block->getZ() == $dgen4[2]){
          if($dgen4l == 1.5){
            if($player->getInventory()->contains(Item::get(266, 0, 30))){
              $this->getScheduler()->cancelTask(24);
              $config->set($arena . "Altin4level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(266, 0, 30));
            }else{
              $player->sendMessage("§c30 Altın Gerekli!");
            }
          }elseif($dgen4l == 2.5){
            if($player->getInventory()->contains(Item::get(264, 0, 15))){
              $this->getScheduler()->cancelTask(25);
              $config->set($arena . "Altin4level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 15));
            }else{
              $player->sendMessage("§c15 Elmas Gerekli!");
            }
          }
        }



        $dgen4 = $config->get($arena . "Elmas1");
        $dgen4l = $config->get($arena . "Elmas1level");
        if($block->getX() == $dgen4[0] && $block->getY() == $dgen4[1] && $block->getZ() == $dgen4[2]){
          if($dgen4l == 1.5){
            if($player->getInventory()->contains(Item::get(264, 0, 15))){
              $this->getScheduler()->cancelTask(27);
              $config->set($arena . "Elmas1level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 15));
            }else{
              $player->sendMessage("§c15 Elmas Gerekli!");
            }
          }elseif($dgen4l == 2.5){
            if($player->getInventory()->contains(Item::get(264, 0, 30))){
              $this->getScheduler()->cancelTask(28);
              $config->set($arena . "Elmas1level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 30));
            }else{
              $player->sendMessage("§c30 Elmas Gerekli!");
            }
          }
        }
        $dgen4 = $config->get($arena . "Elmas2");
        $dgen4l = $config->get($arena . "Elmas2level");
        if($block->getX() == $dgen4[0] && $block->getY() == $dgen4[1] && $block->getZ() == $dgen4[2]){
          if($dgen4l == 1.5){
            if($player->getInventory()->contains(Item::get(264, 0, 15))){
              $this->getScheduler()->cancelTask(30);
              $config->set($arena . "Elmas2level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 15));
            }else{
              $player->sendMessage("§c15 Elmas Gerekli!");
            }
          }elseif($dgen4l == 2.5){
            if($player->getInventory()->contains(Item::get(264, 0, 30))){
              $this->getScheduler()->cancelTask(31);
              $config->set($arena . "Elmas2level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 30));
            }else{
              $player->sendMessage("§c30 Elmas Gerekli!");
            }
          }
        }
        $dgen4 = $config->get($arena . "Elmas3");
        $dgen4l = $config->get($arena . "Elmas3level");
        if($block->getX() == $dgen4[0] && $block->getY() == $dgen4[1] && $block->getZ() == $dgen4[2]){
          if($dgen4l == 1.5){
            if($player->getInventory()->contains(Item::get(264, 0, 15))){
              $this->getScheduler()->cancelTask(33);
              $config->set($arena . "Elmas3level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 15));
            }else{
              $player->sendMessage("§c15 Elmas Gerekli!");
            }
          }elseif($dgen4l == 2.5){
            if($player->getInventory()->contains(Item::get(264, 0, 30))){
              $this->getScheduler()->cancelTask(34);
              $config->set($arena . "Elmas3level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 30));
            }else{
              $player->sendMessage("§c30 Elmas Gerekli!");
            }
          }
        }
        $dgen4 = $config->get($arena . "Elmas4");
        $dgen4l = $config->get($arena . "Elmas4level");
        if($block->getX() == $dgen4[0] && $block->getY() == $dgen4[1] && $block->getZ() == $dgen4[2]){
          if($dgen4l == 1.5){
            if($player->getInventory()->contains(Item::get(264, 0, 15))){
              $this->getScheduler()->cancelTask(36);
              $config->set($arena . "Elmas4level", 2);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 15));
            }else{
              $player->sendMessage("§c15 Elmas Gerekli!");
            }
          }elseif($dgen4l == 2.5){
            if($player->getInventory()->contains(Item::get(264, 0, 30))){
              $this->getScheduler()->cancelTask(37);
              $config->set($arena . "Elmas4level", 3);
              $config->save();
              $player->getInventory()->removeItem(Item::get(264, 0, 30));
            }else{
              $player->sendMessage("§c30 Elmas Gerekli!");
            }
          }
        }
      }
    }

    if($tile instanceof Sign){
      if($this->mode == 26){
        $tile->setText("§7*§cAug§fMCPE§7*", "§e0/8", $this->currentLevel, "§aOyna");
        $this->refreshArenas();
        $this->currentLevel = "";
        $this->mode = 0;
        $player->sendMessage("§aArena başarı ile kuruldu.");
      }else{
        $text = $tile->getText();
        if($text[3] == "§aOyna"){
          if($text[0] == "§7*§cAug§fMCPE§7*"){
            $namemap = str_replace("§f", "", $text[2]);
            $level = $this->getServer()->getLevelByName($namemap);
            if($text[1] == "§e0/8"){
              $thespawn = $config->get($namemap . "Spawn1");
              $player->sendMessage("§9> §eOyuna katıldın.");
              $this->reds[$player->getName()] = $player->getName();
              $player->setNameTag("§4" . $player->getName());
              foreach ($level->getPlayers() as $pl){
                $pl->sendMessage("§9> §f" . $player->getName() . " §eisimli oyuncu oyuna katıldı.");
              }
            }elseif($text[1] == "§e1/8"){
              $thespawn = $config->get($namemap . "Spawn2");
              $player->sendMessage("§9> §eOyuna katıldın.");
              $this->reds[$player->getName()] = $player->getName();
              $player->setNameTag("§4" . $player->getName());
              foreach ($level->getPlayers() as $pl){
                $pl->sendMessage("§9> §f" . $player->getName() . " §eisimli oyuncu oyuna katıldı.");
              }
            }elseif($text[1] == "§e2/8"){
              $thespawn = $config->get($namemap . "Spawn3");
              $player->sendMessage("§9> §eOyuna katıldın.");
              $this->blues[$player->getName()] = $player->getName();
              $player->setNameTag("§b" . $player->getName());
              foreach ($level->getPlayers() as $pl){
                $pl->sendMessage("§9> §f" . $player->getName() . " §eisimli oyuncu oyuna katıldı.");
              }
            }elseif($text[1] == "§e3/8"){
              $thespawn = $config->get($namemap . "Spawn4");
              $player->sendMessage("§9> §eOyuna katıldın.");
              $this->blues[$player->getName()] = $player->getName();
              $player->setNameTag("§b" . $player->getName());
              foreach ($level->getPlayers() as $pl){
                $pl->sendMessage("§9> §f" . $player->getName() . " §eisimli oyuncu oyuna katıldı.");
              }
            }elseif($text[1] == "§e4/8"){
              $thespawn = $config->get($namemap . "Spawn5");
              $player->sendMessage("§9> §eOyuna katıldın.");
              $this->yellows[$player->getName()] = $player->getName();
              $player->setNameTag("§e" . $player->getName());
              foreach ($level->getPlayers() as $pl){
                $pl->sendMessage("§9> §f" . $player->getName() . " §eisimli oyuncu oyuna katıldı.");
              }
            }elseif($text[1] == "§e5/8"){
              $thespawn = $config->get($namemap . "Spawn6");
              $player->sendMessage("§9> §eOyuna katıldın.");
              $this->yellows[$player->getName()] = $player->getName();
              $player->setNameTag("§e" . $player->getName());
              foreach ($level->getPlayers() as $pl){
                $pl->sendMessage("§9> §f" . $player->getName() . " §eisimli oyuncu oyuna katıldı.");
              }
            }elseif($text[1] == "§e6/8"){
              $thespawn = $config->get($namemap . "Spawn7");
              $player->sendMessage("§9> §eOyuna katıldın.");
              $this->greens[$player->getName()] = $player->getName();
              $player->setNameTag("§a" . $player->getName());
              foreach ($level->getPlayers() as $pl){
                $pl->sendMessage("§9> §f" . $player->getName() . " §eisimli oyuncu oyuna katıldı.");
              }
            }elseif($text[1] == "§e7/8"){
              $thespawn = $config->get($namemap . "Spawn8");
              $player->sendMessage("§9> §eOyuna katıldın.");
              $this->greens[$player->getName()] = $player->getName();
              $player->setNameTag("§a" . $player->getName());
              foreach ($level->getPlayers() as $pl){
                $pl->sendMessage("§9> §f" . $player->getName() . " §eisimli oyuncu oyuna katıldı.");
              }
            }
            $spawn = new Position($thespawn[0],$thespawn[1]+1,$thespawn[2], $level);
            $level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
            $player->teleport($spawn, 0, 0);
            $player->getInventory()->clearAll();
            $player->removeAllEffects();
            $player->getInventory()->addItem(Item::get(340,0,1)->setCustomName("§aKit Seç"));
            $player->getInventory()->addItem(Item::get(339,0,1)->setCustomName("§cTakım Seç"));
            $player->setHealth(20);
          }
        }
      }
    }elseif($this->mode >= 1 && $this->mode <=8){
      $config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(), $block->getY(), $block->getZ()));
      $config->save();
      $player->sendMessage("§aSpawn ayarlandı.");
      $this->mode++;
    }elseif($this->mode >= 9 && $this->mode <= 10){
      $player->sendMessage("§aLobi belirlemek için tıkla.");
      $config->set($this->currentLevel . "Lobi", array($block->getX(), $block->getY() + 1, $block->getZ()));
      $config->save();
      $this->mode++;
    }elseif($this->mode >= 11 && $this->mode <= 12){
      $player->sendMessage("§aYumurtaları ayarla.");
      $config->set($this->currentLevel . "EggRed", array($block->getX(), $block->getY(), $block->getZ()));
      $config->set($this->currentLevel . "RedKirildimi", "hayir");
      $config->save();
      $this->mode++;
    }elseif($this->mode >= 13 && $this->mode <= 14){
      $player->sendMessage("§aYumurtaları ayarla.");
      $config->set($this->currentLevel . "EggBlue", array($block->getX(), $block->getY(), $block->getZ()));
      $config->set($this->currentLevel . "BlueKirildimi", "hayir");
      $config->save();
      $this->mode++;
    }elseif($this->mode >= 13 && $this->mode <= 14){
      $player->sendMessage("§aYumurtaları ayarla.");
      $config->set($this->currentLevel . "EggYellow", array($block->getX(), $block->getY(), $block->getZ()));
      $config->set($this->currentLevel . "YellowKirildimi", "hayir");
      $config->save();
      $this->mode++;
    }elseif($this->mode >= 13 && $this->mode <= 14){
      $player->sendMessage("§aYumurtaları ayarla.");
      $config->set($this->currentLevel . "EggGreen", array($block->getX(), $block->getY(), $block->getZ()));
      $config->set($this->currentLevel . "GreenKirildimi", "hayir");
      $config->save();
      $this->mode++;
    }elseif($this->mode == 15){
      $player->sendMessage("§aTıkla ve geri dön.");
      $level = $this->getServer()->getLevelByName($this->currentLevel);
      $level->setSpawn = (new Vector3($block->getX(),$block->getY()+2,$block->getZ()));
      $config->save("arenas", $this->arenas);
      $player->sendMessage("§aBir tabelaya tıkla ve kurulumu tamamla.");
      $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
      $this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
      $player->teleport($spawn, 0, 0);
      $config->save();
      $this->mode = 26;
    }
  }

  public function onCommand(CommandSender $cs, Command $cmd, string $label, array $args): bool{
    switch($cmd->getName()){
      case "ewt":
      if($cs->isOp()){
        if(!empty($args[0])){
          if($args[0] == "olustur"){
            if(!empty($args[1])){
              if(file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1])){
                $this->getServer()->loadLevel($args[1]);
								$this->getServer()->getLevelByName($args[1])->loadChunk($this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(), $this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ());
								array_push($this->arenas,$args[1]);
								$this->currentLevel = $args[1];
								$this->mode = 1;
								$cs->sendMessage("Spawn noktalarını belirlemek için tıkla!");
								$cs->setGamemode(1);
								$cs->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn(),0,0);
                $name = $args[1];
                $this->getZip()->zip($cs, $name);
              }else{
                $cs->sendMessage("Harita bulunamadı");
              }
            }else{
              $cs->sendMessage("Eksik değer");
            }
          }elseif($args[0] == "market"){
            $this->spawnNPC($cs);
          }
        }else{
          $cs->sendMessage("Eksik değer");
        }
      }else{
        $cs->sendMessage("Yetkin yok");
      }
      break;
    }
    return true;
  }
}

class RefreshSigns extends Task{

  public function __construct($plugin){
    $this->p = $plugin;
  }

  public function onRun($tick){
    $allplayers = $this->p->getServer()->getOnlinePlayers();
    $level = $this->p->getServer()->getDefaultLevel();
    $tiles = $level->getTiles();
    foreach($tiles as $t){
      if($t instanceof Sign){
        $text = $t->getText();
        if($text[0] == "§7*§cAug§fMCPE§7*"){
          $aop = 0;
          $namemap = str_replace("§f", "", $text[2]);
          foreach($allplayers as $player){
            if($player->getLevel()->getFolderName() == $namemap){
              $aop = $aop+1;
            }
          }
          $ingame = "§aOyna";
          $config = new Config($this->p->getDataFolder() . "config.yml", Config::YAML);

          if($config->get($namemap . "PlayTime") != 780){
            $ingame = "§cOynanıyor";
          }elseif($aop>=12){
            $ingame = "§6Dolu";
          }
          $t->setText("§7*§cAug§fMCPE§7*", "§e$aop/8", $text[2], $ingame);
        }
      }
    }
  }
}
class GameSender extends Task{
  public function __construct($plugin){
    $this->p = $plugin;
  }
  public function onRun($tick){
    $config = new Config($this->p->getDataFolder()."config.yml", Config::YAML);
    $arenas = $config->get("arenas");
    if(!empty($arenas)){
      foreach($arenas as $arena){
        $sure = $config->get($arena . "PlayTime");
        $bsure = $config->get($arena . "StartTime");
        $levelArena = $this->p->getServer()->getLevelByName($arena);
        if($levelArena instanceof Level){
          $oarena = $levelArena->getPlayers();
          if(count($oarena)==0){
            $config->set($arena . "PlayTime", 780);
            $config->set($arena . "StartTime", 30);
            $config->set($arena . "RedKirildimi", "hayir");
            $config->set($arena . "BlueKirildimi", "hayir");
            $config->set($arena . "YellowKirildimi", "hayir");
            $config->set($arena . "GreenKirildimi", "hayir");
            $config->set($arena . "Basladimi", "hayir");
            $config->set($arena . "Demir1level", 1);
            $config->set($arena . "Demir2level", 1);
            $config->set($arena . "Demir3level", 1);
            $config->set($arena . "Demir4level", 1);
            $config->set($arena . "Altin1level", 1);
            $config->set($arena . "Altin2level", 1);
            $config->set($arena . "Altin3level", 1);
            $config->set($arena . "Altin4level", 1);
          }else{
            if(count($oarena)>2){
              if($bsure > 0){
                $bsure--;
                $config->save();
                foreach ($oarena as $oy) {
                  $oy->sendTip("§aOyunun başlamasına §f$bsure §akaldı.");
                }
                $config->set($arena . "StartTime", $bsure);
              }else{
                $aop = count($levelArena->getPlayers());
                if($aop==2){
                  foreach($oarena as $oy){
                    if(isset($this->p->reds[$oy->getName()])){
                      $takim = "§cKırmızı";
                    }elseif(isset($this->p->blues[$oy->getName()])){
                      $takim = "§bMavi";
                    }elseif(isset($this->p->yellows[$oy->getName()])){
                      $takim = "§eSarı";
                    }elseif(isset($this->p->greens[$oy->getName()])){
                      $takim = "§aYeşil";
                    }
                    foreach($this->p->getServer()->getOnlinePlayers() as $oyoy){
                      $oyoy->sendMessage("§1> $takim §atakım §f$arena §aharitasını kazandı.");
                    }
                    $oy->getInventory()->clearAll();
                    $oy->setNameTag($oy->getName());
                    $spawn = $this->p->getServer()->getDefaultLevel()->getSafeSpawn();
                    $this->p->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                    $oy->teleport($spawn, 0, 0);
                    $oy->setHealth(20);
                    $wini = $this->p->win->get($oy->getName());
                    $this->p->win->set($oy->getName(), $wini+1);
                    $this->p->win->save();
                  }
                }
                if(($aop>2)){
                  //var_dump($this->p->reds);
                  //var_dump($this->p->blues);
                  $red = $config->get($arena . "RedKirildimi");
                  $blue = $config->get($arena . "BlueKirildimi");
                  $yellow = $config->get($arena . "YellowKirildimi");
                  $green = $config->get($arena . "GreenKirildimi");

                  $redm = "§aHayatta";
                  if($red == "evet"){
                    $redm = "§cKırıldı";
                  }
                  $bluem = "§aHayatta";
                  if($blue == "evet"){
                    $bluem = "§cKırıldı";
                  }
                  $yellowm = "§aHayatta";
                  if($yellow == "evet"){
                    $yellowm = "§cKırıldı";
                  }
                  $greenm = "§aHayatta";
                  if($green == "evet"){
                    $greenm = "§cKırıldı";
                  }
                  foreach($oarena as $oy){
                    $oy->sendTip("§4Kırmızı: $redm §7- §bMavi: $bluem §7- §eSarı: $yellowm §7- §aYeşil: $greenm");
                  }
                }
                $sure--;
                $config->save();

                //DEMİR
                $dgen1 = $config->get($arena . "Demir1");
                $dgen1l = $config->get($arena . "Demir1level");
                if($dgen1l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir($this), 100); //id 3
                  $config->set($arena . "Demir1level", 1.5);
                  $config->save();
                }elseif($dgen1l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir($this), 60); //id 4
                  $config->set($arena . "Demir1level", 2.5);
                  $config->save();
                }elseif($dgen1l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir($this), 40); //id 5
                  $config->set($arena . "Demir1level", 3.5);
                  $config->save();
                }

                $dgen2 = $config->get($arena . "Demir2");
                $dgen2l = $config->get($arena . "Demir2level");
                if($dgen2l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir2($this), 100); //id 6
                  $config->set($arena . "Demir2level", 1.5);
                  $config->save();
                }elseif($dgen2l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir2($this), 60); //id 7
                  $config->set($arena . "Demir2level", 2.5);
                  $config->save();
                }elseif($dgen2l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir2($this), 40); //id 8
                  $config->set($arena . "Demir2level", 3.5);
                  $config->save();
                }

                $dgen3 = $config->get($arena . "Demir3");
                $dgen3l = $config->get($arena . "Demir3level");
                if($dgen3l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir3($this), 100); //id 9
                  $config->set($arena . "Demir3level", 1.5);
                  $config->save();
                }elseif($dgen3l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir3($this), 60); //id 10
                  $config->set($arena . "Demir3level", 2.5);
                  $config->save();
                }elseif($dgen3l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir3($this), 40); //id 11
                  $config->set($arena . "Demir3level", 3.5);
                  $config->save();
                }

                $dgen4 = $config->get($arena . "Demir4");
                $dgen4l = $config->get($arena . "Demir4level");
                if($dgen4l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir4($this), 100); //id 12
                  $config->set($arena . "Demir4level", 1.5);
                  $config->save();
                }elseif($dgen4l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir4($this), 60); //id 13
                  $config->set($arena . "Demir4level", 2.5);
                  $config->save();
                }elseif($dgen4l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Demir4($this), 40); //id 14
                  $config->set($arena . "Demir4level", 3.5);
                  $config->save();
                }
                //DEMİR

                //ALTIN
                $agen1 = $config->get($arena . "Altin1");
                $agen1l = $config->get($arena . "Altin1level");
                if($agen1l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin($this), 100); //id 15
                  $config->set($arena . "Altin1level", 1.5);
                  $config->save();
                }elseif($agen1l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin($this), 60); //id 16
                  $config->set($arena . "Altin1level", 2.5);
                  $config->save();
                }elseif($agen1l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin($this), 40); //id 17
                  $config->set($arena . "Altin1level", 3.5);
                  $config->save();
                }

                $agen2 = $config->get($arena . "Altin2");
                $agen2l = $config->get($arena . "Altin2level");
                if($agen2l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin2($this), 100); //id 18
                  $config->set($arena . "Altin2level", 1.5);
                  $config->save();
                }elseif($agen2l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin2($this), 60); //id 19
                  $config->set($arena . "Altin2level", 2.5);
                  $config->save();
                }elseif($agen2l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin2($this), 40); //id 20
                  $config->set($arena . "Altin2level", 3.5);
                  $config->save();
                }

                $agen3 = $config->get($arena . "Altin3");
                $agen3l = $config->get($arena . "Altin3level");
                if($agen3l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin3($this), 100); //id 21
                  $config->set($arena . "Altin3level", 1.5);
                  $config->save();
                }elseif($agen3l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin3($this), 60); //id 22
                  $config->set($arena . "Altin3level", 2.5);
                  $config->save();
                }elseif($agen3l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin3($this), 40); //id 23
                  $config->set($arena . "Altin3level", 3.5);
                  $config->save();
                }

                $agen4 = $config->get($arena . "Altin4");
                $agen4l = $config->get($arena . "Altin4level");
                if($agen4l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin4($this), 100); //id 24
                  $config->set($arena . "Altin4level", 1.5);
                  $config->save();
                }elseif($agen4l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin4($this), 60); //id 25
                  $config->set($arena . "Altin4level", 2.5);
                  $config->save();
                }elseif($agen4l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Altin4($this), 40); //id 26
                  $config->set($arena . "Altin4level", 3.5);
                  $config->save();
                }
                //ALTIN

                //ELMAS
                $agen1 = $config->get($arena . "Elmas1");
                $agen1l = $config->get($arena . "Elmas1level");
                if($agen1l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas($this), 100); //id 27
                  $config->set($arena . "Elmas1level", 1.5);
                  $config->save();
                }elseif($agen1l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas($this), 60); //id 28
                  $config->set($arena . "Elmas1level", 2.5);
                  $config->save();
                }elseif($agen1l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas($this), 40); //id 29
                  $config->set($arena . "Elmas1level", 3.5);
                  $config->save();
                }
                $agen1 = $config->get($arena . "Elmas2");
                $agen1l = $config->get($arena . "Elmas2level");
                if($agen1l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas2($this), 100); //id 27
                  $config->set($arena . "Elmas2level", 1.5);
                  $config->save();
                }elseif($agen1l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas2($this), 60); //id 28
                  $config->set($arena . "Elmas2level", 2.5);
                  $config->save();
                }elseif($agen1l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas2($this), 40); //id 29
                  $config->set($arena . "Elmas2level", 3.5);
                  $config->save();
                }
                $agen1 = $config->get($arena . "Elmas3");
                $agen1l = $config->get($arena . "Elmas3level");
                if($agen1l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas3($this), 100); //id 27
                  $config->set($arena . "Elmas3level", 1.5);
                  $config->save();
                }elseif($agen1l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas3($this), 60); //id 28
                  $config->set($arena . "Elmas3level", 2.5);
                  $config->save();
                }elseif($agen1l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas3($this), 40); //id 29
                  $config->set($arena . "Elmas3level", 3.5);
                  $config->save();
                }
                $agen1 = $config->get($arena . "Elmas4");
                $agen1l = $config->get($arena . "Elmas4level");
                if($agen1l == 1){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas4($this), 100); //id 27
                  $config->set($arena . "Elmas4level", 1.5);
                  $config->save();
                }elseif($agen1l == 2){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas4($this), 60); //id 28
                  $config->set($arena . "Elmas4level", 2.5);
                  $config->save();
                }elseif($agen1l == 3){
                  $this->p->getScheduler()->scheduleRepeatingTask(new Elmas4($this), 40); //id 29
                  $config->set($arena . "Elmas4level", 3.5);
                  $config->save();
                }
                //ELMAS

                if($sure == 779){
                  $config->set($arena . "Basladimi", "evet");
                  $config->save();
                }
                if($sure == 765){
                  foreach ($oarena as $oy){
                  $oy->sendMessage("§1> §1Mücadelenin başlamasına 15 saniye var.");
                  }
                }
                if($sure == 750){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Mücadele başladı, elini çabuk tut.");
                  }
                }
                if($sure == 550){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Kalitenin adresi §fSunucu Adı.");
                  }
                }
                if($sure == 5){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f5 §1saniye.");
                  }
                }
                if($sure == 4){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f4 §1saniye.");
                  }
                }
                if($sure == 3){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f3 §1saniye.");
                  }
                }
                if($sure == 2){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f2 §1saniye.");
                  }
                }
                if($sure == 1){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f1 §1saniye.");
                  }
                }
                if($sure <= 0){
                  $spawn = $this->p->getServer()->getDefaultLevel()->getSafeSpawn();
                  $this->p->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                  foreach($oarena as $oy){
                    $oy->teleport($spawn, 0, 0);
                    $oy->sendMessage("§1> §f$arena §1mapinde kazanan yok.");
                    $oy->getInventory()->clearAll();
           	        $oy->setHealth(20);
           	        $oy->setNameTag($oy->getName());
                   	$this->p->mapYenile()->reload($levelArena);
                  }
                  $sure = 780;
                }
              }
              $config->set($arena . "PlayTime", $sure);
            }else{
              if($bsure <= 0){
                foreach($oarena as $oy){
                  foreach($this->p->getServer()->getOnlinePlayers() as $oyoy){
                    $oyoy->sendMessage("§1> §f" . $oy->getName() . " §1isimli oyuncu §f" . $arena . " §1isimli oyunu kazandı.");
                  }
                  $spawn = $this->p->getServer()->getDefaultLevel()->getSafeSpawn();
                  $this->p->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                  $oy->getInventory()->clearAll();
                  $oy->teleport($spawn,0,0);
                  $oy->setHealth(20);
                  $oy->setNameTag($oy->getName());
                  $this->mapYenile()->reload($levelArena);
                  }
                  $config->set($arena . "PlayTime", 780);
							    $config->set($arena . "StartTime", 30);
                }else{
                  foreach ($oarena as $oy) {
                    $oy->sendTip("§cOyuncular bekleniyor..");
                  }
                  $config->set($arena . "PlayTime", 780);
							    $config->set($arena . "StartTime", 30);
                }
              }
            }
          }
        }
      }
      $config->save();
    }
    public function mapYenile(){
    return new Resetmap($this);
  }
  }

  class Demir extends Task{

    public function __construct($plugin){
      $this->b = $plugin;
    }

    public function onRun($tick){
      $config = new Config($this->b->p->getDataFolder()."config.yml", Config::YAML);
      $arenas = $config->get("arenas");
      if(!empty($arenas)){
        foreach($arenas as $arena){
          $dgen = $config->get($arena . "Demir1");
          $level = $this->b->p->getServer()->getLevelByName($arena);
          $level->dropItem(new Vector3($dgen[0], $dgen[1], $dgen[2]), Item::get(265));
        }
      }
    }
  }

  class Demir2 extends Task{

    public function __construct($plugin){
      $this->b = $plugin;
    }

    public function onRun($tick){
      $config = new Config($this->b->p->getDataFolder()."config.yml", Config::YAML);
      $arenas = $config->get("arenas");
      if(!empty($arenas)){
        foreach($arenas as $arena){
          $dgen = $config->get($arena . "Demir2");
          $level = $this->b->p->getServer()->getLevelByName($arena);
          $level->dropItem(new Vector3($dgen[0], $dgen[1], $dgen[2]), Item::get(265));
        }
      }
    }
  }

  class Demir3 extends Task{

    public function __construct($plugin){
      $this->b = $plugin;
    }

    public function onRun($tick){
      $config = new Config($this->b->p->getDataFolder()."config.yml", Config::YAML);
      $arenas = $config->get("arenas");
      if(!empty($arenas)){
        foreach($arenas as $arena){
          $dgen = $config->get($arena . "Demir3");
          $level = $this->b->p->getServer()->getLevelByName($arena);
          $level->dropItem(new Vector3($dgen[0], $dgen[1], $dgen[2]), Item::get(265));
        }
      }
    }
  }

  class Demir4 extends Task{

    public function __construct($plugin){
      $this->b = $plugin;
    }

    public function onRun($tick){
      $config = new Config($this->b->p->getDataFolder()."config.yml", Config::YAML);
      $arenas = $config->get("arenas");
      if(!empty($arenas)){
        foreach($arenas as $arena){
          $dgen = $config->get($arena . "Demir4");
          $level = $this->b->p->getServer()->getLevelByName($arena);
          $level->dropItem(new Vector3($dgen[0], $dgen[1], $dgen[2]), Item::get(265));
        }
      }
    }
  }

  class Altin extends Task{

    public function __construct($plugin){
      $this->b = $plugin;
    }

    public function onRun($tick){
      $config = new Config($this->b->p->getDataFolder()."config.yml", Config::YAML);
      $arenas = $config->get("arenas");
      if(!empty($arenas)){
        foreach($arenas as $arena){
          $dgen = $config->get($arena . "Altin1");
          $level = $this->b->p->getServer()->getLevelByName($arena);
          $level->dropItem(new Vector3($dgen[0], $dgen[1], $dgen[2]), Item::get(266));
        }
      }
    }
  }

  class Altin2 extends Task{

    public function __construct($plugin){
      $this->b = $plugin;
    }

    public function onRun($tick){
      $config = new Config($this->b->p->getDataFolder()."config.yml", Config::YAML);
      $arenas = $config->get("arenas");
      if(!empty($arenas)){
        foreach($arenas as $arena){
          $dgen = $config->get($arena . "Altin2");
          $level = $this->b->p->getServer()->getLevelByName($arena);
          $level->dropItem(new Vector3($dgen[0], $dgen[1], $dgen[2]), Item::get(266));
        }
      }
    }
  }

  class Altin3 extends Task{

    public function __construct($plugin){
      $this->b = $plugin;
    }

    public function onRun($tick){
      $config = new Config($this->b->p->getDataFolder()."config.yml", Config::YAML);
      $arenas = $config->get("arenas");
      if(!empty($arenas)){
        foreach($arenas as $arena){
          $dgen = $config->get($arena . "Altin3");
          $level = $this->b->p->getServer()->getLevelByName($arena);
          $level->dropItem(new Vector3($dgen[0], $dgen[1], $dgen[2]), Item::get(266));
        }
      }
    }
  }

  class Altin4 extends Task{

    public function __construct($plugin){
      $this->b = $plugin;
    }

    public function onRun($tick){
      $config = new Config($this->b->p->getDataFolder()."config.yml", Config::YAML);
      $arenas = $config->get("arenas");
      if(!empty($arenas)){
        foreach($arenas as $arena){
          $dgen = $config->get($arena . "Altin4");
          $level = $this->b->p->getServer()->getLevelByName($arena);
          $level->dropItem(new Vector3($dgen[0], $dgen[1], $dgen[2]), Item::get(266));
        }
      }
    }
  }
