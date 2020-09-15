import time
import paho.mqtt.client as mqtt

device = "openevse"
mqtt_user = "emonpi"
mqtt_passwd = "emonpimqtt2016"
mqtt_host = "localhost"
mqtt_port = 1883

#basetopic = "user"
basetopic = "emon" # multiuser

amp = 0
wh = 0
temp = 10
timer = "1 30 3 30"
           
def on_connect(client, userdata, flags, rc):
    # Initialisation string
    mqttc.subscribe(basetopic+"/"+device+"/rapi/#")
    pass

def on_message(client, userdata, msg):
    global ctrlmode, timer, amp
    
    print msg.topic+": "+msg.payload

    # On
    if msg.topic==basetopic+"/"+device+"/rapi/in/$FE":
        print "state: on"
        amp = 15.4

    # Off
    if msg.topic==basetopic+"/"+device+"/rapi/in/$FS":
        print "state: off"
        amp = 0.0
    
    # Set timer:
    if msg.topic==basetopic+"/"+device+"/rapi/in/$ST":
        print "set timer: "+msg.payload
        timer = msg.payload
    
    # Fetch and return smartplug state
    if msg.topic==basetopic+"/"+device+"/rapi/in/$GS":
        print "fetch state"
        if amp==0: 
            mqttc.publish(basetopic+"/"+device+"/rapi/out","0 254",2)
        else:
            mqttc.publish(basetopic+"/"+device+"/rapi/out","0 1",2)

    if msg.topic==basetopic+"/"+device+"/rapi/in/$GD":
        print "fetch timer state"
        mqttc.publish(basetopic+"/"+device+"/rapi/out","$OK "+timer,2)   

mqttc = mqtt.Client("openevse-remote")
mqttc.on_connect = on_connect
mqttc.on_message = on_message

# Connect
try:
    mqttc.username_pw_set(mqtt_user, mqtt_passwd)
    mqttc.connect(mqtt_host, mqtt_port, 60)
    mqttc.loop_start()
except Exception:
    print "Could not connect to MQTT"
else:
    print "Connected to MQTT"

time.sleep(1)

# Loop
while 1:

    power = 240 * amp
    wh += (power*10)/3600.0
    dT = temp - 6.0
    temp += (power*0.001) - (dT*0.10)
 
    mqttc.publish(basetopic+"/"+device+"/amp",amp*1000,2)
    mqttc.publish(basetopic+"/"+device+"/wh",wh,2)
    mqttc.publish(basetopic+"/"+device+"/temp1",temp*10,2)
    mqttc.publish(basetopic+"/"+device+"/temp2",-2560,2)
    mqttc.publish(basetopic+"/"+device+"/temp3",-2560,2)
    mqttc.publish(basetopic+"/"+device+"/pilot",32,2)
    mqttc.publish(basetopic+"/"+device+"/state",254,2)
    time.sleep(10)

# Close
mqttc.loop_stop()
mqttc.disconnect()


