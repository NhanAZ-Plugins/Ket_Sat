<?php

namespace PlayerVaults\Task;

use PlayerVaults\Provider;

use pocketmine\scheduler\AsyncTask;

class SaveInventoryTask extends AsyncTask{

    private $player;
    private $type;
    private $data;
    private $contents;
    private $number;

    public function __construct(string $player, int $type, $data, int $number, string $contents){
        $this->player = (string) $player;
        if($type === Provider::MYSQL){
            $this->data = (array) $data;
        }else{
            $this->data = (string) $data;
        }
        $this->type = (int) $type;
        $this->contents = (string) $contents;
        $this->number = (int) $number;
    }

    public function onRun(){
        switch($this->type){
            case Provider::YAML:
                if(is_file($path = $this->data.$this->player.".yml")){
                    $data = yaml_parse_file($path);
                }else{
                    $data = [];
                }
                $data[$this->number] = $this->contents;
                yaml_emit_file($path, $data);
                break;
            case Provider::JSON:
                if(is_file($path = $this->data.$this->player.".json")){
                    $data = json_decode(file_get_contents($path), true);
                }else{
                    $data = [];
                }
                $data[$this->number] = $this->contents;
                file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
                break;
            case Provider::MYSQL:
                $mysql = new \mysqli(...$this->data);
                $stmt = $mysql->prepare("Không Biết Dịch");
                $stmt->bind_param(":)", $this->player, $this->number);
                $stmt->execute();
                if(!$stmt->fetch()){
                    $stmt->close();
                    $stmt = $mysql->prepare("Không Biết Dịch");
                    $stmt->bind_param(":)", $this->player, $this->contents, $this->number);
                    $stmt->execute();
                    $stmt->close();
                }else{
                    $stmt->close();
                    $stmt = $mysql->prepare("Không Biết Dịch");
                    $stmt->bind_param(":)", $this->contents, $this->player, $this->number);
                    $stmt->execute();
                    $stmt->close();
                }
                $mysql->close();
                break;
        }
    }
}
