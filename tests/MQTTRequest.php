<?php
class MQTTRequest
{
    // MQTT Server details
    private $host;
    private $port;
    private $username;
    private $password;
    private $clientId;
    
    // State
    private $client;
    private $state;
    
    private $request;
    private $result;

    public function __construct($mqtt_server)
    {
        $this->host = $mqtt_server['host'];
        $this->port = $mqtt_server['port'];
        $this->username = $mqtt_server['user'];
        $this->password = $mqtt_server['password'];
    }
    
    public function request($topic,$payload,$result_topic)
    {
        $this->request = new stdClass();
        $this->request->topic = $topic;
        $this->request->payload = $payload;
        
        $this->result = new stdClass();
        $this->result->topic = $result_topic;
        $this->result->payload = false;
        
        $this->client = new Mosquitto\Client();
        
        $this->client->onConnect(function($r, $message){
            $this->connect($r, $message);
        });
        $this->client->onDisconnect(function(){
            $this->disconnect();
        });
        $this->client->onSubscribe(function(){
            $this->subscribe();
        });
        $this->client->onMessage(function($message){
            $this->message($message);
        });
                   
        $this->state = 0; // 0: startfetch
                    // 1: connected
                    // 2: subscribed
                    // 3: complete

        $this->client->setCredentials($this->username,$this->password);
        $this->client->connect($this->host, $this->port, 5);
               
        $start = time();
        while((time()-$start)<10.0) {
            try { 
                $this->client->loop(10); 
            } catch (Exception $e) {
                if ($this->state) return "error: ".$e;
            }
            
            if ((time()-$start)>=3.0) {
                $this->client->disconnect();
            }
            
            if ($this->state==3) break;
        }
        
        if ($this->result->payload) {
            return $this->result->payload;
        } else {
            return false;
        }
    }
    
    private function connect($r, $message) {
        if( $r==0 ) {
            $this->state = 1;
            $this->client->subscribe($this->result->topic,2);
        } else {
            $this->client->disconnect();
        }
    }

    private function subscribe() {
        $this->client->publish($this->request->topic, $this->request->payload);
    }

    private function unsubscribe() {
        $this->state = 1;
    }

    private function disconnect() {
        $this->state = 3;
    }

    private function message($message) {
        $this->result->topic = $message->topic;
        $this->result->payload = $message->payload;
        $this->client->disconnect();
    }
}

