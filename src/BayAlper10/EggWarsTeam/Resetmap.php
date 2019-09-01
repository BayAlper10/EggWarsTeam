<?php

namespace BayAlper10\EggWarsTeam;

use BayAlper10\EggWarsTeam\GameSender;

Class Resetmap
{
    public $main;

    public function __construct(GameSender $main){
        $this->main = $main;
    }

    public function reload($lev)
    {
            $name = $lev->getFolderName();
            if ($this->main->p->getServer()->isLevelLoaded($name))
            {
                    $this->main->p->getServer()->unloadLevel($this->main->p->getServer()->getLevelByName($name));
            }
            $zip = new \ZipArchive;
            $zip->open($this->main->p->getDataFolder() . 'arenas/' . $name . '.zip');
            $zip->extractTo($this->main->p->getServer()->getDataPath() . 'worlds');
            $zip->close();
            unset($zip);
            $this->main->p->getServer()->loadLevel($name);
            return true;
    }
}
