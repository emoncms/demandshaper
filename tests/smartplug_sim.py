import time
import paho.mqtt.client as mqtt

device = "smartplug1272"
mqtt_user = "emonpi"
mqtt_passwd = "emonpimqtt2016"
mqtt_host = "localhost"
mqtt_port = 1883

basetopic = "emon"
# basetopic = "user/"+str(userid) # multiuser

ctrlmode = "off"
timer = "0000 0000 0000 0000"
           
def on_connect(client, userdata, flags, rc):
    # Initialisation string
    mqttc.subscribe(basetopic+"/"+device+"/in/#")
    pass

def on_message(client, userdata, msg):
    global ctrlmode, timer
    
    print msg.topic+": "+msg.payload

    # Set control mode: On, Off, Timer
    if msg.topic==basetopic+"/"+device+"/in/ctrlmode":
        ctrlmode = msg.payload
    
    # Set timer: start1 stop1 start2 stop2
    if msg.topic==basetopic+"/"+device+"/in/timer":
        ctrlmode = "Timer"
        timer = msg.payload
    
    # Fetch and return smartplug state
    if msg.topic==basetopic+"/"+device+"/in/state":
        value = '{"ip":"192.168.1.71","time":0,"ctrlmode":"'+ctrlmode+'","timer":"'+timer+'","vout":0}'
        print basetopic+"/"+device+"/out/state"+" "+value+"\n"
        mqttc.publish(basetopic+"/"+device+"/out/state",value,2)

mqttc = mqtt.Client("smartplug-remote")
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
    # Sent a device parameter
    topic = basetopic+"/"+device+"/temperature"
    value = 18.5
    mqttc.publish(topic,value,2)
    time.sleep(10)

# Close
mqttc.loop_stop()
mqttc.disconnect()


