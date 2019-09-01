<?php

namespace BayAlper10\EggWarsTeam\npc;

use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\Player;

use BayAlper10\EggWarsTeam\EWMain;
use BayAlper10\EggWarsTeam\formapi\SimpleForm;

class EWEntity extends Human{

  public function touch(EntityDamageEvent $event): void{
    $event->setCancelled();
    if(!$event instanceof EntityDamageByEntityEvent)return;
    $damager = $event->getDamager();
    if(!$damager instanceof Player)return;
    $damager->sendMessage("sa");
  }
}
