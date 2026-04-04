<?php

namespace PlayerVaults;

use PlayerVaults\Vault\Vault;

use pocketmine\command\{Command, CommandSender};
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Tile;
use pocketmine\utils\TextFormat as TF;

class PlayerVaults extends PluginBase{

    private $data = null;
    private $mysqldata = [];
    private static $instance = null;
    private $parsedConfig = [];

    public function onEnable(){
        self::$instance = $this;
        $this->getLogger()->notice(implode(['§f•§a Plugin Đã Hoạt Động §f•']));

        if(!is_dir($this->getDataFolder())){
            mkdir($this->getDataFolder());
        }
        if(!is_dir($this->getDataFolder()."vaults")){
            mkdir($this->getDataFolder()."vaults");
        }
        if(!file_exists($this->getDataFolder()."config.yml")){
            $this->saveDefaultConfig();
        }

        $this->updateConfig();
        $this->registerConfig();

        $type = $this->getConfig()->get("provider", "json");
        $type = Provider::TYPE_FROM_STRING[strtolower($type)] ?? Provider::UNKNOWN;
        $this->mysqldata = array_values($this->getConfig()->get("mysql", []));
        $this->maxvaults = $this->getConfig()->get("max-vaults", 25);
        if($type === Provider::MYSQL){
            $mysql = new \mysqli(...$this->mysqldata);
            $db = $this->mysqldata[3];
            $mysql->query("§f•§a Không Biết Dịch :) §f•");
            $mysql->close();
        }
        $this->data = new Provider($type);

        Tile::registerTile(Vault::class);
    }

    private function updateConfig(){
        $config = $this->getConfig();
        foreach(yaml_parse(stream_get_contents($this->getResource("config.yml"))) as $key => $value){
            if($config->get($key) === false){
                $config->set($key, $value);
            }
        }
        $config->save();
    }

    private function registerConfig(){
        $this->parsedConfig = yaml_parse_file($this->getDataFolder()."config.yml");
    }

    public function getFromConfig($key){
        return $this->parsedConfig[$key] ?? null;
    }

    public function getData() : Provider{
        return $this->data;
    }

    public function getMysqlData() : array{
        return $this->mysqldata;
    }

    public function getMaxVaults() : int{
        return $this->maxvaults;
    }

    public static function getInstance() : self{
        return self::$instance;
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args){
        if(isset($args[0]) && $args[0] !== "help" && $args[0] !== ""){
            if(is_numeric($args[0])){
                if(strpos($args[0], ".") !== false){
                    $sender->sendMessage("§f•§a Vui Lòng Chèn Một Số Hợp Lệ §f•");
                }elseif($args[0] < 1 || $args[0] > $this->getMaxVaults()){
                    $sender->sendMessage("§f•§a Xin Hãy Sử Dụng Lệnh: §b/ketsat <1-".$this->getMaxVaults()."> §f•");
                }else{
                    if($sender->y + Provider::INVENTORY_HEIGHT > Level::Y_MAX){
                        $sender->sendMessage("§f•§a Vui Lòng Hạ Độ Cao Để Mở Két Sắt Để Tránh Bay Đồ §f•");
                    }else{
                        if($sender->hasPermission("ketsat.command".$args[0])){
                            $sender->sendMessage("§f•§a Đang Mở Két Sắt Số:§b ".$args[0]." §f•");
                            $this->getData()->sendContents($sender, $args[0]);
                        }else{
                            $sender->sendMessage("§f•§a Bạn Không Có Quyền Truy Cập Két Sắt §f•");
                        }
                    }
                }
            }else{
                if($sender->isOp()){
                    switch(strtolower($args[0])){
                        case "of":
                            if(!isset($args[1])){
                                $sender->sendMessage("§f•§a Xin Hãy Sử Dụng: §b/$cmd of <Tên Người Chơi> <Số Két Sắt> §f•");
                            }else{
                                if(($player = $this->getServer()->getPlayer($args[1])) !== null){
                                    $args[1] = $player->getLowerCaseName();
                                    $player = $player->getName();
                                }
                                $args[2] = $args[2] ?? 1;
                                if(!is_numeric($args[2])){
                                    $sender->sendMessage("§f•§a Xin Hãy Sử Dụng: §b/$cmd of <Tên Người Chơi> <1-".$this->getMaxVaults()."> §f•");
                                    break;
                                }
                                $this->getData()->sendContents($args[1], $args[2] ?? 1, $sender->getName());
                                $sender->sendMessage("§f•§a Đang Mở Két Sắt Số: §b".($args[2] ?? 1)." §aCủa:§b ".($player ?? $args[1])." §f•");
                            }
                            break;
                        case "empty":
                            if(!isset($args[1])){
                                $sender->sendMessage("§f•§a Xin Hãy Sử Dụng: §b/$cmd empty <Tên Người Chơi> <Số Két Sắt|all> §f•");
                            }else{
                                if(($player = $this->getServer()->getPlayerExact($args[1])) !== null){
                                    $args[1] = $player->getLowerCaseName();
                                    $player = $player->getName();
                                }
                                if(!isset($args[2]) || ($args[2] != "all" && !is_numeric($args[2]))){
                                    $sender->sendMessage("§f•§a Xin hãy sử dụng: §b/$cmd empty <Tên Người Chơi> <Số Két Sắt|all> §f•");
                                }else{
                                    if((is_numeric($args[2]) && ($args[2] >= 1 || $args[2] <= $this->getMaxVaults())) || $args[2] == "all"){
                                        $this->getData()->deleteVault(strtolower($player ?? $args[1]), $args[2] == "all" ? -1 : $args[2]);
                                        if($args[2] == "all"){
                                            $sender->sendMessage("§f•§a Đã Xóa Tất Cả Vật Phẩm Trong Két Sắt Của:§b ".($player ?? $args[1])." §f•");
                                        }else{
                                            $sender->sendMessage("§f•§a Đã Xóa Vật Phẩm Trong Két Sắt Số:§b ".$args[2]." §aCủa:§b ".($player ?? $args[1])." §f•");
                                        }
                                    }else{
                                        $sender->sendMessage("§f•§a Xin Hãy Sử Dụng:§b /$cmd empty ".$args[1]." <1-".$this->getMaxVaults()."> §f•");
                                    }
                                }
                            }
                            break;
                    }
                }
                switch(strtolower($args[0])){
                    case "admin":
                        $sender->sendMessage(implode(["§f• §b/$cmd of <Tên Người Chơi> <Số Két Sắt> §f-§a Để Kiểm Tra Két Sắt Của Người Chơi §f•\n§f• §b/$cmd empyt <Tên Người Chơi> <Số Két Sắt|all> §f-§a Để Xóa Vật Phẩm Có Trong Két Sắt Của Người Chơi §f•"
                        ]));
                        break;
                }
            }
        }else{
            $sender->sendMessage(implode(["§f• §b/$cmd <Số Két Sắt> §f-§a Để Mở Két Sắt §f•"
            ]));
            if($sender->isOp()){
                $sender->sendMessage("§f• §b/$cmd admin §f-§a Để Xem Lệnh Của Admin §f•");
            }
        }
    }
}
