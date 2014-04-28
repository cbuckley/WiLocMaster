#!/usr/bin/perl
use DBI;
use Net::Stomp;
use Data::Dumper;
use JSON;
use Switch;
use Math::Trig;
use POSIX qw(strftime);
use Math::Vector::Real;
use Math::Complex;
Math::Complex::display_format('cartesian');
use strict;
$| = 1;

my $dbh = DBI->connect("DBI:mysql:wiloc:localhost", 'wiloc', 'q9MF2Jbed9S7DPmP') or die "Can't connect to db";
my $json = JSON->new->allow_nonref;
my $stomp = Net::Stomp->new( { hostname => 'localhost', port => '61613' } );
my $connection = $stomp->connect( { login => 'admin', passcode => 'password' } );

die("STOMP Connection Failed") if $connection->command eq "ERROR";

$stomp->subscribe(
	{
		destination             => '/topic/packets',
		'ack'                   => 'client',
        	'activemq.prefetchSize' => 1
      	}
);
#print "    CNTRL   	" . "   SOURCE   	"."DESTINATION	". "    SSI    "\n";
#print "------------	------------	------------	------------\n";

my $tickers;
while (1) {
	my $frame = $stomp->receive_frame;
	my $packet = $json->decode($frame->body );
	if(!$packet->{IEEE80211_FROM_DS} && $packet->{IEEE80211_TO_DS})	{ # is this a wireless client
		my $sql = "select * from clients where clMac = ?";
		my $sth = $dbh->prepare($sql);
		$sth->execute($packet->{IEEE80211_ADDR2_HEX}); #Get the client from the DB
		my $tick;
		if($sth->rows())	{ #if the client is in the DB
			$tick = $sth->fetchrow_hashref();
			$tick->{clSSI} = $packet->{IEEE80211_RADIOTAP_SSI}; # get the SSI
			$tick->{coId} = $packet->{CONTROLLER_ID}; # Get the controller that saw it
			if($tick->{clActive})	{ # is the client active
				$sql = "INSERT INTO `wiloc`.`ticks` (`clMac`, `tSSI`, `coId`, `tSeen`) VALUES (?, ?, ?, NOW())";
				$sth = $dbh->prepare($sql);
				$sth->execute($packet->{IEEE80211_ADDR2_HEX}, $packet->{IEEE80211_RADIOTAP_SSI}, $packet->{CONTROLLER_ID}); # Insert the packet into the DB
				$sql = "SELECT LAST_INSERT_ID()"; # Get the ID of the inserted packet
				$sth = $dbh->prepare($sql);
				$sth->execute();
				($packet->{tId}) = $sth->fetchrow_array();
				$tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$packet->{CONTROLLER_ID}}{time} = time(); # New perl time for reference and wrap in active tickers hash
				$tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$packet->{CONTROLLER_ID}}{SSI} = $packet->{IEEE80211_RADIOTAP_SSI}; # Get the SSI and put into tickers hash
				$tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$packet->{CONTROLLER_ID}}{tId} = $packet->{tId}; # get the tick ID and put into tickers hash
#				$tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$packet->{CONTROLLER_ID}}{ticks} = $packet;
				my $tbreak = 1  ; # initialise the breaker variable for if summary is needed
				my $keys = scalar keys $tickers->{$packet->{IEEE80211_ADDR2_HEX}}; # How many tickets are active for the mac
				$tbreak = 0 if !($keys == 3); # 3 ticks don't exist, don't bother summarising
				foreach my $tkey (keys $tickers->{$packet->{IEEE80211_ADDR2_HEX}})	{ # GO through the tickers
					if ($tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$tkey}{time} < time - 10)	{ #if the tick was NOT last seen within 10 seconds
						$tbreak = 0; # don't summarise
						delete $tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$tkey}; # this key is no longer useful, delete it!
					}
					
				}	
				generateSummary($tickers->{$packet->{IEEE80211_ADDR2_HEX}}, $packet->{IEEE80211_ADDR2_HEX}) if $tbreak; #if tbreak is set then we summarise
				print $packet->{CONTROLLER_ID} . "      " . $packet->{IEEE80211_ADDR2_HEX}."    ".$packet->{IEEE80211_ADDR3_HEX}."          ".$packet->{IEEE80211_RADIOTAP_SSI}."       ".$keys."\n"; # Print the tick
			}
		}
		else	{
			#Not currently tracking, set the vars anyway so that they can still make it to the clients screen
			$tick->{clMac} = $packet->{IEEE80211_ADDR2_HEX};
			$tick->{clActive} = 0;
			$tick->{clSSI} = $packet->{IEEE80211_RADIOTAP_SSI};
			$tick->{coId} = $packet->{CONTROLLER_ID};
		}
		my $jsonstr = $json->encode($tick); #send the tick
		$stomp->send({ destination => '/topic/web.ticks',body => $jsonstr });
	}
	$stomp->ack( { frame => $frame } );
}

sub toRad	{
	my ($val) = @_;
	$val * pi / 180;
}
sub toDeg       {
        my ($val) = @_;
        $val * 180 / pi;
}

sub generateSummary	{
        my ($mac, $macAddr) = @_;
        my ($sql, $sth, $result);
	my $date = strftime "%m/%d/%Y %H:%I:%S", localtime;
	my $detail = "$date - Summarised $macAddr (";
	
	print "Begin Trilateration\n";
	foreach my $key (keys $mac)	{
		$sql = "SELECT * FROM controllerLocs where coId = ? ORDER BY clTimestamp DESC LIMIT 1";
		$sth = $dbh->prepare($sql);
		$sth->execute($key);
		$result = $sth->fetchrow_hashref();#Get the locations of the controllers
		$mac->{$key}{lat} = $result->{clLat};
		$mac->{$key}{lon} = $result->{clLon};
		#Distance lookup
		$mac->{$key}{distance} = 7 if $mac->{$key}{SSI} > -55;
		$mac->{$key}{distance} = 6 if $mac->{$key}{SSI} <= -55;
		$mac->{$key}{distance} = 5 if $mac->{$key}{SSI} <= -60;
		$mac->{$key}{distance} = 4 if $mac->{$key}{SSI} <= -65;
		$mac->{$key}{distance} = 3 if $mac->{$key}{SSI} <= -70;
		$mac->{$key}{distance} = 2 if $mac->{$key}{SSI} <= -80;
		$mac->{$key}{distance} = 1 if $mac->{$key}{SSI} <= -90;
		$mac->{$key}{distance} /= 1000;
		$detail .= "$key = ".$mac->{$key}{distance} . " ";
	}
	$detail .=")";
	my @keys = keys $mac;
	my $earthR = 6371;
	#convert to ECEF from lat lng
	my $xA = $earthR *(cos(toRad($mac->{$keys[0]}{lat})) * cos(toRad($mac->{$keys[0]}{lon})));
	my $yA = $earthR *(cos(toRad($mac->{$keys[0]}{lat})) * sin(toRad($mac->{$keys[0]}{lon})));
	my $zA = $earthR *(sin(toRad($mac->{$keys[0]}{lat})));

        my $xB = $earthR *(cos(toRad($mac->{$keys[1]}{lat})) * cos(toRad($mac->{$keys[1]}{lon})));
        my $yB = $earthR *(cos(toRad($mac->{$keys[1]}{lat})) * sin(toRad($mac->{$keys[1]}{lon})));
        my $zB = $earthR *(sin(toRad($mac->{$keys[1]}{lat})));

        my $xC = $earthR *(cos(toRad($mac->{$keys[2]}{lat})) * cos(toRad($mac->{$keys[2]}{lon})));
        my $yC = $earthR *(cos(toRad($mac->{$keys[2]}{lat})) * sin(toRad($mac->{$keys[2]}{lon})));
        my $zC = $earthR *(sin(toRad($mac->{$keys[2]}{lat})));

	#initialise ECEF as vectors
	my $P1 = V($xA,$yA,$zA);
	my $P2 = V($xB,$yB,$zB);
	my $P3 = V($xC,$yC,$zC);
	
	#Trilateration Algorithm
        my $a = $P2 - $P1;
	my $b = abs($P2 - $P1);
	my $ex = $a / $b;
	my $c = $P3 - $P1;
        my $i = $ex * $a;
	my $intermediate = ($P3 - $P1) - ($ex * $i);
	my $ey = $intermediate / abs($intermediate);
      	my $ez = $ex x $ey;
	my $d = abs($P2 - $P1);
	my $j = $ey * ($P3 - $P1);
	my $x = ($mac->{$keys[0]}{distance} ** 2 - $mac->{$keys[1]}{distance} ** 2 + $d ** 2)/(2*$d);

	#final trilaterated ECEF format
	my $y = (($mac->{$keys[0]}{distance} **2 - $mac->{$keys[2]}{distance} ** 2 + $i ** 2 + $j ** 2)/(2*$j)) - (($i/$j)*$x);
	my $z = sqrt($mac->{$keys[0]}{distance} ** 2 - $x ** 2 - $y ** 2);
	my $z = sprintf("%.17f", $z);

	#convert back to lat lng
	my $triPt = $P1 + ($ex * $x) + ($ey * $y) + ($ez * $z);
	my $lat = toDeg(asin($triPt->[2] / $earthR));
	my $lon = toDeg(atan2($triPt->[1],$triPt->[0]));

	my $x = $lat;
	my $y = $lon;
	$sql = "INSERT INTO `wiloc`.`clientSummary` (`clMac`, `csGenerated`, `csLat`, `csLon`) VALUES (?, NOW(), ?, ?)";
	$sth = $dbh->prepare($sql);
	$sth->execute($macAddr, $x, $y); # Add the summary location to the DB
	$sql = "SELECT LAST_INSERT_ID()";
	$sth = $dbh->prepare($sql);
	$sth->execute();
	my ($csid) = $sth->fetchrow_array();
	foreach my $key (keys $mac)     {
		$sql = "INSERT INTO `wiloc`.`clientTicks` (`csId`, `tId`) VALUES (?, ?)";
		$sth = $dbh->prepare($sql);
		$sth->execute($csid, $mac->{$key}{tId}); # Associate the client ticks to the summary through the clientTicks table
	}
	print "Location: $x, $y\n";
	my $summary;
	$summary->{clMac} = $macAddr;
	$summary->{clLat} = $x;
	$summary->{clLon} = $y;
	$summary->{controllers} = $mac;
	my $jsonstr = $json->encode($summary);
	$detail .= " Estimated Location: ".$summary->{clLat} .",".$summary->{clLon};
        $stomp->send({ destination => '/topic/web.summary',body => $jsonstr }); # Send the summary to the web queue
	$stomp->send({ destination => '/topic/web.logs',body => $detail }); # send the log to the web log queue
}
