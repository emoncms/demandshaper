CanvasRenderingContext2D.prototype.roundRect = function (x, y, w, h, r) {
  if (w < 2 * r) r = w / 2;
  if (h < 2 * r) r = h / 2;
  this.beginPath();
  this.moveTo(x+r, y);
  this.arcTo(x+w, y,   x+w, y+h, r);
  this.arcTo(x+w, y+h, x,   y+h, r);
  this.arcTo(x,   y+h, x,   y,   r);
  this.arcTo(x,   y,   x+w, y,   r);
  this.closePath();
  return this;
}

var battery = {

    element: false,
    x: 0,
    mousedown: false,
    touchdown: false,
    pad: 10,
    soc: 0.2,
    end_soc: 0.8,
    capacity: 20.0,
    charge_rate: 3.8,
    events_loaded: false,

    init: function(element) {
        battery.element = element;
        
        // Dimentions
        battery.width = $("#"+element+"_bound").width()-5;
        if (battery.width>400) battery.width = 400;
        battery.height = battery.width*0.4;
        $("#"+element+"_bound").css("height",battery.height);
        $("#"+element).attr("width",battery.width);
        $("#"+element).attr("height",battery.height);

        if (!battery.events_loaded) battery.events();
    },
    
    draw: function() {
      
        var width = battery.width;
        var height = battery.height;
        var pad = battery.pad;
    
        // Limits
        if (battery.soc<0.0) battery.soc = 0.0;
        if (battery.soc>1.0) battery.soc = 1.0;
        if (battery.end_soc<0.0) battery.end_soc = 0.0;
        if (battery.end_soc>1.0) battery.end_soc = 1.0;
        if (battery.end_soc<battery.soc) battery.end_soc = battery.soc;
                
        // ctx
        var c = document.getElementById(battery.element);  
        var ctx = c.getContext("2d");
        ctx.clearRect(0,0,width,height);
         
        ctx.textAlign = "center";
        ctx.font="15px Arial"
        ctx.lineWidth = 2;
        
        var batteryWidth = width-pad*2;
        var batteryDiv = batteryWidth / 10;

        ctx.fillStyle = "#ddd";
        //ctx.fillRect(pad*0.5,pad*0.5,batteryWidth+pad,75+pad);   
        ctx.roundRect(pad*0.5,pad*0.5,batteryWidth+pad,75+pad, 5).fill(); //or .fill() for a filled rect 
        ctx.fillStyle = "rgba(0,255,0,0.1)";
        ctx.fillRect(pad,pad,batteryWidth*battery.end_soc,75);
        ctx.fillStyle = "rgba(0,255,0,0.4)";
        ctx.fillRect(pad,pad,batteryWidth*battery.soc,75);
        
        ctx.strokeStyle = "#fff";
        ctx.setLineDash([5,5]);
        for (var i=1; i<10; i++) {
            ctx.beginPath();
            ctx.moveTo(pad+i*batteryDiv,pad);
            ctx.lineTo(pad+i*batteryDiv,pad+75);
            ctx.stroke();
        }
        
        ctx.setLineDash([]);
        ctx.strokeRect(pad,pad,batteryWidth,75);
        
        ctx.fillStyle = "#666";
        ctx.fillText(Math.round(battery.soc*100)+'%',pad+(batteryWidth*battery.soc),pad + 100);
     
        if (battery.end_soc>0.95) ctx.textAlign = "right";
        if (battery.end_soc>battery.soc+0.05) {
            ctx.fillStyle = "#666";
            ctx.fillText(Math.round(battery.end_soc*100)+'%',pad+(batteryWidth*battery.end_soc),pad + 100);
        }
        
        var kwh = (battery.end_soc - battery.soc) * battery.capacity;
        var time_left = kwh / battery.charge_rate;
        battery.period = time_left;
        
        var h = Math.floor(time_left);
        var m = Math.round((time_left - h)*60);
        
        ctx.textAlign = "left";
        ctx.fillText("Time left: "+h+" hours "+m+" mins",pad*0.5,140);
        
        ctx.textAlign = "right";
        ctx.fillText(kwh.toFixed(1)+" kWh",width-pad*0.5,140);
    },
    
    events: function() {
        battery.events_loaded = true;
        element = "#"+battery.element;
    
        $(element).mousedown(function( event ) {
           battery.mousedown = true;
           battery.adjust_charge(event.offsetX);
        });

        $(element).mouseup(function( event ) {
           battery.mousedown = false;
           battery.adjust_charge(event.offsetX);
           $("#"+battery.element).trigger("bchange");
        });

        $(element).mousemove(function( event ) {
            battery.adjust_charge(event.offsetX);
        });

        $(element).bind("touchstart",function( event ) {
            battery.touchdown = true;
            var touch = event.originalEvent.touches[0] || event.originalEvent.changedTouches[0];
            battery.adjust_charge(touch.pageX);
        });

        $(element).bind("touchend",function( event ) {
            battery.touchdown = false;
            var touch = event.originalEvent.touches[0] || event.originalEvent.changedTouches[0];
            battery.adjust_charge(touch.pageX);
            $("#"+battery.element).trigger("bchange");
        });

        $(element).bind("touchmove",function( event ) {
            event.preventDefault();
            var touch = event.originalEvent.touches[0] || event.originalEvent.changedTouches[0];
            battery.adjust_charge(touch.pageX);
        });
    },
    
    adjust_charge: function(mx) {
        
        var batteryWidth = battery.width-battery.pad*2;
        var batteryDiv = batteryWidth / 10;
        mx -= battery.pad;

        if ((battery.mousedown || battery.touchdown) && mx!=undefined && mx>0) {
            var lx = battery.x;
            battery.x = Math.round(mx/batteryDiv)*batteryDiv;
            if (battery.x!=lx) {
                battery.end_soc = battery.x/batteryWidth
                battery.draw();
            }
        }
    }
}
