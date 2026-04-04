<?php

namespace PlayerVaults\Vault;

use PlayerVaults\PlayerVaults;

use pocketmine\inventory\{ChestInventory, InventoryType};
use pocketmine\Player;

class VaultInventory extends ChestInventory{

    public function __construct(Vault $tile){
        parent::__construct($tile, InventoryType::get(InventoryType::CHEST));
    }

    public function onClose(Player $who){
        if(isset($this->getHolder()->namedtag->Vault)){
            PlayerVaults::getInstance()->getData()->saveContents($this->getHolder(), $this->getContents());
        }
        $this->holder->sendReplacement($who);
        $this->holder->close();
    }
}