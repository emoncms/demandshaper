import time, random
import paho.mqtt.client as mqtt
           
# -------------------------------------------------------------------------------------
# Connect to EmonPi MQTT server 
# -------------------------------------------------------------------------------------
def emonpi_on_connect(client, userdata, flags, rc):
    # mqtt_emonpi.subscribe("emon/#")
    pass

def emonpi_on_message(client, userdata, msg):
    # print msg.topic+": "+msg.payload
    pass
    
mqtt_emonpi = mqtt.Client("ovms_bridge_"+str(time.time())[6:-3])
mqtt_emonpi.on_connect = emonpi_on_connect
mqtt_emonpi.on_message = emonpi_on_message

# Connect to Emoncms Local MQTT Server
try:
    mqtt_emonpi.username_pw_set("emonpi", "emonpimqtt2016")
    mqtt_emonpi.connect("emonpi.local", 1883, 60)
    mqtt_emonpi.loop_start()
except Exception:
    print "Could not connect to emonPi MQTT server"
else:
    print "Connected to emonPi MQTT server"
    
# -------------------------------------------------------------------------------------
# Connect to OVMS MQTT server 
# -------------------------------------------------------------------------------------
def ovms_on_connect(client, userdata, flags, rc):
    mqtt_ovms.subscribe("user/1/ovms/metric/v/b/soc")

def ovms_on_message(client, userdata, msg):
    print msg.topic+": "+msg.payload
    mqtt_emonpi.publish("emon/openevse/soc",msg.payload,2)
    
mqtt_ovms = mqtt.Client("ovms_bridge_"+str(time.time())[6:-3])
mqtt_ovms.on_connect = ovms_on_connect
mqtt_ovms.on_message = ovms_on_message

# Connect to OVMS MQTT Server
try:
    mqtt_ovms.tls_set(ca_certs="/usr/share/ca-certificates/mozilla/DST_Root_CA_X3.crt")
    mqtt_ovms.username_pw_set("username", "pass")
    mqtt_ovms.connect("host", 8883, 60)
    mqtt_ovms.loop_start()
except Exception:
    print "Could not connect to OVMS MQTT server"
else:
    print "Connected to OVMS MQTT server"
    
# -------------------------------------------------------------------------------------

# Loop
while 1:
    time.sleep(10)

# Close
mqtt_ovms.loop_stop()
mqtt_ovms.disconnect()
mqtt_emonpi.loop_stop()
mqtt_emonpi.disconnect()
