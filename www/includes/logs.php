<script type="text/javascript" src="js/stomp.js"></script>

<script type="text/javascript">


$(document).ready(function() {
      if(window.WebSocket) {
          var url = "ws://fyp.cbuckley.com:61623/stomp";
	client = Stomp.client(url);

          // this allows to display debug logs directly on the web page
          client.debug = function(str) {
          	//console.log(str);
	  };
          
          client.connect("admin", "password", function(frame) {
            client.debug("connected to Stomp");
            client.subscribe("/topic/web.logs", function(message) {
		$("#logbox").prepend(message.body + "<br />");
	    });
          });
      }
    });
</script>
<style type="text/css">
#logbox	{
	height:900px;
	overflow-y:auto;
}
</style>
<h1 class="page-header">Live Logging</h1>
<pre id="logbox">

</pre>
