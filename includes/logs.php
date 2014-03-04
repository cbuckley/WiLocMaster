<script type="text/javascript" src="js/stomp.js"></script>

<script type="text/javascript">


$(document).ready(function() {
      if(window.WebSocket) {
          var url = "ws://fyp.cbuckley.com:61623";
	client = Stomp.client(url);

          // this allows to display debug logs directly on the web page
          client.debug = function(str) {
          	//console.log(str);
	  };
          
          client.connect("admin", "password", function(frame) {
            client.debug("connected to Stomp");
            client.subscribe("/queue/foo", function(message) {
		var body = JSON.parse(message.body)
		$("#logbox").append(body.IEEE80211_RADIOTAP_SSI);
	    });
          });
      }
    });
</script>

<h1 class="page-header">Live</h1>
<pre id="logbox">

</pre>
